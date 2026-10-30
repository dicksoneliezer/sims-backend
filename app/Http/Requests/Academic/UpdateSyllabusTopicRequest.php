<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSyllabusTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['sometimes', 'required', 'integer', 'exists:subjects,id'],
            'class_id' => ['sometimes', 'required', 'integer', 'exists:school_classes,id'],
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:3000'],
            'term' => ['sometimes', 'required', 'string', 'max:20'],
            'week' => ['nullable', 'integer', 'min:1', 'max:52'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
