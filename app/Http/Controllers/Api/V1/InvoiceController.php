<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\GenerateInvoicesRequest;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Student;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $invoices = Invoice::query()
            ->with(['student:id,student_number,full_name,class', 'feeStructure:id,name,term,academic_year'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('invoice_number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('student', function ($query) use ($search) {
                            $query->where('full_name', 'like', "%{$search}%")
                                ->orWhere('student_number', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('student_id'), fn ($query) => $query->where('student_id', $request->integer('student_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Invoices retrieved',
            'data' => InvoiceResource::collection($invoices->items()),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
                'last_page' => $invoices->lastPage(),
            ],
        ]);
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoiceService->createInvoice($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Invoice created',
            'data' => new InvoiceResource($invoice->load(['student:id,student_number,full_name,class', 'feeStructure:id,name,term,academic_year'])),
        ], 201);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Invoice retrieved',
            'data' => new InvoiceResource($invoice->load(['student:id,student_number,full_name,class', 'feeStructure:id,name,term,academic_year', 'transactions'])),
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice = $this->invoiceService->updateInvoice($invoice, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Invoice updated',
            'data' => new InvoiceResource($invoice),
        ]);
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        return DB::transaction(function () use ($invoice): JsonResponse {
            Student::whereKey($invoice->student_id)->lockForUpdate()->firstOrFail();
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ((float) $invoice->amount_paid > 0 || $invoice->payments()->exists() || $invoice->transactions()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoices with payments cannot be deleted.',
                    'code' => 422,
                ], 422);
            }

            $invoice->delete();

            return response()->json([
                'success' => true,
                'message' => 'Invoice deleted',
                'data' => null,
            ]);
        });
    }

    public function generate(GenerateInvoicesRequest $request): JsonResponse
    {
        $result = $this->invoiceService->bulkGenerate($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => "{$result['created']} invoice(s) generated.",
            'data' => $result,
        ], 201);
    }
}
