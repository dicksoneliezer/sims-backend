<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\StoreResultRequest;
use App\Http\Requests\Academic\UpdateResultRequest;
use App\Http\Resources\ExamResultResource;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Student;
use App\Services\AcademicAnalyticsService;
use App\Services\GradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicResultController extends Controller
{
    public function __construct(
        private readonly GradingService $grading,
        private readonly AcademicAnalyticsService $analytics,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 100), 200);

        $results = ExamResult::query()
            ->with(['student:id,full_name,student_number,class,class_id', 'student.schoolClass:id,name', 'subject:id,code,name', 'exam:id,name,type', 'enteredBy:id,full_name'])
            ->when($request->filled('exam_id'), fn ($query) => $query->where('exam_id', $request->integer('exam_id')))
            ->when($request->filled('class_id'), fn ($query) => $query->whereHas('student', fn ($query) => $query->where('class_id', $request->integer('class_id'))))
            ->when($request->filled('subject_id'), fn ($query) => $query->where('subject_id', $request->integer('subject_id')))
            ->when($request->filled('student_id'), fn ($query) => $query->where('student_id', $request->integer('student_id')))
            ->when($request->filled('term'), fn ($query) => $query->where('term', $request->string('term')))
            ->when($request->filled('academic_year'), fn ($query) => $query->where('academic_year', $request->string('academic_year')))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Results retrieved',
            'data' => ExamResultResource::collection($results->items()),
            'meta' => [
                'current_page' => $results->currentPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
                'last_page' => $results->lastPage(),
            ],
        ]);
    }

    public function store(StoreResultRequest $request): JsonResponse
    {
        $exam = Exam::findOrFail($request->integer('exam_id'));
        $student = Student::findOrFail($request->integer('student_id'));
        $marks = (float) $request->input('marks');
        $grade = $this->grading->grade($marks);

        $result = ExamResult::updateOrCreate(
            ['exam_id' => $exam->id, 'student_id' => $student->id, 'subject_id' => $request->integer('subject_id')],
            [
                'exam_name' => $exam->name,
                'exam_type' => $exam->type,
                'term' => $exam->term,
                'academic_year' => $exam->academic_year,
                'marks' => $marks,
                'grade' => $grade['grade'],
                'points' => $grade['points'],
                'remarks' => $request->input('remarks') ?? $grade['remarks'],
                'entered_by' => $request->user()->id,
            ],
        );

        return response()->json([
            'success' => true,
            'message' => 'Result saved',
            'data' => new ExamResultResource($result->load(['student.schoolClass', 'subject', 'exam', 'enteredBy'])),
        ], 201);
    }

    public function update(UpdateResultRequest $request, ExamResult $result): JsonResponse
    {
        $marks = (float) $request->input('marks');
        $grade = $this->grading->grade($marks);

        $result->fill([
            'marks' => $marks,
            'grade' => $grade['grade'],
            'points' => $grade['points'],
            'remarks' => $request->input('remarks', $result->remarks),
            'entered_by' => $request->user()->id,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Result updated',
            'data' => new ExamResultResource($result->fresh()->load(['student.schoolClass', 'subject', 'exam', 'enteredBy'])),
        ]);
    }

    public function destroy(ExamResult $result): JsonResponse
    {
        $result->delete();

        return response()->json([
            'success' => true,
            'message' => 'Result deleted',
            'data' => null,
        ]);
    }

    // =========================================
    // APPROVE RESULT (MPYA)
    // =========================================
    public function approve(Request $request, ExamResult $result): JsonResponse
    {
        $result->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Result approved successfully',
            'data' => new ExamResultResource($result->fresh()->load(['student.schoolClass', 'subject', 'exam', 'enteredBy'])),
        ]);
    }

    public function analysis(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'exam_id' => ['nullable', 'integer', 'exists:exams,id'],
            'class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'term' => ['nullable', 'string', 'max:20'],
            'academic_year' => ['nullable', 'string', 'max:9'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Results analysis retrieved',
            'data' => $this->analytics->analysis($filters),
            'filters' => [
                'exams' => Exam::orderByDesc('academic_year')->orderBy('name')->get(['id', 'name', 'type', 'term', 'academic_year']),
            ],
        ]);
    }

    public function studentProgress(Student $student): JsonResponse
    {
        $student->load('schoolClass:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Student progress retrieved',
            'data' => $this->analytics->studentProgress($student),
        ]);
    }
}