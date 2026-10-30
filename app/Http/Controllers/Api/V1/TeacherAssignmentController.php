<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreTeacherClassRequest;
use App\Http\Requests\Teacher\StoreTeacherSubjectRequest;
use App\Models\TeacherClass;
use App\Models\TeacherSubject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherAssignmentController extends Controller
{
    public function classes(Request $request): JsonResponse
    {
        $assignments = TeacherClass::query()
            ->with(['teacher:id,full_name', 'schoolClass'])
            ->when($request->filled('teacher_id'), fn ($query) => $query->where('teacher_id', $request->integer('teacher_id')))
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Teacher class assignments retrieved',
            'data' => $assignments->map(fn (TeacherClass $assignment) => [
                'id' => $assignment->id,
                'teacher_id' => $assignment->teacher_id,
                'teacher_name' => $assignment->teacher?->full_name,
                'class_id' => $assignment->class_id,
                'class_name' => $assignment->schoolClass?->name,
                'academic_year' => $assignment->academic_year,
            ]),
        ]);
    }

    public function storeClass(StoreTeacherClassRequest $request): JsonResponse
    {
        $data = $request->validated();
        $duplicate = TeacherClass::where('teacher_id', $data['teacher_id'])
            ->where('class_id', $data['class_id'])
            ->where('academic_year', $data['academic_year'] ?? null)
            ->exists();

        if ($duplicate) {
            return response()->json([
                'success' => false,
                'message' => 'This class is already assigned to the teacher.',
                'code' => 422,
            ], 422);
        }

        $assignment = TeacherClass::create($data)->load(['teacher:id,full_name', 'schoolClass']);

        return response()->json([
            'success' => true,
            'message' => 'Teacher assigned to class',
            'data' => [
                'id' => $assignment->id,
                'teacher_id' => $assignment->teacher_id,
                'teacher_name' => $assignment->teacher?->full_name,
                'class_id' => $assignment->class_id,
                'class_name' => $assignment->schoolClass?->name,
                'academic_year' => $assignment->academic_year,
            ],
        ], 201);
    }

    public function destroyClass(TeacherClass $teacherClass): JsonResponse
    {
        $teacherClass->delete();

        return response()->json([
            'success' => true,
            'message' => 'Class assignment removed',
            'data' => null,
        ]);
    }

    public function subjects(Request $request): JsonResponse
    {
        $assignments = TeacherSubject::query()
            ->with(['teacher:id,full_name', 'subject:id,code,name', 'schoolClass:id,name'])
            ->when($request->filled('teacher_id'), fn ($query) => $query->where('teacher_id', $request->integer('teacher_id')))
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Teacher subject assignments retrieved',
            'data' => $assignments->map(fn (TeacherSubject $assignment) => [
                'id' => $assignment->id,
                'teacher_id' => $assignment->teacher_id,
                'teacher_name' => $assignment->teacher?->full_name,
                'subject_id' => $assignment->subject_id,
                'subject_code' => $assignment->subject?->code,
                'subject_name' => $assignment->subject?->name,
                'class_id' => $assignment->class_id,
                'class_name' => $assignment->schoolClass?->name,
                'academic_year' => $assignment->academic_year,
            ]),
        ]);
    }

    public function storeSubject(StoreTeacherSubjectRequest $request): JsonResponse
    {
        $data = $request->validated();
        $duplicate = TeacherSubject::where('teacher_id', $data['teacher_id'])
            ->where('subject_id', $data['subject_id'])
            ->when(
                empty($data['class_id']),
                fn ($query) => $query->whereNull('class_id'),
                fn ($query) => $query->where('class_id', $data['class_id']),
            )
            ->where('academic_year', $data['academic_year'] ?? null)
            ->exists();

        if ($duplicate) {
            return response()->json([
                'success' => false,
                'message' => 'This subject is already assigned to the teacher.',
                'code' => 422,
            ], 422);
        }

        $assignment = TeacherSubject::create($data)->load(['teacher:id,full_name', 'subject:id,code,name', 'schoolClass:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Teacher assigned to subject',
            'data' => [
                'id' => $assignment->id,
                'teacher_id' => $assignment->teacher_id,
                'teacher_name' => $assignment->teacher?->full_name,
                'subject_id' => $assignment->subject_id,
                'subject_code' => $assignment->subject?->code,
                'subject_name' => $assignment->subject?->name,
                'class_id' => $assignment->class_id,
                'class_name' => $assignment->schoolClass?->name,
                'academic_year' => $assignment->academic_year,
            ],
        ], 201);
    }

    public function destroySubject(TeacherSubject $teacherSubject): JsonResponse
    {
        $teacherSubject->delete();

        return response()->json([
            'success' => true,
            'message' => 'Subject assignment removed',
            'data' => null,
        ]);
    }
}
