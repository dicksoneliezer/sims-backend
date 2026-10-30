<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Timetable\StoreTimetableRequest;
use App\Http\Requests\Timetable\UpdateTimetableRequest;
use App\Http\Resources\TimetableResource;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Timetable;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TimetableController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->respond($this->baseQuery(), $request, true);
    }

    public function academic(Request $request): JsonResponse
    {
        return $this->respond($this->baseQuery(), $request, true);
    }

    public function store(StoreTimetableRequest $request): JsonResponse
    {
        $data = $this->normalizeTimes($request->validated());
        $this->validateSlot($data);

        $timetable = Timetable::create($data)->load($this->relations());

        return response()->json([
            'success' => true,
            'message' => 'Timetable slot created',
            'data' => new TimetableResource($timetable),
        ], 201);
    }

    public function show(Timetable $timetable): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Timetable slot retrieved',
            'data' => new TimetableResource($timetable->load($this->relations())),
        ]);
    }

    public function update(UpdateTimetableRequest $request, Timetable $timetable): JsonResponse
    {
        $data = array_merge(
            $timetable->only(['class_id', 'subject_id', 'teacher_id', 'day_of_week', 'start_time', 'end_time', 'room', 'term', 'academic_year']),
            $request->validated(),
        );
        $data = $this->normalizeTimes($data);
        $this->validateSlot($data, $timetable);

        $timetable->fill($data)->save();

        return response()->json([
            'success' => true,
            'message' => 'Timetable slot updated',
            'data' => new TimetableResource($timetable->fresh()->load($this->relations())),
        ]);
    }

    public function destroy(Timetable $timetable): JsonResponse
    {
        $timetable->delete();

        return response()->json([
            'success' => true,
            'message' => 'Timetable slot deleted',
            'data' => null,
        ]);
    }

    public function teacher(Request $request): JsonResponse
    {
        $query = $this->baseQuery()
            ->where('teacher_id', $request->user()->id);

        return $this->respond($query, $request);
    }

    public function student(Request $request): JsonResponse
    {
        $student = $request->user()->studentProfile;

        abort_unless($student, 404, 'Student profile not found.');

        return $this->respond($this->forStudent($student), $request);
    }

    public function child(Request $request, Student $student): JsonResponse
    {
        $isLinked = $request->user()->children()->whereKey($student->id)->exists();

        abort_unless($isLinked, 403, 'You can only view timetables for your children.');

        return $this->respond($this->forStudent($student), $request);
    }

    private function forStudent(Student $student): Builder
    {
        $classId = $student->class_id ?? SchoolClass::where('name', $student->class)->value('id');

        return $this->baseQuery()->when(
            $classId,
            fn ($query) => $query->where('class_id', $classId),
            fn ($query) => $query->whereRaw('1 = 0'),
        );
    }

    private function baseQuery(): Builder
    {
        $request = request();

        return Timetable::query()
            ->with($this->relations())
            ->when($request->filled('class_id'), fn ($query) => $query->where('class_id', $request->integer('class_id')))
            ->when($request->filled('teacher_id'), fn ($query) => $query->where('teacher_id', $request->integer('teacher_id')))
            ->when($request->filled('subject_id'), fn ($query) => $query->where('subject_id', $request->integer('subject_id')))
            ->when($request->filled('day_of_week'), fn ($query) => $query->where('day_of_week', $request->string('day_of_week')))
            ->when($request->filled('term'), fn ($query) => $query->where('term', $request->string('term')))
            ->when($request->filled('academic_year'), fn ($query) => $query->where('academic_year', $request->string('academic_year')))
            ->orderByRaw("CASE day_of_week WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2 WHEN 'Wednesday' THEN 3 WHEN 'Thursday' THEN 4 WHEN 'Friday' THEN 5 WHEN 'Saturday' THEN 6 ELSE 7 END")
            ->orderBy('start_time');
    }

    private function respond(Builder $query, Request $request, bool $withFilters = false): JsonResponse
    {
        $slots = $query->get();

        $payload = [
            'success' => true,
            'message' => 'Timetable retrieved',
            'data' => TimetableResource::collection($slots),
        ];

        if ($withFilters) {
            $payload['filters'] = [
                'classes' => SchoolClass::orderBy('name')->get(['id', 'name', 'level']),
                'subjects' => Subject::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
                'teachers' => User::where('role', 'teacher')->where('is_active', true)->orderBy('full_name')->get(['id', 'full_name']),
                'terms' => Timetable::query()->whereNotNull('term')->distinct()->orderBy('term')->pluck('term'),
                'years' => Timetable::query()->whereNotNull('academic_year')->distinct()->orderByDesc('academic_year')->pluck('academic_year'),
            ];
        }

        return response()->json($payload);
    }

    private function relations(): array
    {
        return [
            'schoolClass:id,name,level',
            'subject:id,code,name',
            'teacher:id,full_name',
        ];
    }

    private function normalizeTimes(array $data): array
    {
        foreach (['start_time', 'end_time'] as $field) {
            if (! empty($data[$field]) && strlen($data[$field]) === 5) {
                $data[$field] .= ':00';
            }
        }

        return $data;
    }

    private function validateSlot(array $data, ?Timetable $ignore = null): void
    {
        if (strtotime($data['start_time']) >= strtotime($data['end_time'])) {
            throw ValidationException::withMessages([
                'end_time' => ['The end time must be after the start time.'],
            ]);
        }

        $query = Timetable::query()
            ->where('day_of_week', $data['day_of_week'])
            ->where('term', $data['term'] ?? null)
            ->where('academic_year', $data['academic_year'] ?? null)
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->when($ignore, fn ($query) => $query->where('id', '!=', $ignore->id));

        if ((clone $query)->where('class_id', $data['class_id'])->exists()) {
            throw ValidationException::withMessages([
                'start_time' => ['This class already has a lesson during that time.'],
            ]);
        }

        if (! empty($data['teacher_id']) && (clone $query)->where('teacher_id', $data['teacher_id'])->exists()) {
            throw ValidationException::withMessages([
                'teacher_id' => ['This teacher already has a lesson during that time.'],
            ]);
        }
    }
}
