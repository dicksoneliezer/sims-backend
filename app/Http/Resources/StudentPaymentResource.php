<?php

namespace App\Http\Resources;

use App\Models\StudentPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StudentPayment
 */
class StudentPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'control_number' => $this->control_number,
            'student_id' => $this->student_id,
            'invoice_id' => $this->invoice_id,
            'student' => $this->whenLoaded('student', fn () => $this->student ? [
                'id' => $this->student->id,
                'student_number' => $this->student->student_number,
                'full_name' => $this->student->full_name,
                'class' => $this->student->class,
            ] : null),
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice ? [
                'id' => $this->invoice->id,
                'invoice_number' => $this->invoice->invoice_number,
                'amount' => $this->invoice->amount,
                'balance' => $this->invoice->balance,
                'status' => $this->invoice->status,
            ] : null),
            'amount' => $this->amount,
            'description' => $this->description,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'transaction_ref' => $this->transaction_ref,
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_expired' => $this->isExpired(),
        ];
    }
}
