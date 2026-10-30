<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBehaviorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'category' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['sometimes', 'required', 'string', 'max:2000'],
            'severity' => ['sometimes', 'required', Rule::in(['low', 'medium', 'high'])],
            'action_taken' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
