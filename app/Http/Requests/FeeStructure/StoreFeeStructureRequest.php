<?php

namespace App\Http\Requests\FeeStructure;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeeStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'school_level' => ['nullable', Rule::in(['pre_primary', 'primary', 'secondary'])],
            'term' => ['nullable', 'string', 'max:20'],
            'academic_year' => ['nullable', 'string', 'max:9'],
            'amount' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
