<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subject\StoreSubjectRequest;
use App\Http\Requests\Subject\UpdateSubjectRequest;
use App\Http\Resources\SubjectResource;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $subjects = Subject::query()
            ->withCount('examResults')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('level'), fn ($query) => $query->where('level', $request->string('level')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Subjects retrieved',
            'data' => SubjectResource::collection($subjects->items()),
            'meta' => [
                'current_page' => $subjects->currentPage(),
                'per_page' => $subjects->perPage(),
                'total' => $subjects->total(),
                'last_page' => $subjects->lastPage(),
            ],
        ]);
    }

    public function store(StoreSubjectRequest $request): JsonResponse
    {
        $subject = Subject::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Subject created',
            'data' => new SubjectResource($subject),
        ], 201);
    }

    public function show(Subject $subject): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Subject retrieved',
            'data' => new SubjectResource($subject->loadCount('examResults')),
        ]);
    }

    public function update(UpdateSubjectRequest $request, Subject $subject): JsonResponse
    {
        $subject->fill($request->validated())->save();

        return response()->json([
            'success' => true,
            'message' => 'Subject updated',
            'data' => new SubjectResource($subject->fresh()->loadCount('examResults')),
        ]);
    }

    public function destroy(Subject $subject): JsonResponse
    {
        if ($subject->examResults()->exists()
            || $subject->teacherSubjects()->exists()
            || $subject->timetables()->exists()
            || $subject->syllabusTopics()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This subject is in use and cannot be deleted.',
                'code' => 422,
            ], 422);
        }

        $subject->delete();

        return response()->json([
            'success' => true,
            'message' => 'Subject deleted',
            'data' => null,
        ]);
    }
}
