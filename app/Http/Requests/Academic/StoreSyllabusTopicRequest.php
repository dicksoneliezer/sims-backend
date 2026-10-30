<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class StoreSyllabusTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'class_id' => ['required', 'integer', 'exists:school_classes,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:3000'],
            'term' => ['required', 'string', 'max:20'],
            'week' => ['nullable', 'integer', 'min:1', 'max:52'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
