<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Scopes\SchoolScope;
use App\Models\Student;
use App\Models\StudentPayment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BillingService
{
    public static function cents(string|float|int|null $value): int
    {
        $decimal = number_format((float) ($value ?? 0), 2, '.', '');

        return (int) str_replace('.', '', $decimal);
    }

    public function summaries(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }
        $bills = Invoice::whereIn('student_id', $studentIds)->selectRaw('student_id, SUM(amount) as billed, SUM(amount_paid) as applied')->groupBy('student_id')->get()->keyBy('student_id');
        $payments = StudentPayment::whereIn('student_id', $studentIds)->where('status', 'completed')->selectRaw('student_id, SUM(amount) as received')->groupBy('student_id')->get()->keyBy('student_id');
        $adjustments = Transaction::whereIn('student_id', $studentIds)->where('status', 'completed')->whereIn('type', ['credit_transfer_in', 'credit_transfer_out', 'billing_opening'])->selectRaw('student_id, type, SUM(amount) as total')->groupBy('student_id', 'type')->get()->groupBy('student_id');
        $result = [];
        foreach ($studentIds as $id) {
            $types = ($adjustments->get($id) ?? collect())->keyBy('type');
            $billed = self::cents($bills->get($id)?->billed);
            $received = self::cents($payments->get($id)?->received);
            $incoming = self::cents($types->get('credit_transfer_in')?->total);
            $outgoing = self::cents($types->get('credit_transfer_out')?->total);
            $legacy = self::cents($types->get('billing_opening')?->total);
            $funds = $received + $incoming - $outgoing + $legacy;
            $net = $funds - $billed;
            $result[$id] = [
                'total_invoiced' => $billed / 100, 'total_bills' => $billed / 100,
                'total_paid' => $funds / 100, 'total_payments' => $funds / 100,
                'cash_payments' => $received / 100, 'credit_received' => $incoming / 100,
                'credit_transferred' => $outgoing / 100, 'legacy_paid_adjustment' => $legacy / 100,
                'net_balance' => $net / 100, 'total_outstanding' => max(-$net, 0) / 100,
                'credit_balance' => max($net, 0) / 100,
                'balance_status' => $net > 0 ? 'overpaid' : ($net < 0 ? 'debt' : 'settled'),
            ];
        }

        return $result;
    }

    public function summary(Student $student): array
    {
        return $this->summaries([$student->id])[$student->id];
    }

    // Every writer locks the student before locking payments or bills.
    public function applyCredit(Student $student, ?User $actor = null): void
    {
        DB::transaction(function () use ($student, $actor): void {
            $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $invoices = Invoice::where('student_id', $student->id)->where('balance', '>', 0)
                ->orderByRaw('COALESCE(due_date, DATE(created_at))')->orderBy('created_at')->orderBy('id')->lockForUpdate()->get();
            if ($invoices->isEmpty()) {
                return;
            }
            $used = Transaction::where('student_id', $student->id)->where('type', 'bill_allocation')->where('status', 'completed')->selectRaw('reference, SUM(amount) as total')->groupBy('reference')->get()->keyBy('reference');
            $sources = StudentPayment::where('student_id', $student->id)->where('status', 'completed')->get()->map(fn ($payment) => [
                'reference' => $payment->control_number, 'amount' => self::cents($payment->amount),
                'date' => $payment->completed_at?->toIso8601String() ?? $payment->created_at?->toIso8601String() ?? '', 'method' => $payment->payment_method,
            ])->all();
            foreach (Transaction::where('student_id', $student->id)->whereIn('type', ['credit_transfer_in', 'billing_opening'])->where('status', 'completed')->get() as $credit) {
                $sources[] = ['reference' => $credit->reference, 'amount' => self::cents($credit->amount), 'date' => $credit->created_at?->toIso8601String() ?? '', 'method' => 'credit'];
            }
            usort($sources, fn ($a, $b) => [$a['date'], $a['reference']] <=> [$b['date'], $b['reference']]);
            $legacyApplied = max(0, self::cents(Invoice::where('student_id', $student->id)->sum('amount_paid')) - self::cents($used->sum('total')));
            $consumed = $legacyApplied + self::cents(Transaction::where('student_id', $student->id)->where('type', 'credit_transfer_out')->where('status', 'completed')->sum('amount'));
            foreach ($sources as &$source) {
                $source['amount'] = max(0, $source['amount'] - self::cents($used->get($source['reference'])?->total));
                $deduct = min($consumed, $source['amount']);
                $source['amount'] -= $deduct;
                $consumed -= $deduct;
            }
            unset($source);
            foreach ($invoices as $invoice) {
                $remaining = self::cents($invoice->balance);
                $applied = 0;
                foreach ($sources as &$source) {
                    $amount = min($remaining, $source['amount']);
                    if ($amount <= 0) {
                        continue;
                    }
                    Transaction::create([
                        'transaction_ref' => 'ALLOC-'.Str::uuid(), 'student_id' => $student->id, 'invoice_id' => $invoice->id,
                        'type' => 'bill_allocation', 'amount' => $amount / 100, 'payment_method' => $source['method'],
                        'description' => 'FIFO allocation to '.$invoice->invoice_number, 'status' => 'completed',
                        'reference' => $source['reference'], 'created_by' => $actor?->id,
                    ]);
                    $source['amount'] -= $amount;
                    $remaining -= $amount;
                    $applied += $amount;
                    if ($remaining === 0) {
                        break;
                    }
                }
                unset($source);
                if ($applied > 0) {
                    $invoice->forceFill(['amount_paid' => (self::cents($invoice->amount_paid) + $applied) / 100, 'balance' => $remaining / 100, 'status' => $remaining === 0 ? 'paid' : 'partial'])->save();
                }
            }
        }, 3);
    }

    public function transfer(Student $source, Student $target, float $amount, User $actor, string $reason, ?string $requestId = null): array
    {
        abort_unless(in_array($actor->role, ['parent', 'accountant', 'super_admin'], true), 403);

        return DB::transaction(function () use ($source, $target, $amount, $actor, $reason, $requestId): array {
            $students = Student::whereIn('id', [$source->id, $target->id])->orderBy('id')->lockForUpdate()->get();
            abort_unless($students->count() === 2 && $source->id !== $target->id, 422, 'Choose two different students.');
            $family = User::where('role', 'parent')->whereHas('children', fn ($q) => $q->where('students.id', $source->id))->whereHas('children', fn ($q) => $q->where('students.id', $target->id));
            if ($actor->role === 'parent') {
                $family->whereKey($actor->id);
            }
            abort_unless($family->exists(), 403, 'Both students must be linked to the same parent.');
            $reference = 'CREDIT-'.($requestId ?: Str::uuid());
            $previous = Transaction::where('reference', $reference)->where('type', 'credit_transfer_out')->first();
            if ($previous) {
                $incoming = Transaction::where('reference', $reference)->where('type', 'credit_transfer_in')->first();
                if ((int) $previous->student_id !== (int) $source->id || ! $incoming || (int) $incoming->student_id !== (int) $target->id || self::cents($previous->amount) !== self::cents($amount)) {
                    throw ValidationException::withMessages(['request_id' => 'This transfer request has already been used with different details.']);
                }

                return ['reference' => $reference, 'source' => $this->summary($source), 'target' => $this->summary($target)];
            }
            if ($requestId && Transaction::withoutGlobalScope(SchoolScope::class)->where('transaction_ref', $reference.'-OUT')->exists()) {
                throw ValidationException::withMessages(['request_id' => 'This transfer request has already been used.']);
            }
            $cents = self::cents($amount);
            if ($cents <= 0 || $cents > self::cents($this->summary($source)['credit_balance'])) {
                throw ValidationException::withMessages(['amount' => 'The amount exceeds the available credit.']);
            }
            foreach ([[$source, 'credit_transfer_out', 'OUT'], [$target, 'credit_transfer_in', 'IN']] as [$student, $type, $suffix]) {
                Transaction::create(['transaction_ref' => $reference.'-'.$suffix, 'student_id' => $student->id, 'type' => $type, 'amount' => $cents / 100, 'payment_method' => 'credit', 'description' => $reason, 'status' => 'completed', 'reference' => $reference, 'created_by' => $actor->id]);
            }
            $this->applyCredit($target, $actor);

            return ['reference' => $reference, 'source' => $this->summary($source), 'target' => $this->summary($target)];
        }, 3);
    }
}
