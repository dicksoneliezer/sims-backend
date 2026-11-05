<?php

namespace App\Http\Requests\User;

use App\Models\User;
use App\Rules\UniqueLoginIdentifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:users,username', new UniqueLoginIdentifier],
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email', new UniqueLoginIdentifier],
            'phone' => ['nullable', 'string', 'max:30', new UniqueLoginIdentifier],
            'nin_number' => ['nullable', 'string', 'max:50'],
            'employment_id' => ['nullable', 'string', 'max:50'],
            'role' => ['required', Rule::in(array_values(array_diff(User::ROLES, ['super_admin', 'system_admin'])))],
            'password' => ['required', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
