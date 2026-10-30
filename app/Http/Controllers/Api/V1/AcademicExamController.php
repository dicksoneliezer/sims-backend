<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\StoreExamRequest;
use App\Http\Requests\Academic\UpdateExamRequest;
use App\Http\Resources\ExamResource;
use App\Http\Resources\ExamResultResource;
use App\Models\Exam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicExamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 50), 100);

        $exams = Exam::query()
            ->withCount('results')
            ->with('createdBy:id,full_name')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search')->toString().'%'))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('term'), fn ($query) => $query->where('term', $request->string('term')))
            ->when($request->filled('academic_year'), fn ($query) => $query->where('academic_year', $request->string('academic_year')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByDesc('academic_year')
            ->orderByDesc('start_date')
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Exams retrieved',
            'data' => ExamResource::collection($exams->items()),
            'meta' => [
                'current_page' => $exams->currentPage(),
                'per_page' => $exams->perPage(),
                'total' => $exams->total(),
                'last_page' => $exams->lastPage(),
            ],
        ]);
    }

    public function store(StoreExamRequest $request): JsonResponse
    {
        $exam = Exam::create([
            ...$request->validated(),
            'status' => $request->input('status', 'active'),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Exam created',
            'data' => new ExamResource($exam->loadCount('results')->load('createdBy:id,full_name')),
        ], 201);
    }

    public function show(Exam $exam): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Exam retrieved',
            'data' => new ExamResource($exam->loadCount('results')->load('createdBy:id,full_name')),
        ]);
    }

    public function update(UpdateExamRequest $request, Exam $exam): JsonResponse
    {
        $exam->fill($request->validated())->save();

        return response()->json([
            'success' => true,
            'message' => 'Exam updated',
            'data' => new ExamResource($exam->fresh()->loadCount('results')->load('createdBy:id,full_name')),
        ]);
    }

    public function destroy(Exam $exam): JsonResponse
    {
        if ($exam->results()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This exam has results and cannot be deleted.',
                'code' => 422,
            ], 422);
        }

        $exam->delete();

        return response()->json([
            'success' => true,
            'message' => 'Exam deleted',
            'data' => null,
        ]);
    }

    public function results(Request $request, Exam $exam): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 100), 200);

        $query = $exam->results()
            ->with(['student:id,full_name,student_number,class,class_id', 'student.schoolClass:id,name', 'subject:id,code,name', 'enteredBy:id,full_name'])
            ->when($request->filled('class_id'), fn ($query) => $query->whereHas('student', fn ($query) => $query->where('class_id', $request->integer('class_id'))))
            ->when($request->filled('subject_id'), fn ($query) => $query->where('subject_id', $request->integer('subject_id')))
            ->when($request->filled('student_id'), fn ($query) => $query->where('student_id', $request->integer('student_id')));

        $results = (clone $query)->orderBy('student_id')->paginate($perPage);
        $all = $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Exam results retrieved',
            'data' => ExamResultResource::collection($results->items()),
            'meta' => [
                'current_page' => $results->currentPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
                'last_page' => $results->lastPage(),
            ],
            'summary' => [
                'results_count' => $all->count(),
                'students_count' => $all->pluck('student_id')->unique()->count(),
                'average' => $all->isEmpty() ? null : round((float) $all->avg('marks'), 2),
                'highest' => $all->isEmpty() ? null : round((float) $all->max('marks'), 2),
                'lowest' => $all->isEmpty() ? null : round((float) $all->min('marks'), 2),
                'pass_rate' => $all->isEmpty() ? null : round($all->where('marks', '>=', 50)->count() / $all->count() * 100, 2),
            ],
        ]);
    }
}
