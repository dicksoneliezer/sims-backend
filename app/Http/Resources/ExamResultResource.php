<?php

namespace App\Http\Resources;

use App\Models\ExamResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExamResult
 */
class ExamResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exam_id' => $this->exam_id,
            'exam' => $this->whenLoaded('exam', fn () => $this->exam ? [
                'id' => $this->exam->id,
                'name' => $this->exam->name,
                'type' => $this->exam->type,
            ] : null),
            'student_id' => $this->student_id,
            'student' => $this->whenLoaded('student', fn () => $this->student ? [
                'id' => $this->student->id,
                'full_name' => $this->student->full_name,
                'student_number' => $this->student->student_number,
                'class' => $this->student->schoolClass?->name ?: $this->student->class,
                'class_id' => $this->student->class_id,
            ] : null),
            'subject_id' => $this->subject_id,
            'subject' => $this->whenLoaded('subject', fn () => $this->subject ? [
                'id' => $this->subject->id,
                'code' => $this->subject->code,
                'name' => $this->subject->name,
            ] : null),
            'exam_name' => $this->exam_name,
            'exam_type' => $this->exam_type,
            'term' => $this->term,
            'academic_year' => $this->academic_year,
            'marks' => (float) $this->marks,
            'grade' => $this->grade,
            'points' => $this->points,
            'remarks' => $this->remarks,
            'entered_by' => $this->entered_by,
            'entered_by_name' => $this->whenLoaded('enteredBy', fn () => $this->enteredBy?->full_name),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
