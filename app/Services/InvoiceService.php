<?php

namespace App\Services;

use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function createInvoice(array $data, User $issuer): Invoice
    {
        $amount = round((float) $data['amount'], 2);

        return Invoice::create([
            'invoice_number' => $data['invoice_number'] ?? $this->nextInvoiceNumber(),
            'student_id' => $data['student_id'],
            'fee_structure_id' => $data['fee_structure_id'] ?? null,
            'description' => $data['description'] ?? null,
            'amount' => $amount,
            'amount_paid' => 0,
            'balance' => $amount,
            'status' => 'unpaid',
            'due_date' => $data['due_date'] ?? null,
            'issued_by' => $issuer->id,
        ]);
    }

    public function updateInvoice(Invoice $invoice, array $data): Invoice
    {
        if (array_key_exists('amount', $data)) {
            $amount = round((float) $data['amount'], 2);

            if ($amount < (float) $invoice->amount_paid) {
                throw ValidationException::withMessages([
                    'amount' => 'The invoice amount cannot be less than the amount already paid.',
                ]);
            }

            $invoice->amount = $amount;
        }

        foreach (['description', 'due_date', 'fee_structure_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $invoice->{$field} = $data[$field];
            }
        }

        $this->recalculate($invoice);
        $invoice->save();

        return $invoice->fresh()->load(['student:id,full_name,student_number,class', 'feeStructure']);
    }

    public function bulkGenerate(array $filters, User $issuer): array
    {
        $feeStructures = FeeStructure::query()
            ->where('is_active', true)
            ->when(! empty($filters['class_id']), fn ($query) => $query->where('class_id', $filters['class_id']))
            ->when(! empty($filters['school_level']), fn ($query) => $query->where('school_level', $filters['school_level']))
            ->when(! empty($filters['term']), fn ($query) => $query->where('term', $filters['term']))
            ->when(! empty($filters['academic_year']), fn ($query) => $query->where('academic_year', $filters['academic_year']))
            ->orderBy('name')
            ->get();

        if ($feeStructures->isEmpty()) {
            throw ValidationException::withMessages([
                'fee_structure' => 'No active fee structures match the selected filters.',
            ]);
        }

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($feeStructures, $filters, $issuer, &$created, &$skipped) {
            foreach ($feeStructures as $feeStructure) {
                if (! $feeStructure->class_id && ! $feeStructure->school_level) {
                    continue;
                }

                $studentQuery = Student::query()->where('is_active', true);

                if ($feeStructure->class_id) {
                    $studentQuery->where('class_id', $feeStructure->class_id);
                } else {
                    $studentQuery->where('school_level', $feeStructure->school_level);
                }

                $studentQuery->chunkById(200, function ($students) use ($feeStructure, $filters, $issuer, &$created, &$skipped) {
                    foreach ($students as $student) {
                        $exists = Invoice::where('student_id', $student->id)
                            ->where('fee_structure_id', $feeStructure->id)
                            ->exists();

                        if ($exists) {
                            $skipped++;

                            continue;
                        }

                        $this->createInvoice([
                            'student_id' => $student->id,
                            'fee_structure_id' => $feeStructure->id,
                            'description' => trim($feeStructure->name.' '.($feeStructure->term ?? '').' '.($feeStructure->academic_year ?? '')),
                            'amount' => $feeStructure->amount,
                            'due_date' => $filters['due_date'] ?? null,
                        ], $issuer);

                        $created++;
                    }
                });
            }
        });

        return [
            'created' => $created,
            'skipped' => $skipped,
            'fee_structures' => $feeStructures->count(),
        ];
    }

    public function recalculate(Invoice $invoice): void
    {
        $amount = round((float) $invoice->amount, 2);
        $amountPaid = round((float) $invoice->amount_paid, 2);
        $balance = max(round($amount - $amountPaid, 2), 0);

        $invoice->amount = $amount;
        $invoice->amount_paid = $amountPaid;
        $invoice->balance = $balance;
        $invoice->status = $balance <= 0 ? 'paid' : ($amountPaid > 0 ? 'partial' : 'unpaid');
    }

    public function nextInvoiceNumber(): string
    {
        $prefix = 'INV-'.now()->format('Y').'-';
        $sequence = Invoice::where('invoice_number', 'like', $prefix.'%')->count() + 1;

        do {
            $number = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (Invoice::where('invoice_number', $number)->exists());

        return $number;
    }
}
