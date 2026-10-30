<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'class_id' => ['nullable', 'required_without:school_level', 'integer', 'exists:school_classes,id'],
            'school_level' => ['nullable', 'required_without:class_id', Rule::in(['pre_primary', 'primary', 'secondary'])],
            'term' => ['nullable', 'string', 'max:20'],
            'academic_year' => ['nullable', 'string', 'max:9'],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
