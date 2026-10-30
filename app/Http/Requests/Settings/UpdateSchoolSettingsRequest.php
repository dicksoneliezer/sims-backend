<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'school_motto' => ['sometimes', 'nullable', 'string', 'max:150'],
            'school_address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'school_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'school_email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'school_website' => ['sometimes', 'nullable', 'string', 'max:150'],
            'head_teacher_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'academic_year' => ['sometimes', 'nullable', 'string', 'max:20'],
            'current_term' => ['sometimes', 'nullable', 'string', 'max:30'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:10'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
