<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentPaymentResource;
use App\Models\Invoice;
use App\Models\Student;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountantPaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', Rule::in(array_keys(PaymentService::METHODS))],
            'description' => ['nullable', 'string'],
            'phone_number' => ['nullable', 'string', 'max:30'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        $invoice = isset($data['invoice_id']) ? Invoice::findOrFail($data['invoice_id']) : null;

        if ($invoice && (int) $invoice->student_id !== (int) $student->id) {
            throw ValidationException::withMessages([
                'invoice_id' => 'The selected invoice does not belong to this student.',
            ]);
        }

        $payment = $this->paymentService->recordManualPayment(
            $request->user(),
            $student,
            $invoice,
            (float) $data['amount'],
            $data['payment_method'],
            $data['description'] ?? null,
            $data['phone_number'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Payment recorded successfully',
            'data' => new StudentPaymentResource($payment),
        ], 201);
    }
}
