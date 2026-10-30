<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\StudentPayment;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminFinanceController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $invoiced = (float) Invoice::sum('amount');
        $collected = (float) StudentPayment::where('status', 'completed')->sum('amount');
        $expenses = (float) Expense::sum('amount');
        $outstanding = (float) Invoice::where('balance', '>', 0)->sum('balance');

        $methods = StudentPayment::query()
            ->where('status', 'completed')
            ->selectRaw('payment_method, count(*) as count, sum(amount) as total')
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'payment_method' => $row->payment_method,
                'count' => (int) $row->count,
                'total' => (float) $row->total,
            ]);

        $recentTransactions = Transaction::query()
            ->with(['student:id,student_number,full_name,class', 'invoice:id,invoice_number'])
            ->latest('id')
            ->limit(8)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Finance overview retrieved',
            'data' => [
                'total_invoiced' => $invoiced,
                'total_collected' => $collected,
                'total_outstanding' => $outstanding,
                'total_expenses' => $expenses,
                'net_income' => round($collected - $expenses, 2),
                'collection_rate' => $invoiced > 0 ? round(($collected / $invoiced) * 100, 1) : 0,
                'pending_payments' => StudentPayment::where('status', 'pending')->count(),
                'overdue_invoices' => Invoice::where('balance', '>', 0)->whereDate('due_date', '<', today())->count(),
                'payment_methods' => $methods,
                'recent_transactions' => $recentTransactions->map(fn ($transaction) => [
                    'id' => $transaction->id,
                    'transaction_ref' => $transaction->transaction_ref,
                    'type' => $transaction->type,
                    'amount' => (float) $transaction->amount,
                    'status' => $transaction->status,
                    'payment_method' => $transaction->payment_method,
                    'student' => $transaction->student ? [
                        'full_name' => $transaction->student->full_name,
                        'student_number' => $transaction->student->student_number,
                    ] : null,
                    'invoice_number' => $transaction->invoice?->invoice_number,
                    'created_at' => $transaction->created_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }
}
