<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreBehaviorRequest;
use App\Http\Requests\Teacher\UpdateBehaviorRequest;
use App\Http\Resources\BehaviorRecordResource;
use App\Models\BehaviorRecord;
use App\Models\Student;
use App\Services\TeacherAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherBehaviorController extends Controller
{
    public function __construct(private readonly TeacherAccessService $access) {}

    public function index(Request $request): JsonResponse
    {
        $teacher = $request->user();
        $allowedStudentIds = $this->access->students($teacher)->pluck('id');
        $perPage = min((int) $request->integer('per_page', 50), 100);

        $records = BehaviorRecord::query()
            ->with('student:id,full_name,student_number,class', 'teacher:id,full_name')
            ->whereIn('student_id', $allowedStudentIds)
            ->when($request->filled('student_id'), fn ($query) => $query->where('student_id', $request->integer('student_id')))
            ->when($request->filled('severity'), fn ($query) => $query->where('severity', $request->string('severity')))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Behavior records retrieved',
            'data' => BehaviorRecordResource::collection($records->items()),
            'meta' => [
                'current_page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'last_page' => $records->lastPage(),
            ],
        ]);
    }

    public function store(StoreBehaviorRequest $request): JsonResponse
    {
        $teacher = $request->user();
        $student = Student::findOrFail($request->integer('student_id'));
        $this->access->ensureStudent($teacher, $student);

        $record = BehaviorRecord::create([
            ...$request->validated(),
            'teacher_id' => $teacher->id,
        ])->load('student:id,full_name,student_number,class', 'teacher:id,full_name');

        return response()->json([
            'success' => true,
            'message' => 'Behavior record saved',
            'data' => new BehaviorRecordResource($record),
        ], 201);
    }

    public function update(UpdateBehaviorRequest $request, BehaviorRecord $behavior): JsonResponse
    {
        $this->ensureOwner($request, $behavior);
        $behavior->fill($request->validated())->save();

        return response()->json([
            'success' => true,
            'message' => 'Behavior record updated',
            'data' => new BehaviorRecordResource($behavior->fresh()->load('student:id,full_name,student_number,class', 'teacher:id,full_name')),
        ]);
    }

    public function destroy(Request $request, BehaviorRecord $behavior): JsonResponse
    {
        $this->ensureOwner($request, $behavior);
        $behavior->delete();

        return response()->json([
            'success' => true,
            'message' => 'Behavior record deleted',
            'data' => null,
        ]);
    }

    private function ensureOwner(Request $request, BehaviorRecord $behavior): void
    {
        abort_unless($behavior->teacher_id === $request->user()->id, 403, 'You can only manage behavior records you entered.');
    }
}
