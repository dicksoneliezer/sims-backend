<?php

namespace App\Http\Requests\SchoolClass;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSchoolClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('school_classes', 'name')->ignore($this->route('schoolClass')->id)],
            'level' => ['sometimes', Rule::in(['pre_primary', 'primary', 'secondary'])],
            'section' => ['sometimes', 'nullable', 'string', 'max:30'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:500'],
            'class_teacher_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')->where('role', 'teacher')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
