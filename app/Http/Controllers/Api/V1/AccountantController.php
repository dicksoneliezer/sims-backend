<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accountant\StoreAccountantRequest;
use App\Http\Requests\Accountant\UpdateAccountantRequest;
use App\Http\Resources\UserResource;
use App\Models\BankStatement;
use App\Models\BankTransaction;
use App\Models\ReconciliationReport;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SchoolFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $accountants = User::query()
            ->where('role', 'accountant')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('username', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('full_name')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Accountants retrieved',
            'data' => UserResource::collection($accountants->items()),
            'meta' => [
                'current_page' => $accountants->currentPage(),
                'per_page' => $accountants->perPage(),
                'total' => $accountants->total(),
                'last_page' => $accountants->lastPage(),
            ],
        ]);
    }

    public function store(StoreAccountantRequest $request): JsonResponse
    {
        $accountant = User::create($request->validated() + ['role' => 'accountant']);

        return response()->json([
            'success' => true,
            'message' => 'Accountant created',
            'data' => new UserResource($accountant),
        ], 201);
    }

    public function show(User $accountant): JsonResponse
    {
        $this->ensureAccountant($accountant);

        return response()->json([
            'success' => true,
            'message' => 'Accountant retrieved',
            'data' => new UserResource($accountant),
        ]);
    }

    public function update(UpdateAccountantRequest $request, User $accountant): JsonResponse
    {
        $this->ensureAccountant($accountant);
        $accountant->fill($request->validated())->save();

        return response()->json([
            'success' => true,
            'message' => 'Accountant updated',
            'data' => new UserResource($accountant->fresh()),
        ]);
    }

    public function destroy(User $accountant): JsonResponse
    {
        $this->ensureAccountant($accountant);

        $accountant->tokens()->delete();
        $accountant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Accountant deleted',
            'data' => null,
        ]);
    }

    private function ensureAccountant(User $accountant): void
    {
        abort_unless($accountant->role === 'accountant', 404, 'Accountant not found.');
    }

    // =========================================
    // BANK RECONCILIATION (MPYA)
    // =========================================
    public function importStatement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'statement_date' => 'required|date',
            'opening_balance' => 'required|numeric',
            'closing_balance' => 'required|numeric',
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $file = $request->file('file');
        $path = app(SchoolFileService::class)->store($file, 'bank-statements');

        $statement = BankStatement::create([
            'bank_name' => $validated['bank_name'],
            'account_number' => $validated['account_number'],
            'statement_date' => $validated['statement_date'],
            'opening_balance' => $validated['opening_balance'],
            'closing_balance' => $validated['closing_balance'],
            'file_path' => $path,
            'uploaded_by' => $request->user()->id,
        ]);

        // Parse CSV
        $rows = array_map('str_getcsv', file($file->getRealPath()));
        array_shift($rows); // Chukua header
        $imported = 0;

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                if (count($row) < 4) {
                    continue;
                }

                BankTransaction::create([
                    'bank_statement_id' => $statement->id,
                    'transaction_date' => $row[0] ?? now()->toDateString(),
                    'description' => $row[1] ?? '',
                    'reference' => $row[2] ?? null,
                    'debit' => is_numeric($row[3] ?? null) ? $row[3] : 0,
                    'credit' => is_numeric($row[4] ?? null) ? $row[4] : 0,
                    'balance' => is_numeric($row[5] ?? null) ? $row[5] : 0,
                    'is_matched' => false,
                ]);
                $imported++;
            }

            // Auto-match na transactions zilizopo
            $this->autoMatchTransactions($statement->id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Imported {$imported} transactions",
                'data' => $statement->load('transactions'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to import: '.$e->getMessage(),
            ], 500);
        }
    }

    public function reconciliation(Request $request): JsonResponse
    {
        $reports = ReconciliationReport::with('bankStatement')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $unmatched = BankTransaction::where('is_matched', false)
            ->orderByDesc('transaction_date')
            ->limit(100)
            ->get();

        $matched = BankTransaction::where('is_matched', true)
            ->orderByDesc('transaction_date')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'reports' => $reports,
                'unmatched_transactions' => $unmatched,
                'matched_transactions' => $matched,
                'summary' => [
                    'total_reports' => $reports->count(),
                    'unmatched_count' => $unmatched->count(),
                    'matched_count' => $matched->count(),
                ],
            ],
        ]);
    }

    private function autoMatchTransactions(int $statementId): void
    {
        $bankTransactions = BankTransaction::where('bank_statement_id', $statementId)
            ->where('is_matched', false)
            ->get();

        foreach ($bankTransactions as $bankTxn) {
            if (! $bankTxn->reference) {
                continue;
            }

            $match = Transaction::where('reference', $bankTxn->reference)->first();

            if ($match) {
                $bankTxn->update(['is_matched' => true]);
            }
        }
    }
}
