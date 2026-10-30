<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'title' => ['required', 'string', 'max:150'],
            'note' => ['required', 'string', 'max:5000'],
            'type' => ['required', Rule::in(['general', 'academic', 'behavior', 'attendance', 'performance'])],
        ];
    }
}
