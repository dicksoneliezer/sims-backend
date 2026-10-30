<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['sometimes', 'nullable', 'integer', 'exists:students,id'],
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'note' => ['sometimes', 'required', 'string', 'max:5000'],
            'type' => ['sometimes', 'required', Rule::in(['general', 'academic', 'behavior', 'attendance', 'performance'])],
        ];
    }
}
