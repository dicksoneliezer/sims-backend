<?php

namespace App\Http\Requests\Parent;

use Illuminate\Foundation\Http\FormRequest;

class StoreParentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:users,username'],
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
            'children' => ['nullable', 'array'],
            'children.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'children.*.relationship' => ['nullable', 'string', 'max:30'],
            'children.*.is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
