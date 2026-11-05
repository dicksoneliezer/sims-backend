<?php

namespace App\Http\Resources;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Student
 */
class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'username' => $this->whenLoaded('user', fn () => $this->user?->username),
            'student_number' => $this->student_number,
            'full_name' => $this->full_name,
            'class' => $this->class,
            'class_id' => $this->class_id,
            'school_class' => $this->whenLoaded('schoolClass', fn () => $this->schoolClass ? [
                'id' => $this->schoolClass->id,
                'name' => $this->schoolClass->name,
                'level' => $this->schoolClass->level,
                'section' => $this->schoolClass->section,
            ] : null),
            'school_level' => $this->school_level,
            'prem_number' => $this->prem_number,
            'psle_number' => $this->psle_number,
            'index_number' => $this->index_number,
            'parent_phone' => $this->parent_phone,
            'email' => $this->email,
            'residence' => $this->residence,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'opening_balance' => $this->opening_balance,
            'as_of_date' => $this->as_of_date?->toDateString(),
            'photo' => $this->photo,
            'photo_url' => $this->photo ? route('school-files.student', ['student' => $this->id, 'v' => substr(hash('sha256', $this->photo), 0, 16)]) : null,
            'is_active' => $this->is_active,
            'parents' => $this->whenLoaded('parents', fn () => $this->parents->map(fn ($parent) => [
                'id' => $parent->id,
                'full_name' => $parent->full_name,
                'phone' => $parent->phone,
                'email' => $parent->email,
                'relationship' => $parent->pivot->relationship,
                'is_primary' => (bool) $parent->pivot->is_primary,
            ])),
        ];
    }
}
