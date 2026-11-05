<?php

use App\Http\Controllers\Api\V1\AcademicAnnouncementController; // <-- MPYA
use App\Http\Controllers\Api\V1\AcademicExamController;
use App\Http\Controllers\Api\V1\AcademicResultController;
use App\Http\Controllers\Api\V1\AcademicSyllabusController;
use App\Http\Controllers\Api\V1\AccountantController;
use App\Http\Controllers\Api\V1\AccountantPaymentController;
use App\Http\Controllers\Api\V1\AccountantReportController;
use App\Http\Controllers\Api\V1\AdminFinanceController;
use App\Http\Controllers\Api\V1\AdminInventoryController;
use App\Http\Controllers\Api\V1\AdminReportController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\CalendarController;
use App\Http\Controllers\Api\V1\ControlNumberController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DebtController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\FeeStructureController;
use App\Http\Controllers\Api\V1\GradingSystemController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\MatronController;
use App\Http\Controllers\Api\V1\ParentChildController;
use App\Http\Controllers\Api\V1\ParentController;
use App\Http\Controllers\Api\V1\ParentPaymentController;
use App\Http\Controllers\Api\V1\ParentSchoolController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SchoolClassController;
use App\Http\Controllers\Api\V1\SchoolController;
use App\Http\Controllers\Api\V1\SchoolFileController;
use App\Http\Controllers\Api\V1\SchoolSettingController;
use App\Http\Controllers\Api\V1\StoreKeeperController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\StudentSelfController;
use App\Http\Controllers\Api\V1\SubjectController;
use App\Http\Controllers\Api\V1\TeacherAssignmentController;
use App\Http\Controllers\Api\V1\TeacherAttendanceController;
use App\Http\Controllers\Api\V1\TeacherBehaviorController;
use App\Http\Controllers\Api\V1\TeacherController;
use App\Http\Controllers\Api\V1\TeacherMarkController;
use App\Http\Controllers\Api\V1\TeacherNoteController;
use App\Http\Controllers\Api\V1\TimetableController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'success' => true,
    'message' => 'SIMS API is running',
    'data' => ['version' => '1.0.0'],
]));

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    });

    Route::middleware(['auth:sanctum', 'school.context', 'throttle:60,1'])->group(function () {
        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::get('me', [AuthController::class, 'me']);
        });

        Route::prefix('system/schools')->middleware('role:system_admin')->group(function (): void {
            Route::get('/', [SchoolController::class, 'index']);
            Route::post('/', [SchoolController::class, 'store']);
            Route::patch('{school}', [SchoolController::class, 'update']);
        });

        Route::get('school-files/avatars/{userId}', [SchoolFileController::class, 'avatar'])->whereNumber('userId')->name('school-files.avatar');
        Route::get('school-files/students/{student}/photo', [SchoolFileController::class, 'studentPhoto'])->name('school-files.student');
        Route::get('school-files/expenses/{expense}/receipt', [SchoolFileController::class, 'receipt'])->middleware('role:super_admin,accountant')->name('school-files.receipt');

        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::post('profile/avatar', [ProfileController::class, 'updateAvatar']);
        Route::post('profile/password', [ProfileController::class, 'changePassword']);

        Route::prefix('admin')->middleware('role:super_admin')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'show']);
            Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive']);
            Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);
            Route::apiResource('users', UserController::class);

            Route::get('students/{student}/results', [StudentController::class, 'results']);
            Route::get('students/{student}/attendance', [StudentController::class, 'attendance']);
            Route::get('students/{student}/invoices', [StudentController::class, 'invoices']);
            Route::get('students/{student}/transactions', [StudentController::class, 'transactions']);
            Route::apiResource('students', StudentController::class);

            Route::apiResource('parents', ParentController::class);
            Route::apiResource('accountants', AccountantController::class);
            Route::apiResource('fee-structure', FeeStructureController::class)->parameters(['fee-structure' => 'feeStructure']);

            Route::apiResource('subjects', SubjectController::class);
            Route::apiResource('classes', SchoolClassController::class)->parameters(['classes' => 'schoolClass']);

            Route::get('teachers/{teacher}/classes', [TeacherController::class, 'classes']);
            Route::get('teachers/{teacher}/subjects', [TeacherController::class, 'subjects']);
            Route::apiResource('teachers', TeacherController::class);

            Route::get('teacher-classes', [TeacherAssignmentController::class, 'classes']);
            Route::post('teacher-classes', [TeacherAssignmentController::class, 'storeClass']);
            Route::delete('teacher-classes/{teacherClass}', [TeacherAssignmentController::class, 'destroyClass']);

            Route::get('teacher-subjects', [TeacherAssignmentController::class, 'subjects']);
            Route::post('teacher-subjects', [TeacherAssignmentController::class, 'storeSubject']);
            Route::delete('teacher-subjects/{teacherSubject}', [TeacherAssignmentController::class, 'destroySubject']);

            Route::apiResource('timetables', TimetableController::class);

            Route::prefix('finance')->group(function () {
                Route::get('overview', [AdminFinanceController::class, 'overview']);
                Route::get('invoices', [InvoiceController::class, 'index']);
                Route::get('transactions', [TransactionController::class, 'index']);
                Route::get('expenses', [ExpenseController::class, 'index']);
                Route::get('debts', [DebtController::class, 'index']);
            });

            Route::prefix('inventory')->group(function () {
                Route::get('overview', [AdminInventoryController::class, 'overview']);
                Route::get('assets', [AdminInventoryController::class, 'assets']);
                Route::get('items', [AdminInventoryController::class, 'items']);
                Route::get('stock', [AdminInventoryController::class, 'stock']);
                Route::get('alerts', [AdminInventoryController::class, 'alerts']);
                Route::get('maintenance', [AdminInventoryController::class, 'maintenance']);
            });

            Route::get('reports/overview', [AdminReportController::class, 'overview']);

            Route::get('settings/school', [SchoolSettingController::class, 'show']);
            Route::put('settings/school', [SchoolSettingController::class, 'update']);
            Route::apiResource('settings/grading', GradingSystemController::class)->parameters(['grading' => 'gradingSystem']);
            Route::apiResource('settings/calendar', CalendarController::class)->parameters(['calendar' => 'calendarEvent']);
        });

        Route::prefix('system')->middleware('role:system_admin')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'show']);
            Route::get('students', [StudentController::class, 'index']);
            Route::get('students/{student}', [StudentController::class, 'show']);
        });

        // =========================================
        // ACADEMIC ROUTES (MPYA)
        // =========================================
        Route::prefix('academic')->middleware('role:academic_teacher')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'show']);
            Route::get('students', [StudentController::class, 'index']);
            Route::get('students/{student}', [StudentController::class, 'show']);
            Route::get('students/{student}/results', [StudentController::class, 'results']);
            Route::get('students/{student}/attendance', [StudentController::class, 'attendance']);

            Route::apiResource('subjects', SubjectController::class);
            Route::get('classes', [SchoolClassController::class, 'index']);
            Route::get('teachers', [TeacherController::class, 'index']);
            Route::get('teachers/{teacher}', [TeacherController::class, 'show']);
            Route::get('timetable', [TimetableController::class, 'academic']);
            Route::get('master-timetable', [TimetableController::class, 'academic']);

            Route::get('exams/{exam}/results', [AcademicExamController::class, 'results']);
            Route::apiResource('exams', AcademicExamController::class);

            Route::get('results-analysis', [AcademicResultController::class, 'analysis']);
            Route::get('results', [AcademicResultController::class, 'index']);
            Route::post('results', [AcademicResultController::class, 'store']);
            Route::put('results/{result}', [AcademicResultController::class, 'update']);
            Route::delete('results/{result}', [AcademicResultController::class, 'destroy']);
            Route::get('student-progress/{student}', [AcademicResultController::class, 'studentProgress']);

            Route::get('syllabus-progress', [AcademicSyllabusController::class, 'progressIndex']);
            Route::post('syllabus-progress', [AcademicSyllabusController::class, 'saveProgress']);
            Route::apiResource('syllabus', AcademicSyllabusController::class)->parameters(['syllabus' => 'syllabusTopic']);

            // MPYA
            Route::get('announcements', [AcademicAnnouncementController::class, 'index']);
            Route::post('announcements', [AcademicAnnouncementController::class, 'store']);

            // MPYA - Teacher-Subject Assignments (kwa ajili ya Subject Allocation)
            Route::get('teacher-subjects', [TeacherAssignmentController::class, 'subjects']);
            Route::post('teacher-subjects', [TeacherAssignmentController::class, 'storeSubject']);
            Route::delete('teacher-subjects/{teacherSubject}', [TeacherAssignmentController::class, 'destroySubject']);
        });

        // TEACHER ROUTES
        Route::prefix('teacher')->middleware('role:teacher')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'show']);
            Route::get('students', [StudentController::class, 'index']);
            Route::get('students/{student}', [StudentController::class, 'show']);
            Route::get('students/{student}/results', [StudentController::class, 'results']);
            Route::get('students/{student}/attendance', [StudentController::class, 'attendance']);
            Route::get('classes', [TeacherController::class, 'myClasses']);
            Route::get('subjects', [TeacherController::class, 'mySubjects']);
            Route::get('timetable', [TimetableController::class, 'teacher']);

            Route::get('attendance/report', [TeacherAttendanceController::class, 'report']);
            Route::get('attendance', [TeacherAttendanceController::class, 'index']);
            Route::post('attendance', [TeacherAttendanceController::class, 'store']);

            Route::get('marks', [TeacherMarkController::class, 'index']);
            Route::post('marks', [TeacherMarkController::class, 'store']);
            Route::put('marks/{result}', [TeacherMarkController::class, 'update']);
            Route::delete('marks/{result}', [TeacherMarkController::class, 'destroy']);
            Route::get('class-results', [TeacherMarkController::class, 'classResults']);

            Route::get('notes', [TeacherNoteController::class, 'index']);
            Route::post('notes', [TeacherNoteController::class, 'store']);
            Route::put('notes/{note}', [TeacherNoteController::class, 'update']);
            Route::delete('notes/{note}', [TeacherNoteController::class, 'destroy']);

            Route::get('behavior', [TeacherBehaviorController::class, 'index']);
            Route::post('behavior', [TeacherBehaviorController::class, 'store']);
            Route::put('behavior/{behavior}', [TeacherBehaviorController::class, 'update']);
            Route::delete('behavior/{behavior}', [TeacherBehaviorController::class, 'destroy']);

            Route::get('messages', [TeacherController::class, 'messages']);
            Route::post('messages', [TeacherController::class, 'sendMessage']);
        });

        // =========================================
        // ACCOUNTANT ROUTES (UPDATED)
        // =========================================
        Route::prefix('accountant')->middleware('role:accountant')->group(function () {
            Route::get('billing', [BillingController::class, 'report']);
            Route::get('billing/fee-structures', [BillingController::class, 'feeStructures']);
            Route::post('billing/transfer-credit', [BillingController::class, 'transfer']);
            Route::post('control-numbers/{payment}/confirm', [BillingController::class, 'confirm']);
            Route::get('dashboard', [DashboardController::class, 'show']);
            Route::get('students', [StudentController::class, 'index']);
            Route::get('students/{student}', [StudentController::class, 'show']);
            Route::get('students/{student}/invoices', [StudentController::class, 'invoices']);
            Route::get('students/{student}/transactions', [StudentController::class, 'transactions']);
            Route::get('classes', [SchoolClassController::class, 'index']);

            Route::post('invoices/generate', [InvoiceController::class, 'generate']);
            Route::apiResource('invoices', InvoiceController::class);
            Route::post('payments', [AccountantPaymentController::class, 'store']);
            Route::get('transactions', [TransactionController::class, 'index']);

            Route::get('expenses', [ExpenseController::class, 'index']);
            Route::post('expenses', [ExpenseController::class, 'store']);
            Route::get('expenses/{expense}', [ExpenseController::class, 'show']);
            Route::put('expenses/{expense}', [ExpenseController::class, 'update']);
            Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy']);

            Route::get('debts', [DebtController::class, 'index']);
            Route::get('control-numbers', [ControlNumberController::class, 'index']);
            Route::post('control-numbers', [ControlNumberController::class, 'store']);
            Route::get('reports', [AccountantReportController::class, 'financial']);
            Route::get('reports/financial', [AccountantReportController::class, 'financial']);

            // MPYA - Bank Reconciliation
            Route::post('importstatement', [AccountantController::class, 'importStatement']);
            Route::get('reconciliation', [AccountantController::class, 'reconciliation']);
        });

        // =========================================
        // STORE KEEPER ROUTES (UPDATED)
        // =========================================
        Route::prefix('storekeeper')->middleware('role:store_keeper')->group(function () {
            Route::get('dashboard', [StoreKeeperController::class, 'dashboard']);
            Route::get('assets', [StoreKeeperController::class, 'assets']);
            Route::post('assets', [StoreKeeperController::class, 'storeAsset']);
            Route::get('inventory', [StoreKeeperController::class, 'inventory']);
            Route::post('inventory', [StoreKeeperController::class, 'storeInventory']);
            Route::get('stock', [StoreKeeperController::class, 'stock']);
            Route::get('maintenance', [StoreKeeperController::class, 'maintenance']);

            // MPYA
            Route::get('alerts', [StoreKeeperController::class, 'alerts']);
            Route::get('reports', [StoreKeeperController::class, 'reports']);
            Route::get('reports/assets', [StoreKeeperController::class, 'assetReport']);
        });

        // =========================================
        // MATRON ROUTES (UPDATED — Awamu 1)
        // =========================================
        Route::prefix('matron')->middleware('role:matron')->group(function () {
            // Dashboard
            Route::get('dashboard', [MatronController::class, 'dashboard']);

            // Students (kwa bed assignment)
            Route::get('students', [MatronController::class, 'students']);

            // Dormitories
            Route::get('dormitories', [MatronController::class, 'dormitories']);
            Route::post('dormitories', [MatronController::class, 'storeDormitory']);
            Route::put('dormitories/{dormitory}', [MatronController::class, 'updateDormitory']);
            Route::delete('dormitories/{dormitory}', [MatronController::class, 'destroyDormitory']);

            // Rooms
            Route::get('rooms', [MatronController::class, 'rooms']);
            Route::post('rooms', [MatronController::class, 'storeRoom']);
            Route::delete('rooms/{room}', [MatronController::class, 'destroyRoom']);

            // Beds + Assignments
            Route::get('beds', [MatronController::class, 'beds']);
            Route::post('beds', [MatronController::class, 'storeBed']);
            Route::post('beds/{bed}/assign', [MatronController::class, 'assignBed']);
            Route::post('beds/{bed}/vacate', [MatronController::class, 'vacateBed']);

            // Roll Call
            Route::get('roll-call', [MatronController::class, 'rollCall']);
            Route::post('roll-call', [MatronController::class, 'storeRollCall']);

            // Leaves (Check-in / Check-out)
            Route::get('leaves', [MatronController::class, 'leaves']);
            Route::post('leaves', [MatronController::class, 'storeLeave']);
            Route::put('leaves/{leave}/approve', [MatronController::class, 'approveLeave']);
            Route::put('leaves/{leave}/reject', [MatronController::class, 'rejectLeave']);
            Route::put('leaves/{leave}/check-in', [MatronController::class, 'checkIn']);

            // Sick Bay
            Route::get('sick-bay', [MatronController::class, 'sickBay']);
            Route::post('sick-bay', [MatronController::class, 'storeSickBay']);
            Route::put('sick-bay/{sickBay}/discharge', [MatronController::class, 'dischargePatient']);

            // Incidents
            Route::get('incidents', [MatronController::class, 'incidents']);
            Route::post('incidents', [MatronController::class, 'storeIncident']);
            Route::put('incidents/{incident}', [MatronController::class, 'updateIncident']);

            // Messages to Parents
            Route::get('messages', [MatronController::class, 'messages']);
            Route::post('messages', [MatronController::class, 'sendMessage']);

            // Reports
            Route::get('reports', [MatronController::class, 'reports']);

            // Dormitory Inspections (MPYA)
            Route::get('inspections', [MatronController::class, 'inspections']);
            Route::post('inspections', [MatronController::class, 'storeInspection']);
        });

        // STUDENT ROUTES
        Route::prefix('student')->middleware('role:student')->group(function () {
            Route::get('billing', [BillingController::class, 'student']);
            Route::post('billing/control-number', [BillingController::class, 'studentControlNumber']);
            Route::get('dashboard', [DashboardController::class, 'show']);
            Route::get('profile', [StudentSelfController::class, 'profile']);
            Route::get('results', [StudentSelfController::class, 'results']);
            Route::get('attendance', [StudentSelfController::class, 'attendance']);
            Route::get('notes', [StudentSelfController::class, 'notes']);
            Route::patch('notes/{note}/read', [StudentSelfController::class, 'readNote']);
            Route::get('timetable', [TimetableController::class, 'student']);

            Route::get('assignments', [StudentSelfController::class, 'assignments']);
            Route::get('announcements', [StudentSelfController::class, 'announcements']);
            Route::get('certificates', [StudentSelfController::class, 'certificates']);
            Route::get('report-card', [StudentSelfController::class, 'reportCard']);
        });

        Route::prefix('parent')->middleware('role:parent')->group(function () {
            Route::post('billing/transfer-credit', [BillingController::class, 'transfer']);
            Route::get('schools', [ParentSchoolController::class, 'index']);
            Route::get('schools/{school}', [ParentSchoolController::class, 'show'])->whereNumber('school');
            Route::get('dashboard', [DashboardController::class, 'show']);
            Route::get('children', [ParentChildController::class, 'index']);
            Route::get('children/{student}', [ParentChildController::class, 'show']);
            Route::get('children/{student}/results', [ParentChildController::class, 'results']);
            Route::get('children/{student}/attendance', [ParentChildController::class, 'attendance']);
            Route::get('children/{student}/notes', [ParentChildController::class, 'notes']);
            Route::get('children/{student}/timetable', [TimetableController::class, 'child']);
            Route::get('children/{student}/invoices', [ParentChildController::class, 'invoices']);
            Route::get('children/{student}/payments', [ParentChildController::class, 'payments']);

            Route::get('announcements', fn () => response()->json(['success' => true, 'data' => []]));
            Route::get('messages', fn () => response()->json(['success' => true, 'data' => []]));
            Route::get('gallery', fn () => response()->json(['success' => true, 'data' => []]));
            Route::get('documents', fn () => response()->json(['success' => true, 'data' => []]));

            Route::prefix('payments')->middleware('throttle:10,1,parent-payments')->group(function () {
                Route::get('/', [ParentPaymentController::class, 'index']);
                Route::post('control-number', [ParentPaymentController::class, 'generateControlNumber']);
                Route::get('history', [ParentPaymentController::class, 'history']);
                Route::get('balance', [ParentPaymentController::class, 'balance']);
                Route::get('methods', [ParentPaymentController::class, 'methods']);
                Route::get('status/{controlNumber}', [ParentPaymentController::class, 'status']);
                Route::get('{payment}/receipt', [ParentPaymentController::class, 'receipt'])->whereNumber('payment');
                Route::post('{payment}/pay', [ParentPaymentController::class, 'pay'])->whereNumber('payment');
                Route::post('{payment}/cancel', [ParentPaymentController::class, 'cancel'])->whereNumber('payment');
                Route::get('{payment}', [ParentPaymentController::class, 'show'])->whereNumber('payment');
            });
        });
    });
});
