<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'student_id' => $this->student_id,
            'student' => $this->whenLoaded('student', fn () => $this->student ? [
                'id' => $this->student->id,
                'student_number' => $this->student->student_number,
                'full_name' => $this->student->full_name,
                'class' => $this->student->class,
            ] : null),
            'fee_structure_id' => $this->fee_structure_id,
            'fee_structure' => $this->whenLoaded('feeStructure', fn () => $this->feeStructure ? [
                'id' => $this->feeStructure->id,
                'name' => $this->feeStructure->name,
                'term' => $this->feeStructure->term,
                'academic_year' => $this->feeStructure->academic_year,
            ] : null),
            'description' => $this->description,
            'period' => $this->period,
            'payment_ids' => $this->whenLoaded('transactions', fn () => $this->transactions->whereIn('type', ['payment', 'bill_allocation'])->pluck('reference')->filter()->unique()->values()),
            'amount' => $this->amount,
            'amount_paid' => $this->amount_paid,
            'balance' => $this->balance,
            'status' => $this->status,
            'due_date' => $this->due_date?->toDateString(),
            'is_overdue' => $this->isOverdue(),
            'days_overdue' => $this->daysOverdue(),
            'issued_by' => $this->issued_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
