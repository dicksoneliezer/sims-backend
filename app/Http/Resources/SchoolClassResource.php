<?php

namespace App\Http\Resources;

use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SchoolClass
 */
class SchoolClassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'level' => $this->level,
            'section' => $this->section,
            'capacity' => $this->capacity,
            'class_teacher_id' => $this->class_teacher_id,
            'class_teacher' => $this->whenLoaded('classTeacher', fn () => $this->classTeacher ? [
                'id' => $this->classTeacher->id,
                'full_name' => $this->classTeacher->full_name,
                'email' => $this->classTeacher->email,
            ] : null),
            'students_count' => $this->whenCounted('students'),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
