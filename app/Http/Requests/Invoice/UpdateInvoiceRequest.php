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

    protected function prepareForValidation(): void
    {
        if ($this->filled('period')) {
            $this->merge(['period' => preg_replace('/\s+/', ' ', trim($this->input('period')))]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'period' => ['sometimes', 'string', 'max:80'],
            'description' => ['sometimes', 'nullable', 'string'],
            'amount' => ['sometimes', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999999.99'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'fee_structure_id' => ['sometimes', 'nullable', 'integer', 'exists:fee_structure,id'],
        ];

        if ($this->filled('fee_structure_id')) {
            $invoice = $this->route('invoice');

            $rules['fee_structure_id'][] = Rule::unique('invoices')
                ->where(fn ($query) => $query->where('student_id', $invoice->student_id)->where('period', $this->input('period', $invoice->period)))
                ->ignore($invoice->id);
        }

        return $rules;
    }
}
