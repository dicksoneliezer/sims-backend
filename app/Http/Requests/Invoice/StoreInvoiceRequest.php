<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'fee_structure_id' => ['nullable', 'integer', 'exists:fee_structure,id'],
            'invoice_number' => ['nullable', 'string', 'max:50', 'unique:invoices,invoice_number'],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['nullable', 'date'],
        ];

        if ($this->filled('fee_structure_id')) {
            $rules['fee_structure_id'][] = Rule::unique('invoices')->where(
                fn ($query) => $query->where('student_id', $this->input('student_id')),
            );
        }

        return $rules;
    }
}
