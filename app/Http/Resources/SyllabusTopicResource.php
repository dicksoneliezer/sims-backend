<?php

namespace App\Http\Resources;

use App\Models\SyllabusTopic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SyllabusTopic
 */
class SyllabusTopicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject_id' => $this->subject_id,
            'subject' => $this->whenLoaded('subject', fn () => $this->subject ? [
                'id' => $this->subject->id,
                'code' => $this->subject->code,
                'name' => $this->subject->name,
            ] : null),
            'class_id' => $this->class_id,
            'class_name' => $this->whenLoaded('schoolClass', fn () => $this->schoolClass?->name),
            'title' => $this->title,
            'description' => $this->description,
            'term' => $this->term,
            'week' => $this->week,
            'sort_order' => $this->sort_order,
            'progress_count' => $this->whenCounted('progresses'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
