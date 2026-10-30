<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SchoolClass\StoreSchoolClassRequest;
use App\Http\Requests\SchoolClass\UpdateSchoolClassRequest;
use App\Http\Resources\SchoolClassResource;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolClassController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $classes = SchoolClass::query()
            ->with('classTeacher')
            ->withCount('students')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('section', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('level'), fn ($query) => $query->where('level', $request->string('level')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('level')
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Classes retrieved',
            'data' => SchoolClassResource::collection($classes->items()),
            'meta' => [
                'current_page' => $classes->currentPage(),
                'per_page' => $classes->perPage(),
                'total' => $classes->total(),
                'last_page' => $classes->lastPage(),
            ],
        ]);
    }

    public function store(StoreSchoolClassRequest $request): JsonResponse
    {
        $class = SchoolClass::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Class created',
            'data' => new SchoolClassResource($class->load('classTeacher')->loadCount('students')),
        ], 201);
    }

    public function show(SchoolClass $schoolClass): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Class retrieved',
            'data' => new SchoolClassResource($schoolClass->load('classTeacher')->loadCount('students')),
        ]);
    }

    public function update(UpdateSchoolClassRequest $request, SchoolClass $schoolClass): JsonResponse
    {
        $oldName = $schoolClass->name;
        $schoolClass->fill($request->validated())->save();

        if ($oldName !== $schoolClass->name) {
            Student::where('class_id', $schoolClass->id)->update(['class' => $schoolClass->name]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Class updated',
            'data' => new SchoolClassResource($schoolClass->fresh()->load('classTeacher')->loadCount('students')),
        ]);
    }

    public function destroy(SchoolClass $schoolClass): JsonResponse
    {
        if ($schoolClass->students()->exists() || $schoolClass->timetables()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This class has students or timetable entries and cannot be deleted.',
                'code' => 422,
            ], 422);
        }

        $schoolClass->delete();

        return response()->json([
            'success' => true,
            'message' => 'Class deleted',
            'data' => null,
        ]);
    }
}
