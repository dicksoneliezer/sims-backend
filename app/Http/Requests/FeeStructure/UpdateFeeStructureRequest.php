<?php

namespace App\Http\Requests\FeeStructure;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeeStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'class_id' => ['sometimes', 'nullable', 'integer', 'exists:school_classes,id'],
            'school_level' => ['sometimes', 'nullable', Rule::in(['pre_primary', 'primary', 'secondary'])],
            'term' => ['sometimes', 'nullable', 'string', 'max:20'],
            'academic_year' => ['sometimes', 'nullable', 'string', 'max:9'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
