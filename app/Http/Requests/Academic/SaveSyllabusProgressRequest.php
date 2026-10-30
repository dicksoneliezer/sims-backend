<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSyllabusProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'syllabus_topic_id' => ['required', 'integer', 'exists:syllabus_topics,id'],
            'status' => ['required', Rule::in(['not_started', 'in_progress', 'completed'])],
            'completion_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
