<?php

namespace App\Http\Resources;

use App\Models\Timetable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Timetable
 */
class TimetableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'class_id' => $this->class_id,
            'class_name' => $this->whenLoaded('schoolClass', fn () => $this->schoolClass->name),
            'subject_id' => $this->subject_id,
            'subject' => $this->whenLoaded('subject', fn () => [
                'id' => $this->subject->id,
                'code' => $this->subject->code,
                'name' => $this->subject->name,
            ]),
            'teacher_id' => $this->teacher_id,
            'teacher' => $this->whenLoaded('teacher', fn () => $this->teacher ? [
                'id' => $this->teacher->id,
                'full_name' => $this->teacher->full_name,
            ] : null),
            'day_of_week' => $this->day_of_week,
            'start_time' => $this->start_time ? substr($this->start_time, 0, 5) : null,
            'end_time' => $this->end_time ? substr($this->end_time, 0, 5) : null,
            'room' => $this->room,
            'term' => $this->term,
            'academic_year' => $this->academic_year,
        ];
    }
}
