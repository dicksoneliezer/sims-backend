<?php

namespace App\Http\Requests\Grading;

use Illuminate\Foundation\Http\FormRequest;

class StoreGradingSystemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grade' => ['required', 'string', 'max:10', 'unique:grading_system,grade'],
            'min_marks' => ['required', 'numeric', 'min:0', 'max:100'],
            'max_marks' => ['required', 'numeric', 'min:0', 'max:100', 'gte:min_marks'],
            'points' => ['nullable', 'integer', 'min:0', 'max:20'],
            'remarks' => ['nullable', 'string', 'max:100'],
        ];
    }
}
