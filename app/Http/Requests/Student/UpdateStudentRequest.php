<?php

namespace App\Http\Requests\Student;

use App\Rules\UniqueLoginIdentifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $studentId = $this->route('student')->id;

        return [
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id', Rule::unique('students', 'user_id')->ignore($studentId)],
            'student_number' => ['sometimes', 'string', 'max:50', Rule::unique('students', 'student_number')->ignore($studentId)],
            'full_name' => ['sometimes', 'string', 'max:150'],
            'class' => ['sometimes', 'nullable', 'string', 'max:50'],
            'class_id' => ['sometimes', 'nullable', 'integer', 'exists:school_classes,id'],
            'school_level' => ['sometimes', 'nullable', Rule::in(['pre_primary', 'primary', 'secondary'])],
            'prem_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'psle_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'index_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'parent_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150', new UniqueLoginIdentifier($this->route('student')->user_id)],
            'residence' => ['sometimes', 'nullable', 'string', 'max:150'],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female'])],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'opening_balance' => ['sometimes', 'numeric', 'min:0'],
            'as_of_date' => ['sometimes', 'nullable', 'date'],
            'photo' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
