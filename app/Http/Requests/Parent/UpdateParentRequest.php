<?php

namespace App\Http\Requests\Parent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateParentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $parent = $this->route('parent');

        return [
            'username' => ['sometimes', 'string', 'max:100', 'alpha_dash', Rule::unique('users', 'username')->ignore($parent->id)],
            'full_name' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($parent->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'password' => ['sometimes', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
            'children' => ['sometimes', 'array'],
            'children.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'children.*.relationship' => ['nullable', 'string', 'max:30'],
            'children.*.is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
