<?php

namespace App\Http\Resources;

use App\Models\BehaviorRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BehaviorRecord
 */
class BehaviorRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'student' => $this->whenLoaded('student', fn () => $this->student ? [
                'id' => $this->student->id,
                'full_name' => $this->student->full_name,
                'student_number' => $this->student->student_number,
                'class' => $this->student->class,
            ] : null),
            'teacher_id' => $this->teacher_id,
            'teacher_name' => $this->whenLoaded('teacher', fn () => $this->teacher?->full_name),
            'date' => $this->date?->toDateString(),
            'category' => $this->category,
            'description' => $this->description,
            'severity' => $this->severity,
            'action_taken' => $this->action_taken,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
