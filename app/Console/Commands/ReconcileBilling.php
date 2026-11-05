<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\School;
use App\Models\Student;
use App\Models\Transaction;
use App\Services\BillingService;
use App\Services\SchoolContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:reconcile {--school=} {--check : Verify balances without allocating credit}')]
#[Description('Apply existing credit to outstanding bills and verify billing arithmetic by school')]
class ReconcileBilling extends Command
{
    public function handle(BillingService $billing, SchoolContext $context): int
    {
        $errors = 0;
        $checked = 0;
        foreach (School::query()->when($this->option('school'), fn ($q) => $q->whereKey($this->option('school')))->orderBy('id')->cursor() as $school) {
            $context->run($school->id, function () use ($billing, &$errors, &$checked): void {
                Student::orderBy('id')->chunkById(100, function ($students) use ($billing, &$errors, &$checked): void {
                    foreach ($students as $student) {
                        if (! $this->option('check')) {
                            $billing->applyCredit($student);
                        }
                        $summary = $billing->summary($student);
                        if (BillingService::cents($summary['total_outstanding']) !== BillingService::cents($student->invoices()->sum('balance'))) {
                            $errors++;
                        }
                        $checked++;
                    }
                });
                foreach (Invoice::cursor() as $invoice) {
                    $paid = BillingService::cents($invoice->amount_paid);
                    $balance = BillingService::cents($invoice->balance);
                    if ($paid < 0 || $balance < 0 || BillingService::cents($invoice->amount) !== $paid + $balance || $invoice->status !== ($balance === 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'))) {
                        $errors++;
                    }
                }
                foreach (Transaction::whereIn('type', ['credit_transfer_in', 'credit_transfer_out'])->selectRaw('reference, COUNT(*) as entries, SUM(CASE WHEN type = \'credit_transfer_in\' THEN amount ELSE -amount END) as net')->groupBy('reference')->get() as $transfer) {
                    if ((int) $transfer->entries !== 2 || BillingService::cents($transfer->net) !== 0) {
                        $errors++;
                    }
                }
            });
        }
        $this->info('Student balances checked: '.$checked.'; billing errors: '.$errors);

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
