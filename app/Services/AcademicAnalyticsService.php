<?php

namespace App\Services;

use App\Models\ExamResult;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AcademicAnalyticsService
{
    public function analysis(array $filters): array
    {
        $results = $this->resultQuery($filters)->get();

        $average = fn (Collection $items) => $items->isEmpty() ? null : round((float) $items->avg('marks'), 2);

        $subjectPerformance = $results
            ->groupBy('subject_id')
            ->map(function (Collection $items) use ($average) {
                $subject = $items->first()->subject;

                return [
                    'subject_id' => $subject?->id,
                    'code' => $subject?->code,
                    'name' => $subject?->name,
                    'average' => $average($items),
                    'highest' => round((float) $items->max('marks'), 2),
                    'lowest' => round((float) $items->min('marks'), 2),
                    'results_count' => $items->count(),
                ];
            })
            ->sortByDesc('average')
            ->values();

        $classPerformance = $results
            ->groupBy(fn (ExamResult $result) => $result->student?->class_id ?: $result->student?->class ?: 'Unassigned')
            ->map(function (Collection $items, $key) use ($average) {
                $student = $items->first()->student;

                return [
                    'class_id' => $student?->class_id,
                    'name' => $student?->schoolClass?->name ?: $student?->class ?: (string) $key,
                    'average' => $average($items),
                    'results_count' => $items->count(),
                    'students_count' => $items->pluck('student_id')->unique()->count(),
                ];
            })
            ->sortByDesc('average')
            ->values();

        $topStudents = $results
            ->groupBy('student_id')
            ->map(function (Collection $items) use ($average) {
                $student = $items->first()->student;

                return [
                    'student_id' => $student?->id,
                    'full_name' => $student?->full_name,
                    'student_number' => $student?->student_number,
                    'class' => $student?->schoolClass?->name ?: $student?->class,
                    'average' => $average($items),
                    'results_count' => $items->count(),
                ];
            })
            ->sortByDesc('average')
            ->take(10)
            ->values();

        $gradeDistribution = $results
            ->groupBy(fn (ExamResult $result) => $result->grade ?: 'Ungraded')
            ->map(fn (Collection $items, $grade) => [
                'grade' => (string) $grade,
                'count' => $items->count(),
            ])
            ->values()
            ->sortBy('grade')
            ->values();

        return [
            'summary' => [
                'results_count' => $results->count(),
                'students_count' => $results->pluck('student_id')->unique()->count(),
                'subjects_count' => $results->pluck('subject_id')->unique()->count(),
                'average' => $average($results),
                'highest' => $results->isEmpty() ? null : round((float) $results->max('marks'), 2),
                'lowest' => $results->isEmpty() ? null : round((float) $results->min('marks'), 2),
                'pass_rate' => $results->isEmpty()
                    ? null
                    : round($results->where('marks', '>=', 50)->count() / $results->count() * 100, 2),
            ],
            'grade_distribution' => $gradeDistribution,
            'subject_performance' => $subjectPerformance,
            'class_performance' => $classPerformance,
            'top_students' => $topStudents,
        ];
    }

    public function studentProgress(Student $student): array
    {
        $results = $student->examResults()
            ->with('subject:id,code,name')
            ->orderByDesc('academic_year')
            ->orderBy('term')
            ->get();

        $byTerm = $results
            ->groupBy(fn (ExamResult $result) => ($result->academic_year ?: 'Unknown').'|'.($result->term ?: 'Unknown'))
            ->map(function (Collection $items) {
                $first = $items->first();

                return [
                    'academic_year' => $first->academic_year,
                    'term' => $first->term,
                    'average' => round((float) $items->avg('marks'), 2),
                    'highest' => round((float) $items->max('marks'), 2),
                    'lowest' => round((float) $items->min('marks'), 2),
                    'results_count' => $items->count(),
                ];
            })
            ->values();

        $subjects = $results
            ->groupBy('subject_id')
            ->map(function (Collection $items) {
                $subject = $items->first()->subject;

                return [
                    'subject_id' => $subject?->id,
                    'code' => $subject?->code,
                    'name' => $subject?->name,
                    'average' => round((float) $items->avg('marks'), 2),
                    'highest' => round((float) $items->max('marks'), 2),
                    'lowest' => round((float) $items->min('marks'), 2),
                    'results_count' => $items->count(),
                ];
            })
            ->sortBy('name')
            ->values();

        $attendance = $student->attendances()->get();
        $attendanceSummary = $attendance->countBy('status');

        $behavior = $student->behaviorRecords()->get();

        return [
            'student' => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'student_number' => $student->student_number,
                'class' => $student->schoolClass?->name ?: $student->class,
            ],
            'summary' => [
                'results_count' => $results->count(),
                'average' => $results->isEmpty() ? null : round((float) $results->avg('marks'), 2),
                'attendance_rate' => $attendance->isEmpty()
                    ? null
                    : round($attendance->whereIn('status', ['present', 'late'])->count() / $attendance->count() * 100, 2),
                'behavior_records' => $behavior->count(),
                'notes' => $student->teacherNotes()->count(),
            ],
            'terms' => $byTerm,
            'subjects' => $subjects,
            'attendance' => [
                'total' => $attendance->count(),
                'present' => $attendanceSummary->get('present', 0),
                'absent' => $attendanceSummary->get('absent', 0),
                'late' => $attendanceSummary->get('late', 0),
                'excused' => $attendanceSummary->get('excused', 0),
            ],
            'behavior' => [
                'total' => $behavior->count(),
                'low' => $behavior->where('severity', 'low')->count(),
                'medium' => $behavior->where('severity', 'medium')->count(),
                'high' => $behavior->where('severity', 'high')->count(),
            ],
        ];
    }

    private function resultQuery(array $filters): Builder
    {
        return ExamResult::query()
            ->with(['student:id,full_name,student_number,class,class_id', 'student.schoolClass:id,name', 'subject:id,code,name', 'exam:id,name,type'])
            ->when($filters['exam_id'] ?? null, fn (Builder $query, $examId) => $query->where('exam_id', $examId))
            ->when($filters['subject_id'] ?? null, fn (Builder $query, $subjectId) => $query->where('subject_id', $subjectId))
            ->when($filters['term'] ?? null, fn (Builder $query, $term) => $query->where('term', $term))
            ->when($filters['academic_year'] ?? null, fn (Builder $query, $year) => $query->where('academic_year', $year))
            ->when($filters['class_id'] ?? null, fn (Builder $query, $classId) => $query->whereHas('student', fn (Builder $query) => $query->where('class_id', $classId)));
    }
}
