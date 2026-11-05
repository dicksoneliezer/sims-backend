<?php

namespace App\Http\Requests\Teacher;

use App\Rules\UniqueLoginIdentifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $teacherId = $this->route('teacher')->id;

        return [
            'username' => ['sometimes', 'string', 'max:100', 'alpha_dash', Rule::unique('users', 'username')->ignore($teacherId), new UniqueLoginIdentifier($teacherId)],
            'full_name' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($teacherId), new UniqueLoginIdentifier($teacherId)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', new UniqueLoginIdentifier($teacherId)],
            'nin_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'employment_id' => ['sometimes', 'nullable', 'string', 'max:50'],
            'password' => ['sometimes', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
