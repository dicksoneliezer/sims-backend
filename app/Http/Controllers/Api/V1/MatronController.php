<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\BedAssignment;
use App\Models\Dormitory;
use App\Models\DormitoryInspection;
use App\Models\Incident;
use App\Models\ParentMessage;
use App\Models\Room;
use App\Models\RollCall;
use App\Models\SickBay;
use App\Models\Student;
use App\Models\StudentLeave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MatronController extends Controller
{
    // =========================================
    // DASHBOARD
    // =========================================
    public function dashboard(Request $request): JsonResponse
    {
        $today = now()->toDateString();

        return response()->json([
            'success' => true,
            'message' => 'Matron dashboard retrieved',
            'data' => [
                'boarding_students' => Student::where('is_active', true)->count(),
                'sick_students' => SickBay::where('status', 'admitted')->count(),
                'students_on_leave' => StudentLeave::where('status', 'approved')
                    ->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today)
                    ->count(),
                'overdue_returns' => StudentLeave::where('status', 'approved')
                    ->whereDate('end_date', '<', $today)
                    ->count(),
                'open_incidents' => Incident::where('status', 'open')->count(),
                'dormitories_count' => Dormitory::count(),
                'beds_count' => Bed::count(),
                'available_beds' => Bed::where('status', 'available')->count(),
            ],
        ]);
    }

    // =========================================
    // STUDENTS (kwa bed assignment)
    // =========================================
    public function students(Request $request): JsonResponse
    {
        $students = Student::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->toString();
                $q->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('student_number', 'like', "%{$search}%");
                });
            })
            ->where('is_active', true)
            ->with('schoolClass:id,name')
            ->orderBy('full_name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Students retrieved',
            'data' => $students,
        ]);
    }

    // =========================================
    // DORMITORIES
    // =========================================
    public function dormitories(Request $request): JsonResponse
    {
        $dormitories = Dormitory::with('matron:id,full_name')
            ->withCount('rooms', 'beds')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%' . $request->string('search') . '%'))
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Dormitories retrieved',
            'data' => $dormitories,
        ]);
    }

    public function storeDormitory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'gender' => 'nullable|in:male,female,mixed',
            'capacity' => 'required|integer|min:0',
            'matron_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string',
        ]);

        $dormitory = Dormitory::create($validated + ['is_active' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Dormitory created',
            'data' => $dormitory,
        ], 201);
    }

    public function updateDormitory(Request $request, Dormitory $dormitory): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'gender' => 'nullable|in:male,female,mixed',
            'capacity' => 'sometimes|integer|min:0',
            'matron_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $dormitory->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Dormitory updated',
            'data' => $dormitory,
        ]);
    }

    public function destroyDormitory(Dormitory $dormitory): JsonResponse
    {
        $dormitory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Dormitory deleted',
            'data' => null,
        ]);
    }

    // =========================================
    // ROOMS
    // =========================================
    public function rooms(Request $request): JsonResponse
    {
        $rooms = Room::query()
            ->when($request->filled('dormitory_id'), fn ($q) => $q->where('dormitory_id', $request->integer('dormitory_id')))
            ->with('dormitory:id,name')
            ->withCount('beds')
            ->orderBy('dormitory_id')
            ->orderBy('room_number')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Rooms retrieved',
            'data' => $rooms,
        ]);
    }

    public function storeRoom(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dormitory_id' => 'required|exists:dormitories,id',
            'room_number' => 'required|string|max:50',
            'capacity' => 'required|integer|min:0',
        ]);

        $room = Room::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Room created',
            'data' => $room,
        ], 201);
    }

    public function destroyRoom(Room $room): JsonResponse
    {
        $room->delete();

        return response()->json([
            'success' => true,
            'message' => 'Room deleted',
            'data' => null,
        ]);
    }

    // =========================================
    // BEDS + ASSIGNMENTS
    // =========================================
    public function beds(Request $request): JsonResponse
    {
        $beds = Bed::query()
            ->when($request->filled('room_id'), fn ($q) => $q->where('room_id', $request->integer('room_id')))
            ->when($request->filled('dormitory_id'), fn ($q) => $q->whereHas('room', fn ($q) => $q->where('dormitory_id', $request->integer('dormitory_id'))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->with(['room.dormitory:id,name'])
            ->with(['activeAssignment.student:id,full_name,student_number'])
            ->orderBy('room_id')
            ->orderBy('bed_number')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Beds retrieved',
            'data' => $beds,
        ]);
    }

    public function storeBed(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'bed_number' => 'required|string|max:50',
        ]);

        $bed = Bed::create($validated + ['status' => 'available']);

        return response()->json([
            'success' => true,
            'message' => 'Bed created',
            'data' => $bed,
        ], 201);
    }

    public function assignBed(Request $request, Bed $bed): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
        ]);

        if ($bed->status === 'occupied') {
            return response()->json([
                'success' => false,
                'message' => 'Bed is already occupied.',
            ], 422);
        }

        DB::transaction(function () use ($bed, $validated, $request) {
            BedAssignment::create([
                'bed_id' => $bed->id,
                'student_id' => $validated['student_id'],
                'assigned_at' => now(),
                'assigned_by' => $request->user()->id,
            ]);

            $bed->update(['status' => 'occupied']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Bed assigned successfully',
            'data' => $bed->fresh()->load('activeAssignment.student'),
        ]);
    }

    public function vacateBed(Request $request, Bed $bed): JsonResponse
    {
        $assignment = $bed->activeAssignment;

        if (! $assignment) {
            return response()->json([
                'success' => false,
                'message' => 'No active assignment for this bed.',
            ], 422);
        }

        DB::transaction(function () use ($assignment, $bed) {
            $assignment->update(['vacated_at' => now()]);
            $bed->update(['status' => 'available']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Bed vacated successfully',
            'data' => $bed->fresh(),
        ]);
    }

    // =========================================
    // ROLL CALL
    // =========================================
    public function rollCall(Request $request): JsonResponse
    {
        $rollCalls = RollCall::query()
            ->when($request->filled('date'), fn ($q) => $q->whereDate('date', $request->date('date')))
            ->when($request->filled('session'), fn ($q) => $q->where('session', $request->string('session')))
            ->when($request->filled('dormitory_id'), fn ($q) => $q->where('dormitory_id', $request->integer('dormitory_id')))
            ->with('dormitory:id,name')
            ->with('takenBy:id,full_name')
            ->orderByDesc('date')
            ->orderBy('session')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Roll calls retrieved',
            'data' => $rollCalls,
        ]);
    }

    public function storeRollCall(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dormitory_id' => 'required|exists:dormitories,id',
            'session' => 'required|in:morning,evening,night',
            'date' => 'required|date',
            'total_present' => 'required|integer|min:0',
            'total_absent' => 'required|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $rollCall = RollCall::updateOrCreate(
            [
                'dormitory_id' => $validated['dormitory_id'],
                'session' => $validated['session'],
                'date' => $validated['date'],
            ],
            $validated + ['taken_by' => $request->user()->id]
        );

        return response()->json([
            'success' => true,
            'message' => 'Roll call recorded',
            'data' => $rollCall->load('dormitory:id,name'),
        ], 201);
    }

    // =========================================
    // LEAVES (CHECK-IN / CHECK-OUT)
    // =========================================
    public function leaves(Request $request): JsonResponse
    {
        $today = now()->toDateString();

        $leaves = StudentLeave::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->boolean('overdue'), function ($q) use ($today) {
                $q->where('status', 'approved')
                    ->whereDate('end_date', '<', $today);
            })
            ->with(['student:id,full_name,student_number', 'approvedBy:id,full_name'])
            ->orderByDesc('start_date')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Student leaves retrieved',
            'data' => $leaves,
        ]);
    }

    public function storeLeave(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'type' => 'required|in:sick,home,emergency,other',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'destination' => 'nullable|string|max:150',
            'reason' => 'nullable|string',
        ]);

        $leave = StudentLeave::create($validated + [
            'status' => 'pending',
            'approved_by' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave request submitted',
            'data' => $leave->load('student:id,full_name'),
        ], 201);
    }

    public function approveLeave(Request $request, StudentLeave $leave): JsonResponse
    {
        $leave->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave approved',
            'data' => $leave->fresh()->load('student:id,full_name'),
        ]);
    }

    public function rejectLeave(Request $request, StudentLeave $leave): JsonResponse
    {
        $leave->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave rejected',
            'data' => $leave->fresh(),
        ]);
    }

    public function checkIn(Request $request, StudentLeave $leave): JsonResponse
    {
        $leave->update(['status' => 'completed']);

        return response()->json([
            'success' => true,
            'message' => 'Student checked in',
            'data' => $leave->fresh(),
        ]);
    }

    // =========================================
    // SICK BAY
    // =========================================
    public function sickBay(Request $request): JsonResponse
    {
        $records = SickBay::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->with('student:id,full_name,student_number')
            ->with('attendedBy:id,full_name')
            ->orderByDesc('admitted_at')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Sick bay records retrieved',
            'data' => $records,
        ]);
    }

    public function storeSickBay(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'complaint' => 'required|string',
            'treatment' => 'nullable|string',
            'medication' => 'nullable|string',
        ]);

        $record = SickBay::create($validated + [
            'admitted_at' => now(),
            'status' => 'admitted',
            'attended_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Student admitted to sick bay',
            'data' => $record->load('student:id,full_name'),
        ], 201);
    }

    public function dischargePatient(Request $request, SickBay $sickBay): JsonResponse
    {
        $sickBay->update([
            'status' => 'discharged',
            'discharged_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Patient discharged',
            'data' => $sickBay->fresh(),
        ]);
    }

    // =========================================
    // INCIDENTS
    // =========================================
    public function incidents(Request $request): JsonResponse
    {
        $incidents = Incident::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->string('severity')))
            ->with(['student:id,full_name', 'dormitory:id,name', 'reportedBy:id,full_name'])
            ->orderByDesc('incident_date')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Incidents retrieved',
            'data' => $incidents,
        ]);
    }

    public function storeIncident(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'nullable|exists:students,id',
            'dormitory_id' => 'nullable|exists:dormitories,id',
            'incident_date' => 'required|date',
            'category' => 'required|string|max:100',
            'description' => 'required|string',
            'severity' => 'required|in:low,medium,high,critical',
            'action_taken' => 'nullable|string',
        ]);

        $incident = Incident::create($validated + [
            'status' => 'open',
            'reported_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Incident recorded',
            'data' => $incident->load(['student:id,full_name', 'dormitory:id,name']),
        ], 201);
    }

    public function updateIncident(Request $request, Incident $incident): JsonResponse
    {
        $validated = $request->validate([
            'action_taken' => 'nullable|string',
            'status' => 'sometimes|in:open,investigating,resolved,closed',
        ]);

        $incident->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Incident updated',
            'data' => $incident->fresh(),
        ]);
    }

    // =========================================
    // MESSAGES TO PARENTS
    // =========================================
    public function messages(Request $request): JsonResponse
    {
        $messages = ParentMessage::where('sender_id', $request->user()->id)
            ->with(['parent:id,full_name', 'student:id,full_name'])
            ->orderByDesc('created_at')
            ->get();

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
            'message' => 'required|string',
        ]);

        $student = Student::with('parents')->findOrFail($validated['student_id']);
        $parent = $student->parents->first();

        if (! $parent) {
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
            'message' => $validated['message'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Message sent',
            'data' => $message,
        ], 201);
    }

    // =========================================
    // REPORTS
    // =========================================
    public function reports(Request $request): JsonResponse
    {
        $from = $request->date('from') ?? now()->subDays(30);
        $to = $request->date('to') ?? now();

        return response()->json([
            'success' => true,
            'message' => 'Reports retrieved',
            'data' => [
                'attendance' => RollCall::whereBetween('date', [$from, $to])
                    ->selectRaw('session, SUM(total_present) as present, SUM(total_absent) as absent')
                    ->groupBy('session')
                    ->get(),
                'sick_bay' => SickBay::whereBetween('admitted_at', [$from, $to])->count(),
                'incidents' => Incident::whereBetween('incident_date', [$from, $to])->count(),
                'leaves' => StudentLeave::whereBetween('start_date', [$from, $to])->count(),
                'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            ],
        ]);
    }

    // =========================================
    // DORMITORY INSPECTIONS (MPYA)
    // =========================================
    public function inspections(Request $request): JsonResponse
    {
        $inspections = DormitoryInspection::query()
            ->when($request->filled('dormitory_id'), fn ($q) => $q->where('dormitory_id', $request->integer('dormitory_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->with(['dormitory:id,name', 'inspector:id,full_name'])
            ->orderByDesc('inspection_date')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Inspections retrieved',
            'data' => $inspections,
        ]);
    }

    public function storeInspection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dormitory_id' => 'required|exists:dormitories,id',
            'inspection_date' => 'required|date',
            'hygiene_score' => 'required|integer|min:0|max:100',
            'cleanliness_score' => 'required|integer|min:0|max:100',
            'issues_found' => 'nullable|string',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:pending,passed,failed',
        ]);

        $avg = ($validated['hygiene_score'] + $validated['cleanliness_score']) / 2;
        $status = $validated['status'] ?? ($avg >= 70 ? 'passed' : 'failed');

        $inspection = DormitoryInspection::create($validated + [
            'status' => $status,
            'inspector_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inspection recorded',
            'data' => $inspection->load('dormitory:id,name'),
        ], 201);
    }
}