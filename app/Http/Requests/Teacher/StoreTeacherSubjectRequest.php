<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'teacher_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'teacher')],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'academic_year' => ['nullable', 'string', 'max:9'],
        ];
    }
}
