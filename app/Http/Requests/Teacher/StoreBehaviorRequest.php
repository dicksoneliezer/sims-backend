<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBehaviorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:2000'],
            'severity' => ['required', Rule::in(['low', 'medium', 'high'])],
            'action_taken' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
