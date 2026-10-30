<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DebtController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $query = Invoice::query()
            ->with(['student:id,student_number,full_name,class', 'feeStructure:id,name,term,academic_year'])
            ->where('balance', '>', 0)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('student', function ($query) use ($search) {
                            $query->where('full_name', 'like', "%{$search}%")
                                ->orWhere('student_number', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('class'), fn ($query) => $query->whereHas('student', fn ($query) => $query->where('class', $request->string('class'))))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->boolean('overdue'), fn ($query) => $query->whereDate('due_date', '<', today()));

        $summary = [
            'total_outstanding' => (float) (clone $query)->sum('balance'),
            'invoice_count' => (clone $query)->count(),
            'overdue_count' => (clone $query)->whereDate('due_date', '<', today())->count(),
        ];

        $debts = $query
            ->orderBy('due_date')
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Debts retrieved',
            'data' => InvoiceResource::collection($debts->items()),
            'summary' => $summary,
            'meta' => [
                'current_page' => $debts->currentPage(),
                'per_page' => $debts->perPage(),
                'total' => $debts->total(),
                'last_page' => $debts->lastPage(),
            ],
        ]);
    }
}
