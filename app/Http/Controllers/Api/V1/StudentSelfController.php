<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Http\Resources\TeacherNoteResource;
use App\Models\SchoolCalendar;
use App\Models\Student;
use App\Models\TeacherNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentSelfController extends Controller
{
    public function profile(Request $request): JsonResponse
    {
        $student = $this->studentFor($request);

        return response()->json([
            'success' => true,
            'message' => 'Student profile retrieved',
            'data' => new StudentResource($student),
        ]);
    }

    public function results(Request $request, StudentController $studentController): JsonResponse
    {
        return $studentController->results($request, $this->studentFor($request));
    }

    public function attendance(Request $request, StudentController $studentController): JsonResponse
    {
        return $studentController->attendance($request, $this->studentFor($request));
    }

    public function notes(Request $request): JsonResponse
    {
        $notes = $this->studentFor($request)->teacherNotes()
            ->with('teacher:id,full_name')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Notes retrieved',
            'data' => TeacherNoteResource::collection($notes),
        ]);
    }

    public function readNote(Request $request, TeacherNote $note): JsonResponse
    {
        $student = $this->studentFor($request);
        abort_unless($note->student_id === $student->id, 403, 'This note is not linked to your account.');

        $note->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Note marked as read',
            'data' => new TeacherNoteResource($note->load('teacher:id,full_name')),
        ]);
    }

    // =========================================
    // ASSIGNMENTS (kutoka teacher_notes zenye type 'assignment' au 'general')
    // =========================================
    public function assignments(Request $request): JsonResponse
    {
        $student = $this->studentFor($request);

        $notes = $student->teacherNotes()
            ->whereIn('type', ['assignment', 'general', 'academic'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($note) {
                return [
                    'id' => $note->id,
                    'title' => $note->title,
                    'description' => $note->note,
                    'subject' => '—',
                    'due_date' => null,
                    'status' => $note->is_read ? 'submitted' : 'pending',
                    'submitted_at' => $note->is_read ? $note->updated_at : null,
                    'grade' => null,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Assignments retrieved',
            'data' => $notes,
        ]);
    }

    // =========================================
    // ANNOUNCEMENTS (kutoka school_calendar)
    // =========================================
    public function announcements(Request $request): JsonResponse
    {
        $events = SchoolCalendar::query()
            ->orderByDesc('start_date')
            ->limit(20)
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'body' => $event->description ?? '',
                    'category' => $event->type ?? 'general',
                    'published_at' => $event->created_at ?? $event->start_date,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Announcements retrieved',
            'data' => $events,
        ]);
    }

    // =========================================
    // CERTIFICATES (kwa sasa tupu — table haipo)
    // =========================================
    public function certificates(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Certificates retrieved',
            'data' => [],
        ]);
    }

    // =========================================
    // REPORT CARD (kutoka exam_results + attendance)
    // =========================================
    public function reportCard(Request $request): JsonResponse
    {
        $student = $this->studentFor($request);

        $results = $student->examResults()
            ->with('subject:id,name,code')
            ->orderByDesc('created_at')
            ->get();

        $average = $results->avg('marks');

        $attendanceTotal = $student->attendances()->count();
        $attendancePresent = $student->attendances()->where('status', 'present')->count();
        $attendanceRate = $attendanceTotal > 0
            ? round(($attendancePresent / $attendanceTotal) * 100)
            : 0;

        return response()->json([
            'success' => true,
            'message' => 'Report card retrieved',
            'data' => [
                'student_name' => $student->full_name,
                'student_number' => $student->student_number,
                'class' => $student->schoolClass?->name ?? $student->class,
                'term' => 'Current Term',
                'academic_year' => date('Y'),
                'results' => $results->map(fn ($r) => [
                    'subject' => $r->subject?->name ?? '—',
                    'marks' => $r->marks,
                    'grade' => $r->grade,
                    'remarks' => $r->remarks,
                ]),
                'average' => $average ? round($average, 1) : 0,
                'attendance_rate' => $attendanceRate,
                'published_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function studentFor(Request $request): Student
    {
        $student = Student::where('user_id', $request->user()->id)->first();

        abort_if($student === null, 404, 'No student record is linked to your account.');

        return $student;
    }
}