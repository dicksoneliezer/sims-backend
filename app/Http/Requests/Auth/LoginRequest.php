<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 'username' ni field ya msingi (inaweza kubeba username, email, au phone)
            'username' => ['required_without:login', 'nullable', 'string', 'max:150'],
            // 'login' ni field ya hiari (kwa frontend mpya inayotumia 'login')
            'login' => ['required_without:username', 'nullable', 'string', 'max:150'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required_without' => 'The username, email, or phone field is required.',
            'login.required_without' => 'The username, email, or phone field is required.',
        ];
    }
}