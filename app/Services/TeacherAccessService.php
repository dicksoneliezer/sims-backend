<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeacherClass;
use App\Models\TeacherSubject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TeacherAccessService
{
    public function classIds(User $teacher): Collection
    {
        return TeacherClass::where('teacher_id', $teacher->id)->pluck('class_id')
            ->merge(TeacherSubject::where('teacher_id', $teacher->id)->whereNotNull('class_id')->pluck('class_id'))
            ->merge(SchoolClass::where('class_teacher_id', $teacher->id)->pluck('id'))
            ->filter()
            ->unique()
            ->values();
    }

    public function classes(User $teacher): Collection
    {
        return SchoolClass::whereIn('id', $this->classIds($teacher))
            ->orderBy('name')
            ->get(['id', 'name', 'level', 'section']);
    }

    public function students(User $teacher): Builder
    {
        $classIds = $this->classIds($teacher);
        $classNames = SchoolClass::whereIn('id', $classIds)->pluck('name');

        return Student::query()
            ->where(function (Builder $query) use ($classIds, $classNames) {
                $query->whereIn('class_id', $classIds);

                if ($classNames->isNotEmpty()) {
                    $query->orWhere(function (Builder $query) use ($classNames) {
                        $query->whereNull('class_id')->whereIn('class', $classNames);
                    });
                }
            });
    }

    public function subjectAssignments(User $teacher): Collection
    {
        return TeacherSubject::with(['subject:id,code,name', 'schoolClass:id,name'])
            ->where('teacher_id', $teacher->id)
            ->get();
    }

    public function ensureClass(User $teacher, int $classId): void
    {
        abort_unless($this->classIds($teacher)->contains($classId), 403, 'You are not assigned to this class.');
    }

    public function ensureStudent(User $teacher, Student $student): void
    {
        abort_unless($this->students($teacher)->whereKey($student->id)->exists(), 403, 'You can only access students in your assigned classes.');
    }

    public function ensureSubjectClass(User $teacher, int $subjectId, int $classId): void
    {
        $this->ensureClass($teacher, $classId);

        $assigned = TeacherSubject::where('teacher_id', $teacher->id)
            ->where('subject_id', $subjectId)
            ->where(function (Builder $query) use ($classId) {
                $query->where('class_id', $classId)->orWhereNull('class_id');
            })
            ->exists();

        abort_unless($assigned, 403, 'You are not assigned to teach this subject in this class.');
    }
}
