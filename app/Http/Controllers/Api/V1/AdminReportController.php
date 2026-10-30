<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Attendance;
use App\Models\ExamResult;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\LowStockAlert;
use App\Models\Student;
use App\Models\StudentPayment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReportController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now();
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        $attendance = Attendance::query()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $attendanceTotal = (int) $attendance->sum();

        $results = ExamResult::query()
            ->selectRaw('term, academic_year, count(*) as entries, avg(marks) as average_marks')
            ->groupBy('term', 'academic_year')
            ->orderByDesc('academic_year')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'term' => $row->term,
                'academic_year' => $row->academic_year,
                'entries' => (int) $row->entries,
                'average_marks' => round((float) $row->average_marks, 1),
            ]);

        $classBreakdown = Student::query()
            ->where('is_active', true)
            ->selectRaw('class, count(*) as total')
            ->groupBy('class')
            ->orderBy('class')
            ->pluck('total', 'class')
            ->map(fn ($total, $class) => ['class' => $class, 'total' => (int) $total])
            ->values();

        $genderBreakdown = Student::query()
            ->where('is_active', true)
            ->selectRaw('gender, count(*) as total')
            ->groupBy('gender')
            ->pluck('total', 'gender');

        return response()->json([
            'success' => true,
            'message' => 'Reports overview generated',
            'data' => [
                'period' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ],
                'enrollment' => [
                    'total_students' => Student::count(),
                    'active_students' => Student::where('is_active', true)->count(),
                    'new_students' => Student::whereBetween('as_of_date', [$start->toDateString(), $end->toDateString()])->count(),
                    'by_class' => $classBreakdown,
                    'by_gender' => $genderBreakdown,
                ],
                'attendance' => [
                    'total_records' => $attendanceTotal,
                    'present' => (int) ($attendance['present'] ?? 0),
                    'absent' => (int) ($attendance['absent'] ?? 0),
                    'late' => (int) ($attendance['late'] ?? 0),
                    'attendance_rate' => $attendanceTotal > 0 ? round(((int) ($attendance['present'] ?? 0) / $attendanceTotal) * 100, 1) : 0,
                ],
                'academics' => [
                    'results' => $results,
                ],
                'finance' => [
                    'invoiced' => (float) Invoice::whereBetween('created_at', [$start, $end])->sum('amount'),
                    'collected' => (float) StudentPayment::where('status', 'completed')->whereBetween('completed_at', [$start, $end])->sum('amount'),
                    'expenses' => (float) Expense::whereBetween('expense_date', [$start, $end])->sum('amount'),
                    'outstanding' => (float) Invoice::where('balance', '>', 0)->sum('balance'),
                ],
                'inventory' => [
                    'total_assets' => Asset::count(),
                    'asset_value' => (float) Asset::sum('current_value'),
                    'total_items' => InventoryItem::count(),
                    'stock_value' => (float) InventoryItem::selectRaw('COALESCE(SUM(quantity * unit_price), 0) as total')->value('total'),
                    'low_stock_items' => InventoryItem::whereColumn('quantity', '<=', 'reorder_level')->count(),
                    'open_alerts' => LowStockAlert::where('is_resolved', false)->count(),
                ],
                'users' => [
                    'total' => User::count(),
                    'active' => User::where('is_active', true)->count(),
                    'by_role' => User::query()->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role'),
                ],
            ],
        ]);
    }
}
