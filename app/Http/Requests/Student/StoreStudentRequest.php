<?php

namespace App\Http\Requests\Student;

use App\Rules\UniqueLoginIdentifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'unique:students,user_id'],
            'student_number' => ['nullable', 'string', 'max:50', 'unique:students,student_number'],
            'full_name' => ['required', 'string', 'max:150'],
            'class' => ['nullable', 'string', 'max:50'],
            'class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'school_level' => ['nullable', Rule::in(['pre_primary', 'primary', 'secondary'])],
            'prem_number' => ['nullable', 'string', 'max:50'],
            'psle_number' => ['nullable', 'string', 'max:50'],
            'index_number' => ['nullable', 'string', 'max:50'],
            'parent_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150', new UniqueLoginIdentifier],
            'residence' => ['nullable', 'string', 'max:150'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'as_of_date' => ['nullable', 'date'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
