<?php

namespace App\Http\Requests\SchoolClass;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSchoolClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'unique:school_classes,name'],
            'level' => ['required', Rule::in(['pre_primary', 'primary', 'secondary'])],
            'section' => ['nullable', 'string', 'max:30'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:500'],
            'class_teacher_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'teacher')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
