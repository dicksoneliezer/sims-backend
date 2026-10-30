<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentPaymentResource;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\StudentPayment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ControlNumberController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $payments = StudentPayment::query()
            ->with(['student:id,student_number,full_name,class', 'invoice:id,invoice_number'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('control_number', 'like', "%{$search}%")
                        ->orWhere('transaction_ref', 'like', "%{$search}%")
                        ->orWhereHas('student', fn ($query) => $query->where('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('payment_method'), fn ($query) => $query->where('payment_method', $request->string('payment_method')))
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Control numbers retrieved',
            'data' => StudentPaymentResource::collection($payments->items()),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'last_page' => $payments->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', Rule::in(array_keys(PaymentService::METHODS))],
            'description' => ['nullable', 'string'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        $invoice = Invoice::findOrFail($data['invoice_id']);

        if ((int) $invoice->student_id !== (int) $student->id) {
            throw ValidationException::withMessages([
                'invoice_id' => 'The selected invoice does not belong to this student.',
            ]);
        }

        $payment = $this->paymentService->generateControlNumber(
            $student,
            $invoice,
            (float) $data['amount'],
            $data['payment_method'],
            $data['description'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Control number generated successfully',
            'data' => array_merge(
                (new StudentPaymentResource($payment->load('student:id,student_number,full_name,class')))->resolve(),
                ['payment_instructions' => $this->paymentService->instructions($payment)],
            ),
        ], 201);
    }
}
