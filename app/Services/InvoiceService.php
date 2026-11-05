<?php

namespace App\Services;

use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function createInvoice(array $data, User $issuer): Invoice
    {
        return DB::transaction(function () use ($data, $issuer): Invoice {
            $student = Student::whereKey($data['student_id'])->lockForUpdate()->firstOrFail();
            $fee = empty($data['fee_structure_id']) ? null : FeeStructure::findOrFail($data['fee_structure_id']);
            $period = trim($data['period'] ?? trim(($fee?->term ?? '').' '.($fee?->academic_year ?? ''))) ?: 'Legacy';
            $invoice = Invoice::create([
                'invoice_number' => $data['invoice_number'] ?? $this->nextInvoiceNumber(),
                'student_id' => $student->id, 'fee_structure_id' => $fee?->id,
                'period' => $period, 'description' => $data['description'] ?? null,
                'amount' => $data['amount'], 'amount_paid' => 0, 'balance' => $data['amount'],
                'status' => 'unpaid', 'due_date' => $data['due_date'] ?? null, 'issued_by' => $issuer->id,
            ]);
            app(BillingService::class)->applyCredit($student, $issuer);

            return $invoice->fresh();
        }, 3);
    }

    public function updateInvoice(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data): Invoice {
            $student = Student::whereKey($invoice->student_id)->lockForUpdate()->firstOrFail();
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if (array_key_exists('amount', $data) && BillingService::cents($data['amount']) < BillingService::cents($invoice->amount_paid)) {
                throw ValidationException::withMessages(['amount' => 'The invoice amount cannot be less than the amount already paid.']);
            }
            $invoice->fill(collect($data)->only(['amount', 'description', 'due_date', 'fee_structure_id', 'period'])->all());
            $this->recalculate($invoice);
            $invoice->save();
            app(BillingService::class)->applyCredit($student);

            return $invoice->fresh()->load(['student:id,full_name,student_number,class', 'feeStructure']);
        }, 3);
    }

    public function bulkGenerate(array $filters, User $issuer): array
    {
        $class = empty($filters['class_id']) ? null : SchoolClass::findOrFail($filters['class_id']);
        $fees = FeeStructure::where('is_active', true)
            ->when(! empty($filters['fee_structure_id']), fn ($q) => $q->whereKey($filters['fee_structure_id']))
            ->when($class, fn ($q) => $q->where(fn ($q) => $q->where('class_id', $class->id)->orWhere(fn ($q) => $q->whereNull('class_id')->where('school_level', $class->level))))
            ->when(! $class && ! empty($filters['school_level']), fn ($q) => $q->where('school_level', $filters['school_level']))
            ->when(empty($filters['fee_structure_id']) && ! empty($filters['term']), fn ($q) => $q->where('term', $filters['term']))
            ->when(empty($filters['fee_structure_id']) && ! empty($filters['academic_year']), fn ($q) => $q->where('academic_year', $filters['academic_year']))->orderBy('id')->get();
        if ($fees->isEmpty()) {
            throw ValidationException::withMessages(['fee_structure_id' => 'No active fee structures match the selected class.']);
        }
        $created = 0;
        $skipped = 0;
        DB::transaction(function () use ($fees, $filters, $class, $issuer, &$created, &$skipped): void {
            $created = 0;
            $skipped = 0;
            // Stable student order gives overlapping bulk requests the same lock order.
            $students = Student::where('is_active', true)
                ->when($class, fn ($q) => $q->where('class_id', $class->id))
                ->when(! $class && ! empty($filters['school_level']), fn ($q) => $q->where('school_level', $filters['school_level']))->orderBy('id')->lockForUpdate()->get();
            foreach ($students as $student) {
                foreach ($fees as $fee) {
                    if ($fee->class_id ? (int) $fee->class_id !== (int) $student->class_id : $fee->school_level !== $student->school_level) {
                        continue;
                    }
                    $period = trim($filters['period'] ?? trim(($filters['term'] ?? $fee->term ?? '').' '.($filters['academic_year'] ?? $fee->academic_year ?? ''))) ?: 'Legacy';
                    if (Invoice::where('student_id', $student->id)->where('fee_structure_id', $fee->id)->where('period', $period)->exists()) {
                        $skipped++;

                        continue;
                    }
                    $this->createInvoice(['student_id' => $student->id, 'fee_structure_id' => $fee->id, 'description' => $fee->name.' '.$period, 'period' => $period, 'amount' => $fee->amount, 'due_date' => $filters['due_date'] ?? null], $issuer);
                    $created++;
                }
            }
        }, 3);

        return ['created' => $created, 'skipped' => $skipped, 'fee_structures' => $fees->count()];
    }

    public function recalculate(Invoice $invoice): void
    {
        $amount = BillingService::cents($invoice->amount);
        $paid = BillingService::cents($invoice->amount_paid);
        $balance = max($amount - $paid, 0);
        $invoice->amount = $amount / 100;
        $invoice->amount_paid = $paid / 100;
        $invoice->balance = $balance / 100;
        $invoice->status = $balance === 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
    }

    public function nextInvoiceNumber(): string
    {
        return 'INV-'.now()->format('Y').'-'.Str::upper(Str::random(16));
    }
}
