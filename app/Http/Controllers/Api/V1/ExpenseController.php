<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $expenses = Expense::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('expense_number', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('paid_to', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('expense_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('expense_date', '<=', $request->date('to')))
            ->latest('expense_date')
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Expenses retrieved',
            'data' => ExpenseResource::collection($expenses->items()),
            'meta' => [
                'current_page' => $expenses->currentPage(),
                'per_page' => $expenses->perPage(),
                'total' => $expenses->total(),
                'last_page' => $expenses->lastPage(),
            ],
        ]);
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $data = $request->validated();
        unset($data['receipt']);

        if ($request->hasFile('receipt')) {
            $data['receipt'] = $request->file('receipt')->store('finance/receipts', 'public');
        }

        $data['expense_number'] = $this->nextExpenseNumber();
        $data['created_by'] = $request->user()->id;

        $expense = Expense::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Expense created',
            'data' => new ExpenseResource($expense),
        ], 201);
    }

    public function show(Expense $expense): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Expense retrieved',
            'data' => new ExpenseResource($expense),
        ]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): JsonResponse
    {
        $data = $request->validated();
        unset($data['receipt'], $data['remove_receipt']);

        if ($request->hasFile('receipt')) {
            $this->deleteReceipt($expense);
            $data['receipt'] = $request->file('receipt')->store('finance/receipts', 'public');
        } elseif ($request->boolean('remove_receipt')) {
            $this->deleteReceipt($expense);
            $data['receipt'] = null;
        }

        $expense->fill($data)->save();

        return response()->json([
            'success' => true,
            'message' => 'Expense updated',
            'data' => new ExpenseResource($expense->fresh()),
        ]);
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $this->deleteReceipt($expense);
        $expense->delete();

        return response()->json([
            'success' => true,
            'message' => 'Expense deleted',
            'data' => null,
        ]);
    }

    private function nextExpenseNumber(): string
    {
        $prefix = 'EXP-'.now()->format('Y').'-';
        $sequence = Expense::where('expense_number', 'like', $prefix.'%')->count() + 1;

        do {
            $number = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (Expense::where('expense_number', $number)->exists());

        return $number;
    }

    private function deleteReceipt(Expense $expense): void
    {
        if ($expense->receipt) {
            Storage::disk('public')->delete($expense->receipt);
        }
    }
}
