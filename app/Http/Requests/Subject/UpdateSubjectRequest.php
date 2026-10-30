<?php

namespace App\Http\Requests\Subject;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'string', 'max:30', Rule::unique('subjects', 'code')->ignore($this->route('subject')->id)],
            'name' => ['sometimes', 'string', 'max:150'],
            'level' => ['sometimes', 'nullable', Rule::in(['pre_primary', 'primary', 'secondary'])],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
