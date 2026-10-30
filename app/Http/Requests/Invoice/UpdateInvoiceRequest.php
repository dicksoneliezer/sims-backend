<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'description' => ['sometimes', 'nullable', 'string'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'fee_structure_id' => ['sometimes', 'nullable', 'integer', 'exists:fee_structure,id'],
        ];

        if ($this->filled('fee_structure_id')) {
            $invoice = $this->route('invoice');

            $rules['fee_structure_id'][] = Rule::unique('invoices')
                ->where(fn ($query) => $query->where('student_id', $invoice->student_id))
                ->ignore($invoice->id);
        }

        return $rules;
    }
}
