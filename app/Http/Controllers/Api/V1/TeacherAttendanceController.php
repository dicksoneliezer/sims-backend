<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\TeacherAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TeacherAttendanceController extends Controller
{
    public function __construct(private readonly TeacherAccessService $access) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'date' => ['nullable', 'date'],
        ]);

        $teacher = $request->user();
        $date = isset($data['date']) ? Carbon::parse($data['date'])->toDateString() : now()->toDateString();

        $studentsQuery = $this->access->students($teacher)->with('schoolClass:id,name')->orderBy('full_name');

        if (! empty($data['class_id'])) {
            $this->access->ensureClass($teacher, (int) $data['class_id']);
            $class = SchoolClass::findOrFail($data['class_id']);
            $studentsQuery->where(function ($query) use ($class) {
                $query->where('class_id', $class->id)
                    ->orWhere(fn ($query) => $query->whereNull('class_id')->where('class', $class->name));
            });
        }

        $students = $studentsQuery->get();
        $records = Attendance::whereIn('student_id', $students->pluck('id'))
            ->whereDate('date', $date)
            ->get()
            ->keyBy('student_id');

        return response()->json([
            'success' => true,
            'message' => 'Attendance roster retrieved',
            'data' => $students->map(function (Student $student) use ($records) {
                $record = $records->get($student->id);

                return [
                    'student_id' => $student->id,
                    'full_name' => $student->full_name,
                    'student_number' => $student->student_number,
                    'class' => $student->schoolClass?->name ?: $student->class,
                    'attendance' => $record ? new AttendanceResource($record) : null,
                ];
            })->values(),
            'filters' => [
                'classes' => $this->access->classes($teacher),
                'date' => $date,
            ],
        ]);
    }

    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        $teacher = $request->user();
        $classId = (int) $request->integer('class_id');
        $this->access->ensureClass($teacher, $classId);
        $class = SchoolClass::findOrFail($classId);
        $date = Carbon::parse($request->input('date'))->toDateString();

        $students = $this->access->students($teacher)
            ->whereIn('id', collect($request->input('records'))->pluck('student_id'))
            ->get()
            ->keyBy('id');

        foreach ($request->input('records') as $record) {
            $student = $students->get($record['student_id']);

            abort_if($student === null, 403, 'You can only mark students in your assigned classes.');

            $belongsToClass = ($student->class_id && (int) $student->class_id === $classId)
                || (! $student->class_id && $student->class === $class->name);

            abort_unless($belongsToClass, 422, 'A student does not belong to the selected class.');

            Attendance::updateOrCreate(
                ['student_id' => $student->id, 'date' => $date],
                [
                    'status' => $record['status'],
                    'remarks' => $record['remarks'] ?? null,
                    'marked_by' => $teacher->id,
                ],
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Attendance saved',
            'data' => [
                'class_id' => $classId,
                'date' => $date,
                'records_count' => count($request->input('records')),
            ],
        ]);
    }

    public function report(Request $request): JsonResponse
    {
        $data = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
        ]);

        $teacher = $request->user();
        $allowedStudentIds = $this->access->students($teacher)->pluck('id');

        if (! empty($data['class_id'])) {
            $this->access->ensureClass($teacher, (int) $data['class_id']);
            $allowedStudentIds = $this->access->students($teacher)
                ->where('class_id', $data['class_id'])
                ->pluck('id');
        }

        $query = Attendance::query()
            ->with('student:id,full_name,student_number,class')
            ->whereIn('student_id', $allowedStudentIds)
            ->when($data['from'] ?? null, fn ($query, $from) => $query->whereDate('date', '>=', $from))
            ->when($data['to'] ?? null, fn ($query, $to) => $query->whereDate('date', '<=', $to))
            ->when($data['student_id'] ?? null, fn ($query, $studentId) => $query->where('student_id', $studentId));

        $summaryRows = (clone $query)->get();
        $byStatus = $summaryRows->countBy('status');
        $byStudent = $summaryRows
            ->groupBy('student_id')
            ->map(function ($rows) {
                $student = $rows->first()->student;

                return [
                    'student_id' => $student?->id,
                    'full_name' => $student?->full_name,
                    'student_number' => $student?->student_number,
                    'class' => $student?->class,
                    'present' => $rows->where('status', 'present')->count(),
                    'absent' => $rows->where('status', 'absent')->count(),
                    'late' => $rows->where('status', 'late')->count(),
                    'excused' => $rows->where('status', 'excused')->count(),
                    'total' => $rows->count(),
                ];
            })
            ->sortBy('full_name')
            ->values();

        $records = $query->orderByDesc('date')->orderBy('student_id')->paginate(50);

        return response()->json([
            'success' => true,
            'message' => 'Attendance report retrieved',
            'data' => AttendanceResource::collection($records->items()),
            'meta' => [
                'current_page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'last_page' => $records->lastPage(),
            ],
            'summary' => [
                'total' => $summaryRows->count(),
                'present' => $byStatus->get('present', 0),
                'absent' => $byStatus->get('absent', 0),
                'late' => $byStatus->get('late', 0),
                'excused' => $byStatus->get('excused', 0),
                'by_student' => $byStudent,
            ],
            'filters' => [
                'classes' => $this->access->classes($teacher),
            ],
        ]);
    }
}
