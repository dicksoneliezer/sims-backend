<?php

namespace App\Http\Resources;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'expense_number' => $this->expense_number,
            'category' => $this->category,
            'description' => $this->description,
            'amount' => $this->amount,
            'expense_date' => $this->expense_date?->toDateString(),
            'paid_to' => $this->paid_to,
            'payment_method' => $this->payment_method,
            'receipt' => $this->receipt,
            'receipt_url' => $this->receipt ? Storage::disk('public')->url($this->receipt) : null,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
