<?php

namespace App\Http\Resources;

use App\Models\SyllabusProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SyllabusProgress
 */
class SyllabusProgressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'syllabus_topic_id' => $this->syllabus_topic_id,
            'topic' => $this->whenLoaded('topic', fn () => $this->topic ? [
                'id' => $this->topic->id,
                'title' => $this->topic->title,
                'term' => $this->topic->term,
                'week' => $this->topic->week,
                'subject' => $this->topic->relationLoaded('subject') ? $this->topic->subject?->name : null,
                'subject_id' => $this->topic->subject_id,
                'class_id' => $this->topic->class_id,
            ] : null),
            'teacher_id' => $this->teacher_id,
            'teacher_name' => $this->whenLoaded('teacher', fn () => $this->teacher?->full_name),
            'status' => $this->status,
            'completion_date' => $this->completion_date?->toDateString(),
            'remarks' => $this->remarks,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
