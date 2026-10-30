<?php

namespace App\Http\Requests\Accountant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $accountant = $this->route('accountant');

        return [
            'username' => ['sometimes', 'string', 'max:100', 'alpha_dash', Rule::unique('users', 'username')->ignore($accountant->id)],
            'full_name' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($accountant->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'nin_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'employment_id' => ['sometimes', 'nullable', 'string', 'max:50'],
            'password' => ['sometimes', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
