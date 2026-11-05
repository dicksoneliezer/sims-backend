<?php

namespace App\Http\Requests\Invoice;

use App\Models\FeeStructure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $fee = $this->filled('fee_structure_id') ? FeeStructure::find($this->input('fee_structure_id')) : null;
        $period = $this->input('period') ?: trim(($fee?->term ?? '').' '.($fee?->academic_year ?? ''));
        $this->merge(['period' => preg_replace('/\s+/', ' ', trim((string) $period)) ?: 'Legacy']);
    }

    public function rules(): array
    {
        $rules = [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'fee_structure_id' => ['nullable', 'integer', 'exists:fee_structure,id'],
            'invoice_number' => ['nullable', 'string', 'max:50', 'unique:invoices,invoice_number'],
            'period' => ['sometimes', 'string', 'max:80'],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999999.99'],
            'due_date' => ['nullable', 'date'],
        ];

        if ($this->filled('fee_structure_id')) {
            $rules['fee_structure_id'][] = Rule::unique('invoices')->where(
                fn ($query) => $query->where('student_id', $this->input('student_id'))->where('period', $this->input('period', 'Legacy')),
            );
        }

        return $rules;
    }
}
