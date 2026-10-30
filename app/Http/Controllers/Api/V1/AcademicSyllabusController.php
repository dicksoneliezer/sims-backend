<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\SaveSyllabusProgressRequest;
use App\Http\Requests\Academic\StoreSyllabusTopicRequest;
use App\Http\Requests\Academic\UpdateSyllabusTopicRequest;
use App\Http\Resources\SyllabusProgressResource;
use App\Http\Resources\SyllabusTopicResource;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\SyllabusProgress;
use App\Models\SyllabusTopic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicSyllabusController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 50), 100);

        $topics = SyllabusTopic::query()
            ->with(['subject:id,code,name', 'schoolClass:id,name'])
            ->withCount('progresses')
            ->when($request->filled('subject_id'), fn ($query) => $query->where('subject_id', $request->integer('subject_id')))
            ->when($request->filled('class_id'), fn ($query) => $query->where('class_id', $request->integer('class_id')))
            ->when($request->filled('term'), fn ($query) => $query->where('term', $request->string('term')))
            ->when($request->filled('search'), fn ($query) => $query->where('title', 'like', '%'.$request->string('search')->toString().'%'))
            ->orderBy('term')
            ->orderBy('sort_order')
            ->orderBy('week')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Syllabus topics retrieved',
            'data' => SyllabusTopicResource::collection($topics->items()),
            'meta' => [
                'current_page' => $topics->currentPage(),
                'per_page' => $topics->perPage(),
                'total' => $topics->total(),
                'last_page' => $topics->lastPage(),
            ],
            'filters' => $this->filters(),
        ]);
    }

    public function store(StoreSyllabusTopicRequest $request): JsonResponse
    {
        $topic = SyllabusTopic::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Syllabus topic created',
            'data' => new SyllabusTopicResource($topic->load(['subject:id,code,name', 'schoolClass:id,name'])->loadCount('progresses')),
        ], 201);
    }

    public function show(SyllabusTopic $syllabusTopic): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Syllabus topic retrieved',
            'data' => new SyllabusTopicResource($syllabusTopic->load(['subject:id,code,name', 'schoolClass:id,name'])->loadCount('progresses')),
        ]);
    }

    public function update(UpdateSyllabusTopicRequest $request, SyllabusTopic $syllabusTopic): JsonResponse
    {
        $syllabusTopic->fill($request->validated())->save();

        return response()->json([
            'success' => true,
            'message' => 'Syllabus topic updated',
            'data' => new SyllabusTopicResource($syllabusTopic->fresh()->load(['subject:id,code,name', 'schoolClass:id,name'])->loadCount('progresses')),
        ]);
    }

    public function destroy(SyllabusTopic $syllabusTopic): JsonResponse
    {
        $syllabusTopic->delete();

        return response()->json([
            'success' => true,
            'message' => 'Syllabus topic deleted',
            'data' => null,
        ]);
    }

    public function progressIndex(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 50), 100);

        $progress = SyllabusProgress::query()
            ->with(['topic.subject:id,code,name', 'topic.schoolClass:id,name', 'teacher:id,full_name'])
            ->when($request->filled('syllabus_topic_id'), fn ($query) => $query->where('syllabus_topic_id', $request->integer('syllabus_topic_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('class_id'), fn ($query) => $query->whereHas('topic', fn ($query) => $query->where('class_id', $request->integer('class_id'))))
            ->when($request->filled('subject_id'), fn ($query) => $query->whereHas('topic', fn ($query) => $query->where('subject_id', $request->integer('subject_id'))))
            ->orderByDesc('updated_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Syllabus progress retrieved',
            'data' => SyllabusProgressResource::collection($progress->items()),
            'meta' => [
                'current_page' => $progress->currentPage(),
                'per_page' => $progress->perPage(),
                'total' => $progress->total(),
                'last_page' => $progress->lastPage(),
            ],
        ]);
    }

    public function saveProgress(SaveSyllabusProgressRequest $request): JsonResponse
    {
        $progress = SyllabusProgress::updateOrCreate(
            [
                'syllabus_topic_id' => $request->integer('syllabus_topic_id'),
                'teacher_id' => $request->user()->id,
            ],
            [
                'status' => $request->input('status'),
                'completion_date' => $request->input('completion_date') ?: ($request->input('status') === 'completed' ? now()->toDateString() : null),
                'remarks' => $request->input('remarks'),
            ],
        );

        return response()->json([
            'success' => true,
            'message' => 'Syllabus progress saved',
            'data' => new SyllabusProgressResource($progress->load(['topic.subject:id,code,name', 'topic.schoolClass:id,name', 'teacher:id,full_name'])),
        ]);
    }

    private function filters(): array
    {
        return [
            'subjects' => Subject::orderBy('name')->get(['id', 'code', 'name']),
            'classes' => SchoolClass::orderBy('name')->get(['id', 'name']),
        ];
    }
}
