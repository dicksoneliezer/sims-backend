<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\StudentPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountantReportController extends Controller
{
    public function financial(Request $request): JsonResponse
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now();

        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        $invoiced = (float) Invoice::whereBetween('created_at', [$start, $end])->sum('amount');
        $collected = (float) StudentPayment::where('status', 'completed')->whereBetween('completed_at', [$start, $end])->sum('amount');
        $expenses = (float) Expense::whereBetween('expense_date', [$start, $end])->sum('amount');
        $outstanding = (float) Invoice::where('balance', '>', 0)->sum('balance');

        $methodBreakdown = StudentPayment::query()
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$start, $end])
            ->selectRaw('payment_method, count(*) as count, sum(amount) as total')
            ->groupBy('payment_method')
            ->orderBy('payment_method')
            ->get()
            ->map(fn ($row) => [
                'payment_method' => $row->payment_method,
                'count' => (int) $row->count,
                'total' => (float) $row->total,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Financial report generated',
            'data' => [
                'period' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ],
                'total_invoiced' => $invoiced,
                'total_collected' => $collected,
                'total_outstanding' => $outstanding,
                'total_expenses' => $expenses,
                'net_income' => round($collected - $expenses, 2),
                'payment_methods' => $methodBreakdown,
            ],
        ]);
    }
}
