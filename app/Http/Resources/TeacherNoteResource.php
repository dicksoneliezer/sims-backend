<?php

namespace App\Http\Resources;

use App\Models\TeacherNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TeacherNote
 */
class TeacherNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'teacher_id' => $this->teacher_id,
            'teacher_name' => $this->whenLoaded('teacher', fn () => $this->teacher?->full_name),
            'student_id' => $this->student_id,
            'student' => $this->whenLoaded('student', fn () => $this->student ? [
                'id' => $this->student->id,
                'full_name' => $this->student->full_name,
                'student_number' => $this->student->student_number,
                'class' => $this->student->class,
            ] : null),
            'title' => $this->title,
            'note' => $this->note,
            'type' => $this->type,
            'is_read' => $this->is_read,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
