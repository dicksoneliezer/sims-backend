<?php

namespace App\Http\Requests\User;

use App\Models\User;
use App\Rules\UniqueLoginIdentifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'username' => ['sometimes', 'string', 'max:100', 'alpha_dash', Rule::unique('users', 'username')->ignore($userId), new UniqueLoginIdentifier($this->route('user')?->id)],
            'full_name' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($userId), new UniqueLoginIdentifier($this->route('user')?->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', new UniqueLoginIdentifier($this->route('user')?->id)],
            'nin_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'employment_id' => ['sometimes', 'nullable', 'string', 'max:50'],
            'role' => ['sometimes', Rule::in($this->route('user')->role === 'super_admin' ? ['super_admin'] : array_values(array_diff(User::ROLES, ['super_admin', 'system_admin'])))],
            'is_active' => ['sometimes', 'boolean'],
            'password' => ['sometimes', 'string', 'min:8'],
        ];
    }
}
