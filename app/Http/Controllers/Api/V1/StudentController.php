<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\StudentResource;
use App\Http\Resources\TransactionResource;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeacherClass;
use App\Models\TeacherSubject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class StudentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $students = Student::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('student_number', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhere('parent_phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('class'), fn ($query) => $query->where('class', $request->string('class')))
            ->when($request->filled('class_id'), fn ($query) => $query->where('class_id', $request->integer('class_id')))
            ->when($request->filled('school_level'), fn ($query) => $query->where('school_level', $request->string('school_level')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')));

        if ($request->user()->role === 'teacher') {
            $this->scopeForTeacher($students, $request->user());
        }

        $students = $students
            ->orderBy('full_name')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Students retrieved',
            'data' => StudentResource::collection($students->items()),
            'meta' => [
                'current_page' => $students->currentPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
                'last_page' => $students->lastPage(),
            ],
            'filters' => [
                'classes' => Student::query()
                    ->whereNotNull('class')
                    ->distinct()
                    ->orderBy('class')
                    ->pluck('class'),
            ],
        ]);
    }

    public function store(StoreStudentRequest $request): JsonResponse
    {
        $data = $this->syncClass($request->validated());
        unset($data['photo']);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('students/photos', 'public');
        }

        if (empty($data['student_number'])) {
            $data['student_number'] = $this->generateStudentNumber();
        }

        // =========================================
        // 1. Unda User kwa mwanafunzi
        // =========================================
        $defaultPassword = 'student123';

        $user = User::create([
            'username' => $data['student_number'],
            'full_name' => $data['full_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['parent_phone'] ?? null,
            'password' => Hash::make($defaultPassword),
            'role' => 'student',
            'is_active' => $data['is_active'] ?? true,
        ]);

        // =========================================
        // 2. Unda Student (na user_id)
        // =========================================
        $data['user_id'] = $user->id;
        $student = Student::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Student created',
            'data' => new StudentResource($student),
            'default_password' => $defaultPassword,
        ], 201);
    }

    public function show(Request $request, Student $student): JsonResponse
    {
        $this->ensureStudentAccess($request, $student);
        $student->load(['parents', 'schoolClass:id,name,level,section']);

        return response()->json([
            'success' => true,
            'message' => 'Student retrieved',
            'data' => new StudentResource($student),
        ]);
    }

    public function update(UpdateStudentRequest $request, Student $student): JsonResponse
    {
        $data = $this->syncClass($request->validated());
        unset($data['photo'], $data['remove_photo']);

        if ($request->hasFile('photo')) {
            $this->deletePhoto($student);
            $data['photo'] = $request->file('photo')->store('students/photos', 'public');
        } elseif ($request->boolean('remove_photo')) {
            $this->deletePhoto($student);
            $data['photo'] = null;
        }

        $student->fill($data)->save();

        return response()->json([
            'success' => true,
            'message' => 'Student updated',
            'data' => new StudentResource($student->fresh()->load('parents')),
        ]);
    }

    public function destroy(Student $student): JsonResponse
    {
        $this->deletePhoto($student);

        // =========================================
        // Futa User pia (kama ipo)
        // =========================================
        if ($student->user_id) {
            User::where('id', $student->user_id)->where('role', 'student')->delete();
        }

        $student->delete();

        return response()->json([
            'success' => true,
            'message' => 'Student deleted',
            'data' => null,
        ]);
    }

    public function results(Request $request, Student $student): JsonResponse
    {
        $this->ensureStudentAccess($request, $student);

        $results = $student->examResults()
            ->with(['subject:id,code,name', 'exam:id,name,type'])
            ->when($request->filled('term'), fn ($query) => $query->where('term', $request->string('term')))
            ->when($request->filled('academic_year'), fn ($query) => $query->where('academic_year', $request->string('academic_year')))
            ->orderByDesc('academic_year')
            ->orderBy('term')
            ->orderBy('exam_name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Student results retrieved',
            'data' => [
                'student' => new StudentResource($student),
                'results' => $results,
                'summary' => [
                    'count' => $results->count(),
                    'average' => $results->isEmpty() ? null : round((float) $results->avg('marks'), 2),
                    'highest' => $results->isEmpty() ? null : (float) $results->max('marks'),
                    'lowest' => $results->isEmpty() ? null : (float) $results->min('marks'),
                ],
            ],
        ]);
    }

    public function attendance(Request $request, Student $student): JsonResponse
    {
        $this->ensureStudentAccess($request, $student);

        $records = $student->attendances()
            ->when($request->filled('from'), fn ($query) => $query->whereDate('date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('date', '<=', $request->date('to')))
            ->orderByDesc('date')
            ->get();

        $summary = $records->countBy('status');

        return response()->json([
            'success' => true,
            'message' => 'Student attendance retrieved',
            'data' => [
                'student' => new StudentResource($student),
                'records' => $records,
                'summary' => [
                    'total' => $records->count(),
                    'present' => $summary->get('present', 0),
                    'absent' => $summary->get('absent', 0),
                    'late' => $summary->get('late', 0),
                    'excused' => $summary->get('excused', 0),
                ],
            ],
        ]);
    }

    public function invoices(Request $request, Student $student): JsonResponse
    {
        $this->ensureStudentAccess($request, $student);

        $invoices = $student->invoices()
            ->with('feeStructure:id,name,term,academic_year')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Student invoices retrieved',
            'data' => InvoiceResource::collection($invoices),
            'summary' => [
                'total_invoiced' => (float) $invoices->sum('amount'),
                'total_paid' => (float) $invoices->sum('amount_paid'),
                'total_outstanding' => (float) $invoices->sum('balance'),
            ],
        ]);
    }

    public function transactions(Request $request, Student $student): JsonResponse
    {
        $this->ensureStudentAccess($request, $student);

        $transactions = $student->transactions()
            ->with('invoice:id,invoice_number')
            ->latest('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Student transactions retrieved',
            'data' => TransactionResource::collection($transactions),
        ]);
    }

    private function scopeForTeacher(Builder $query, User $teacher): void
    {
        $classIds = $this->teacherClassIds($teacher);
        $classNames = SchoolClass::whereIn('id', $classIds)->pluck('name');

        $query->where(function (Builder $query) use ($classIds, $classNames) {
            $query->whereIn('class_id', $classIds);

            if ($classNames->isNotEmpty()) {
                $query->orWhere(function (Builder $query) use ($classNames) {
                    $query->whereNull('class_id')->whereIn('class', $classNames);
                });
            }
        });
    }

    private function ensureStudentAccess(Request $request, Student $student): void
    {
        if ($request->user()->role !== 'teacher') {
            return;
        }

        $classIds = $this->teacherClassIds($request->user());
        $allowed = ($student->class_id && $classIds->contains($student->class_id))
            || (! $student->class_id && SchoolClass::whereIn('id', $classIds)->where('name', $student->class)->exists());

        abort_unless($allowed, 403, 'You can only access students in your assigned classes.');
    }

    private function teacherClassIds(User $teacher): Collection
    {
        return TeacherClass::where('teacher_id', $teacher->id)->pluck('class_id')
            ->merge(TeacherSubject::where('teacher_id', $teacher->id)->whereNotNull('class_id')->pluck('class_id'))
            ->merge(SchoolClass::where('class_teacher_id', $teacher->id)->pluck('id'))
            ->filter()
            ->unique()
            ->values();
    }

    private function syncClass(array $data): array
    {
        if (! empty($data['class_id'])) {
            $class = SchoolClass::find($data['class_id']);

            if ($class) {
                $data['class'] = $class->name;
            }
        } elseif (array_key_exists('class', $data)) {
            $data['class_id'] = $data['class']
                ? SchoolClass::where('name', $data['class'])->value('id')
                : null;
        }

        return $data;
    }

    private function generateStudentNumber(): string
    {
        $prefix = 'SIMS-'.now()->format('Y').'-';
        $sequence = Student::where('student_number', 'like', $prefix.'%')->count() + 1;

        do {
            $number = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (Student::where('student_number', $number)->exists());

        return $number;
    }

    private function deletePhoto(Student $student): void
    {
        if ($student->photo) {
            Storage::disk('public')->delete($student->photo);
        }
    }
}