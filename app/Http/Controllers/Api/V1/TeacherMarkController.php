<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreMarksRequest;
use App\Http\Requests\Teacher\UpdateMarkRequest;
use App\Http\Resources\ExamResultResource;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\SchoolClass;
use App\Models\TeacherSubject;
use App\Services\GradingService;
use App\Services\TeacherAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherMarkController extends Controller
{
    public function __construct(
        private readonly TeacherAccessService $access,
        private readonly GradingService $grading,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $teacher = $request->user();
        $assignments = $this->access->subjectAssignments($teacher);
        $allowedStudentIds = $this->access->students($teacher)->pluck('id');

        $query = ExamResult::query()
            ->with(['student:id,full_name,student_number,class,class_id', 'student.schoolClass:id,name', 'subject:id,code,name', 'exam:id,name,type', 'enteredBy:id,full_name'])
            ->whereIn('student_id', $allowedStudentIds)
            ->whereIn('subject_id', $assignments->pluck('subject_id')->unique());

        if ($request->filled('exam_id')) {
            $query->where('exam_id', $request->integer('exam_id'));
        }

        if ($request->filled('class_id')) {
            $this->access->ensureClass($teacher, $request->integer('class_id'));
            $query->whereHas('student', fn ($query) => $query->where('class_id', $request->integer('class_id')));
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->integer('subject_id'));
        }

        $query->when($request->filled('student_id'), fn ($query) => $query->where('student_id', $request->integer('student_id')))
            ->when($request->filled('term'), fn ($query) => $query->where('term', $request->string('term')))
            ->when($request->filled('academic_year'), fn ($query) => $query->where('academic_year', $request->string('academic_year')));

        $perPage = min((int) $request->integer('per_page', 100), 200);
        $results = $query->orderByDesc('created_at')->orderBy('student_id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Marks retrieved',
            'data' => ExamResultResource::collection($results->items()),
            'meta' => [
                'current_page' => $results->currentPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
                'last_page' => $results->lastPage(),
            ],
            'filters' => [
                'classes' => $this->access->classes($teacher),
                'assignments' => $assignments->map(fn ($assignment) => [
                    'id' => $assignment->id,
                    'subject_id' => $assignment->subject_id,
                    'subject_name' => $assignment->subject?->name,
                    'subject_code' => $assignment->subject?->code,
                    'class_id' => $assignment->class_id,
                    'class_name' => $assignment->schoolClass?->name,
                ])->values(),
                'exams' => Exam::orderByDesc('academic_year')->orderBy('name')->get(['id', 'name', 'type', 'term', 'academic_year']),
            ],
        ]);
    }

    public function store(StoreMarksRequest $request): JsonResponse
    {
        $teacher = $request->user();
        $classId = (int) $request->integer('class_id');
        $subjectId = (int) $request->integer('subject_id');
        $this->access->ensureSubjectClass($teacher, $subjectId, $classId);

        $exam = Exam::findOrFail($request->integer('exam_id'));
        $class = SchoolClass::findOrFail($classId);
        $allowedStudents = $this->access->students($teacher)
            ->where(function ($query) use ($class) {
                $query->where('class_id', $class->id)
                    ->orWhere(fn ($query) => $query->whereNull('class_id')->where('class', $class->name));
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $saved = DB::transaction(function () use ($request, $teacher, $exam, $subjectId, $allowedStudents) {
            return collect($request->input('entries'))->map(function (array $entry) use ($teacher, $exam, $subjectId, $allowedStudents) {
                abort_unless($allowedStudents->contains((int) $entry['student_id']), 422, 'A student does not belong to the selected class.');

                return $this->persistMark(
                    $exam,
                    (int) $entry['student_id'],
                    $subjectId,
                    (float) $entry['marks'],
                    $entry['remarks'] ?? null,
                    $teacher->id,
                );
            });
        });

        return response()->json([
            'success' => true,
            'message' => 'Marks saved',
            'data' => ExamResultResource::collection($saved),
        ]);
    }

    public function update(UpdateMarkRequest $request, ExamResult $result): JsonResponse
    {
        $this->ensureMarkAccess($request, $result);
        $grade = $this->grading->grade((float) $request->input('marks'));

        $result->fill([
            'marks' => $request->input('marks'),
            'grade' => $grade['grade'],
            'points' => $grade['points'],
            'remarks' => $request->input('remarks', $result->remarks),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Mark updated',
            'data' => new ExamResultResource($result->fresh()->load(['student.schoolClass', 'subject', 'exam'])),
        ]);
    }

    public function destroy(Request $request, ExamResult $result): JsonResponse
    {
        $this->ensureMarkAccess($request, $result);
        $result->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mark deleted',
            'data' => null,
        ]);
    }

    public function classResults(Request $request): JsonResponse
    {
        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:school_classes,id'],
            'exam_id' => ['nullable', 'integer', 'exists:exams,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'term' => ['nullable', 'string', 'max:20'],
            'academic_year' => ['nullable', 'string', 'max:9'],
        ]);

        $teacher = $request->user();
        $this->access->ensureClass($teacher, (int) $data['class_id']);
        $class = SchoolClass::findOrFail($data['class_id']);
        $students = $this->access->students($teacher)
            ->with('schoolClass:id,name')
            ->where(function ($query) use ($class) {
                $query->where('class_id', $class->id)
                    ->orWhere(fn ($query) => $query->whereNull('class_id')->where('class', $class->name));
            })
            ->orderBy('full_name')
            ->get();

        $results = ExamResult::with(['student:id,full_name,student_number,class,class_id', 'subject:id,code,name'])
            ->whereIn('student_id', $students->pluck('id'))
            ->whereIn('subject_id', $this->access->subjectAssignments($teacher)->pluck('subject_id')->unique())
            ->when($data['exam_id'] ?? null, fn ($query, $examId) => $query->where('exam_id', $examId))
            ->when($data['subject_id'] ?? null, fn ($query, $subjectId) => $query->where('subject_id', $subjectId))
            ->when($data['term'] ?? null, fn ($query, $term) => $query->where('term', $term))
            ->when($data['academic_year'] ?? null, fn ($query, $year) => $query->where('academic_year', $year))
            ->get();

        $bySubject = $results->groupBy('subject_id')->map(function ($items) {
            $subject = $items->first()->subject;

            return [
                'subject_id' => $subject?->id,
                'code' => $subject?->code,
                'name' => $subject?->name,
                'average' => $items->isEmpty() ? null : round((float) $items->avg('marks'), 2),
                'highest' => $items->isEmpty() ? null : round((float) $items->max('marks'), 2),
                'lowest' => $items->isEmpty() ? null : round((float) $items->min('marks'), 2),
                'results_count' => $items->count(),
            ];
        })->sortBy('name')->values();

        $byStudent = $students->map(function ($student) use ($results) {
            $studentResults = $results->where('student_id', $student->id);

            return [
                'student_id' => $student->id,
                'full_name' => $student->full_name,
                'student_number' => $student->student_number,
                'class' => $student->schoolClass?->name ?: $student->class,
                'average' => $studentResults->isEmpty() ? null : round((float) $studentResults->avg('marks'), 2),
                'results_count' => $studentResults->count(),
                'subjects' => $studentResults->map(fn ($result) => [
                    'subject_id' => $result->subject_id,
                    'subject' => $result->subject?->name,
                    'marks' => (float) $result->marks,
                    'grade' => $result->grade,
                ])->values(),
            ];
        })->sortByDesc('average')->values();

        return response()->json([
            'success' => true,
            'message' => 'Class results retrieved',
            'data' => [
                'class' => ['id' => $class->id, 'name' => $class->name],
                'summary' => [
                    'students_count' => $students->count(),
                    'results_count' => $results->count(),
                    'average' => $results->isEmpty() ? null : round((float) $results->avg('marks'), 2),
                    'highest' => $results->isEmpty() ? null : round((float) $results->max('marks'), 2),
                    'lowest' => $results->isEmpty() ? null : round((float) $results->min('marks'), 2),
                    'pass_rate' => $results->isEmpty() ? null : round($results->where('marks', '>=', 50)->count() / $results->count() * 100, 2),
                ],
                'grade_distribution' => $results->groupBy(fn ($result) => $result->grade ?: 'Ungraded')->map(fn ($items, $grade) => [
                    'grade' => (string) $grade,
                    'count' => $items->count(),
                ])->values()->sortBy('grade')->values(),
                'subjects' => $bySubject,
                'students' => $byStudent,
            ],
        ]);
    }

    private function ensureMarkAccess(Request $request, ExamResult $result): void
    {
        $teacher = $request->user();
        $result->loadMissing('student');
        $this->access->ensureStudent($teacher, $result->student);

        if ($result->student->class_id) {
            $this->access->ensureSubjectClass($teacher, $result->subject_id, $result->student->class_id);
        } else {
            abort_unless(
                TeacherSubject::where('teacher_id', $teacher->id)->where('subject_id', $result->subject_id)->exists(),
                403,
                'You are not assigned to this subject.',
            );
        }
    }

    private function persistMark(Exam $exam, int $studentId, int $subjectId, float $marks, ?string $remarks, int $teacherId): ExamResult
    {
        $grade = $this->grading->grade($marks);

        return ExamResult::updateOrCreate(
            ['exam_id' => $exam->id, 'student_id' => $studentId, 'subject_id' => $subjectId],
            [
                'exam_name' => $exam->name,
                'exam_type' => $exam->type,
                'term' => $exam->term,
                'academic_year' => $exam->academic_year,
                'marks' => $marks,
                'grade' => $grade['grade'],
                'points' => $grade['points'],
                'remarks' => $remarks ?? $grade['remarks'],
                'entered_by' => $teacherId,
            ],
        )->load(['student.schoolClass', 'subject', 'exam']);
    }
}
