<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'expense_date' => ['sometimes', 'date'],
            'paid_to' => ['sometimes', 'nullable', 'string', 'max:150'],
            'payment_method' => ['sometimes', 'nullable', 'string', 'max:50'],
            'receipt' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
            'remove_receipt' => ['sometimes', 'boolean'],
        ];
    }
}
