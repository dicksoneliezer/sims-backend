<?php

namespace App\Http\Requests\Timetable;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTimetableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'class_id' => ['sometimes', 'integer', 'exists:school_classes,id'],
            'subject_id' => ['sometimes', 'integer', 'exists:subjects,id'],
            'teacher_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')->where('role', 'teacher')],
            'day_of_week' => ['sometimes', Rule::in(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i'],
            'room' => ['sometimes', 'nullable', 'string', 'max:50'],
            'term' => ['sometimes', 'nullable', 'string', 'max:20'],
            'academic_year' => ['sometimes', 'nullable', 'string', 'max:9'],
        ];
    }
}
