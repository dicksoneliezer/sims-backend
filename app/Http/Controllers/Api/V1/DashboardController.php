<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Attendance;
use App\Models\BehaviorRecord;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\StudentPayment;
use App\Models\SyllabusProgress;
use App\Models\SyllabusTopic;
use App\Models\TeacherNote;
use App\Models\TeacherSubject;
use App\Models\User;
use App\Services\TeacherAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly TeacherAccessService $teacherAccess) {}

    private const MODULES = [
        'super_admin' => ['Users', 'Students', 'Teachers', 'Parents', 'Accountants', 'Subjects', 'Timetables', 'Fee Structure', 'Finance', 'Inventory', 'Reports', 'Settings'],
        'system_admin' => ['Settings', 'System Logs', 'Backups', 'Academic Calendar', 'Departments'],
        'academic_teacher' => ['Subjects', 'Exams', 'Syllabus', 'Results', 'Timetables'],
        'teacher' => ['Students', 'Marks', 'Attendance', 'Notes', 'Behavior', 'Timetable'],
        'accountant' => ['Invoices', 'Payments', 'Expenses', 'Transactions', 'Debts', 'Reports'],
        'store_keeper' => ['Assets', 'Inventory', 'Stock', 'Depreciation', 'Maintenance'],
        'matron' => ['Dormitories', 'Rooms', 'Beds', 'Roll Call', 'Check In/Out', 'Sick Bay', 'Incidents', 'Messages'],
        'student' => ['Results', 'Attendance', 'Notes', 'Timetable', 'Certificates'],
        'parent' => ['Children', 'Payments', 'Balance', 'History', 'Timetable', 'Messages'],
    ];

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = [
            'user' => new UserResource($user),
            'modules' => self::MODULES[$user->role] ?? [],
        ];

        if (in_array($user->role, ['super_admin', 'system_admin'], true)) {
            $data['stats'] = [
                'total_users' => User::count(),
                'active_users' => User::where('is_active', true)->count(),
                'total_students' => Student::count(),
                'active_students' => Student::where('is_active', true)->count(),
                'by_role' => User::query()
                    ->selectRaw('role, count(*) as total')
                    ->groupBy('role')
                    ->pluck('total', 'role'),
            ];
        }

        if ($user->role === 'accountant') {
            $data['stats'] = [
                'total_invoiced' => (float) Invoice::sum('amount'),
                'total_collected' => (float) StudentPayment::where('status', 'completed')->sum('amount'),
                'total_outstanding' => (float) Invoice::where('balance', '>', 0)->sum('balance'),
                'total_expenses' => (float) Expense::sum('amount'),
                'pending_payments' => StudentPayment::where('status', 'pending')->count(),
                'overdue_invoices' => Invoice::where('balance', '>', 0)->whereDate('due_date', '<', today())->count(),
            ];
        }

        if ($user->role === 'parent') {
            $childIds = $user->children()->pluck('students.id');

            $data['stats'] = [
                'children_count' => $childIds->count(),
                'total_outstanding' => (float) Invoice::whereIn('student_id', $childIds)->sum('balance'),
                'pending_payments' => StudentPayment::whereIn('student_id', $childIds)->where('status', 'pending')->count(),
                'total_paid' => (float) StudentPayment::whereIn('student_id', $childIds)->where('status', 'completed')->sum('amount'),
                'unread_notes' => TeacherNote::whereIn('student_id', $childIds)->where('is_read', false)->count(),
                'average_marks' => ExamResult::whereIn('student_id', $childIds)->avg('marks'),
            ];
        }

        if ($user->role === 'teacher') {
            $classIds = $this->teacherAccess->classIds($user);
            $studentIds = $this->teacherAccess->students($user)->pluck('id');

            $data['stats'] = [
                'classes' => $classIds->count(),
                'subjects' => TeacherSubject::where('teacher_id', $user->id)->distinct('subject_id')->count('subject_id'),
                'students' => $studentIds->count(),
                'attendance_today' => Attendance::whereIn('student_id', $studentIds)->whereDate('date', today())->count(),
                'marks_entered' => ExamResult::whereIn('student_id', $studentIds)->where('entered_by', $user->id)->count(),
                'notes' => TeacherNote::where('teacher_id', $user->id)->count(),
                'behavior_records' => BehaviorRecord::where('teacher_id', $user->id)->count(),
                'active_exams' => Exam::where('status', 'active')->count(),
            ];
        }

        if ($user->role === 'academic_teacher') {
            $data['stats'] = [
                'exams' => Exam::count(),
                'active_exams' => Exam::where('status', 'active')->count(),
                'results' => ExamResult::count(),
                'students' => Student::where('is_active', true)->count(),
                'syllabus_topics' => SyllabusTopic::count(),
                'completed_progress' => SyllabusProgress::where('status', 'completed')->count(),
                'average_marks' => ExamResult::avg('marks'),
            ];
        }

        if ($user->role === 'student') {
            $student = Student::where('user_id', $user->id)->first();
            $studentIds = $student ? collect([$student->id]) : collect();

            $attendance = Attendance::whereIn('student_id', $studentIds)->get();
            $data['stats'] = [
                'results' => ExamResult::whereIn('student_id', $studentIds)->count(),
                'average_marks' => ExamResult::whereIn('student_id', $studentIds)->avg('marks'),
                'attendance_rate' => $attendance->isEmpty()
                    ? null
                    : round($attendance->whereIn('status', ['present', 'late'])->count() / $attendance->count() * 100, 2),
                'unread_notes' => TeacherNote::whereIn('student_id', $studentIds)->where('is_read', false)->count(),
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Dashboard loaded',
            'data' => $data,
        ]);
    }
}
