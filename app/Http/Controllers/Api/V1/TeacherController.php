<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreTeacherRequest;
use App\Http\Requests\Teacher\UpdateTeacherRequest;
use App\Http\Resources\TeacherResource;
use App\Models\ParentMessage;
use App\Models\Student;
use App\Models\TeacherClass;
use App\Models\TeacherSubject;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $teachers = User::query()
            ->where('role', 'teacher')
            ->withCount(['teacherClasses', 'teacherSubjects'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('full_name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('employment_id', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('full_name')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Teachers retrieved',
            'data' => TeacherResource::collection($teachers->items()),
            'meta' => [
                'current_page' => $teachers->currentPage(),
                'per_page' => $teachers->perPage(),
                'total' => $teachers->total(),
                'last_page' => $teachers->lastPage(),
            ],
        ]);
    }

    public function store(StoreTeacherRequest $request): JsonResponse
    {
        $teacher = User::create($request->validated() + ['role' => 'teacher']);

        return response()->json([
            'success' => true,
            'message' => 'Teacher created',
            'data' => new TeacherResource($teacher->loadCount(['teacherClasses', 'teacherSubjects'])),
        ], 201);
    }

    public function show(User $teacher): JsonResponse
    {
        $this->ensureTeacher($teacher);

        return response()->json([
            'success' => true,
            'message' => 'Teacher retrieved',
            'data' => new TeacherResource($this->loadAssignments($teacher)),
        ]);
    }

    public function update(UpdateTeacherRequest $request, User $teacher): JsonResponse
    {
        $this->ensureTeacher($teacher);

        $data = $request->validated();
        unset($data['role']);

        $teacher->fill($data)->save();

        return response()->json([
            'success' => true,
            'message' => 'Teacher updated',
            'data' => new TeacherResource($this->loadAssignments($teacher->fresh())),
        ]);
    }

    public function destroy(User $teacher): JsonResponse
    {
        $this->ensureTeacher($teacher);

        $teacher->tokens()->delete();
        $teacher->delete();

        return response()->json([
            'success' => true,
            'message' => 'Teacher deleted',
            'data' => null,
        ]);
    }

    public function classes(User $teacher): JsonResponse
    {
        $this->ensureTeacher($teacher);

        $classes = TeacherClass::where('teacher_id', $teacher->id)
            ->with('schoolClass')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Teacher classes retrieved',
            'data' => $classes->map(fn (TeacherClass $assignment) => $this->classPayload($assignment)),
        ]);
    }

    public function subjects(User $teacher): JsonResponse
    {
        $this->ensureTeacher($teacher);

        $subjects = TeacherSubject::where('teacher_id', $teacher->id)
            ->with(['subject', 'schoolClass'])
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Teacher subjects retrieved',
            'data' => $subjects->map(fn (TeacherSubject $assignment) => $this->subjectPayload($assignment)),
        ]);
    }

    public function myClasses(Request $request): JsonResponse
    {
        $classes = TeacherClass::where('teacher_id', $request->user()->id)
            ->with('schoolClass')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Your classes retrieved',
            'data' => $classes->map(fn (TeacherClass $assignment) => $this->classPayload($assignment)),
        ]);
    }

    public function mySubjects(Request $request): JsonResponse
    {
        $subjects = TeacherSubject::where('teacher_id', $request->user()->id)
            ->with(['subject', 'schoolClass'])
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Your subjects retrieved',
            'data' => $subjects->map(fn (TeacherSubject $assignment) => $this->subjectPayload($assignment)),
        ]);
    }

    // =========================================
    // PARENT MESSAGES (MPYA)
    // =========================================
    public function messages(Request $request): JsonResponse
    {
        $teacher = $request->user();

        $messages = ParentMessage::where('sender_id', $teacher->id)
            ->with('parent:id,full_name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($msg) => [
                'id' => $msg->id,
                'subject' => $msg->subject,
                'body' => $msg->message,
                'parent' => $msg->parent,
                'created_at' => $msg->created_at,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Messages retrieved',
            'data' => $messages,
        ]);
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'subject' => 'required|string|max:150',
            'body' => 'required|string',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $parent = $student->parents()->first();

        if (!$parent) {
            return response()->json([
                'success' => false,
                'message' => 'No parent linked to this student.',
            ], 422);
        }

        $message = ParentMessage::create([
            'parent_id' => $parent->id,
            'student_id' => $student->id,
            'sender_id' => $request->user()->id,
            'subject' => $validated['subject'],
            'message' => $validated['body'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Message sent successfully',
            'data' => $message,
        ], 201);
    }

    private function ensureTeacher(User $teacher): void
    {
        abort_unless($teacher->role === 'teacher', 404, 'Teacher not found.');
    }

    private function loadAssignments(User $teacher): User
    {
        return $teacher->load([
            'teacherClasses.schoolClass',
            'teacherSubjects.subject',
            'teacherSubjects.schoolClass',
        ])->loadCount(['teacherClasses', 'teacherSubjects']);
    }

    private function classPayload(TeacherClass $assignment): array
    {
        return [
            'id' => $assignment->id,
            'class_id' => $assignment->class_id,
            'class_name' => $assignment->schoolClass?->name,
            'level' => $assignment->schoolClass?->level,
            'section' => $assignment->schoolClass?->section,
            'academic_year' => $assignment->academic_year,
        ];
    }

    private function subjectPayload(TeacherSubject $assignment): array
    {
        return [
            'id' => $assignment->id,
            'subject_id' => $assignment->subject_id,
            'subject_code' => $assignment->subject?->code,
            'subject_name' => $assignment->subject?->name,
            'class_id' => $assignment->class_id,
            'class_name' => $assignment->schoolClass?->name,
            'academic_year' => $assignment->academic_year,
        ];
    }
}