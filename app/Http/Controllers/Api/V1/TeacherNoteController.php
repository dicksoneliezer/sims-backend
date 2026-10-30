<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreTeacherNoteRequest;
use App\Http\Requests\Teacher\UpdateTeacherNoteRequest;
use App\Http\Resources\TeacherNoteResource;
use App\Models\Student;
use App\Models\TeacherNote;
use App\Services\TeacherAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherNoteController extends Controller
{
    public function __construct(private readonly TeacherAccessService $access) {}

    public function index(Request $request): JsonResponse
    {
        $teacher = $request->user();
        $perPage = min((int) $request->integer('per_page', 50), 100);

        $notes = TeacherNote::query()
            ->with('student:id,full_name,student_number,class', 'teacher:id,full_name')
            ->where('teacher_id', $teacher->id)
            ->when($request->filled('student_id'), fn ($query) => $query->where('student_id', $request->integer('student_id')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(fn ($query) => $query->where('title', 'like', "%{$search}%")->orWhere('note', 'like', "%{$search}%"));
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Notes retrieved',
            'data' => TeacherNoteResource::collection($notes->items()),
            'meta' => [
                'current_page' => $notes->currentPage(),
                'per_page' => $notes->perPage(),
                'total' => $notes->total(),
                'last_page' => $notes->lastPage(),
            ],
        ]);
    }

    public function store(StoreTeacherNoteRequest $request): JsonResponse
    {
        $teacher = $request->user();
        $data = $request->validated();

        if (! empty($data['student_id'])) {
            $student = Student::findOrFail($data['student_id']);
            $this->access->ensureStudent($teacher, $student);
        }

        $note = TeacherNote::create([
            ...$data,
            'teacher_id' => $teacher->id,
            'is_read' => false,
        ])->load('student:id,full_name,student_number,class', 'teacher:id,full_name');

        return response()->json([
            'success' => true,
            'message' => 'Note saved',
            'data' => new TeacherNoteResource($note),
        ], 201);
    }

    public function update(UpdateTeacherNoteRequest $request, TeacherNote $note): JsonResponse
    {
        $this->ensureOwner($request, $note);
        $data = $request->validated();

        if (array_key_exists('student_id', $data) && $data['student_id']) {
            $this->access->ensureStudent($request->user(), Student::findOrFail($data['student_id']));
        }

        $note->fill($data)->save();

        return response()->json([
            'success' => true,
            'message' => 'Note updated',
            'data' => new TeacherNoteResource($note->fresh()->load('student:id,full_name,student_number,class', 'teacher:id,full_name')),
        ]);
    }

    public function destroy(Request $request, TeacherNote $note): JsonResponse
    {
        $this->ensureOwner($request, $note);
        $note->delete();

        return response()->json([
            'success' => true,
            'message' => 'Note deleted',
            'data' => null,
        ]);
    }

    private function ensureOwner(Request $request, TeacherNote $note): void
    {
        abort_unless($note->teacher_id === $request->user()->id, 403, 'You can only manage your own notes.');
    }
}
