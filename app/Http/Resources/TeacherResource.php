<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin User
 */
class TeacherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'nin_number' => $this->nin_number,
            'employment_id' => $this->employment_id,
            'role' => $this->role,
            'is_active' => $this->is_active,
            'last_login' => $this->last_login?->toIso8601String(),
            'profile_pic_url' => $this->profile_pic ? Storage::disk('public')->url($this->profile_pic) : null,
            'classes_count' => $this->whenCounted('teacherClasses'),
            'subjects_count' => $this->whenCounted('teacherSubjects'),
            'classes' => $this->whenLoaded('teacherClasses', fn () => $this->teacherClasses->map(fn ($assignment) => [
                'id' => $assignment->id,
                'class_id' => $assignment->class_id,
                'class_name' => $assignment->schoolClass?->name,
                'level' => $assignment->schoolClass?->level,
                'section' => $assignment->schoolClass?->section,
                'academic_year' => $assignment->academic_year,
            ])),
            'subjects' => $this->whenLoaded('teacherSubjects', fn () => $this->teacherSubjects->map(fn ($assignment) => [
                'id' => $assignment->id,
                'subject_id' => $assignment->subject_id,
                'subject_code' => $assignment->subject?->code,
                'subject_name' => $assignment->subject?->name,
                'class_id' => $assignment->class_id,
                'class_name' => $assignment->schoolClass?->name,
                'academic_year' => $assignment->academic_year,
            ])),
        ];
    }
}
