<?php

namespace App\Http\Requests\School;

use App\Rules\UniqueLoginIdentifier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSchoolRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'system_admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash', 'unique:schools,code'],
            'admin.full_name' => ['required', 'string', 'max:150'],
            'admin.username' => ['required', 'string', 'max:100', 'alpha_dash', new UniqueLoginIdentifier],
            'admin.email' => ['nullable', 'email', 'max:150', new UniqueLoginIdentifier],
            'admin.phone' => ['nullable', 'string', 'max:30', new UniqueLoginIdentifier],
            'admin.password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
