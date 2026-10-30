<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentPaymentResource;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\StudentPayment;
use App\Services\PaymentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class ParentPaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function index(Request $request): JsonResponse
    {
        $payments = $this->paymentQuery($request)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('payment_method'), fn ($query) => $query->where('payment_method', $request->string('payment_method')))
            ->latest('id')
            ->paginate(min((int) $request->integer('per_page', 15), 100));

        return response()->json([
            'success' => true,
            'message' => 'Payments retrieved',
            'data' => StudentPaymentResource::collection($payments->items()),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'last_page' => $payments->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, StudentPayment $payment): JsonResponse
    {
        $this->ensurePaymentOwner($request, $payment);

        return response()->json([
            'success' => true,
            'message' => 'Payment retrieved',
            'data' => new StudentPaymentResource($payment->load(['student:id,student_number,full_name,class', 'invoice'])),
        ]);
    }

    public function generateControlNumber(Request $request): JsonResponse
    {
        $parentMethods = array_keys(array_filter(PaymentService::METHODS, fn (array $method) => $method['parent']));

        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', Rule::in($parentMethods)],
            'description' => ['nullable', 'string'],
        ]);

        abort_unless(
            $request->user()->children()->whereKey($data['student_id'])->exists(),
            403,
            'This student is not linked to your account.',
        );

        $student = Student::findOrFail($data['student_id']);
        $invoice = Invoice::findOrFail($data['invoice_id']);

        $payment = $this->paymentService->generateControlNumber(
            $student,
            $invoice,
            (float) $data['amount'],
            $data['payment_method'],
            $data['description'] ?? null,
        );

        $resource = (new StudentPaymentResource($payment->load(['student:id,student_number,full_name,class', 'invoice'])))->resolve();

        return response()->json([
            'success' => true,
            'message' => 'Control number generated successfully',
            'data' => array_merge($resource, [
                'payment_instructions' => $this->paymentService->instructions($payment),
            ]),
        ], 201);
    }

    public function pay(Request $request, StudentPayment $payment): JsonResponse
    {
        $parentMethods = array_keys(array_filter(PaymentService::METHODS, fn (array $method) => $method['parent']));

        $data = $request->validate([
            'payment_method' => ['required', Rule::in($parentMethods)],
            'phone_number' => ['nullable', 'string', 'max:30'],
        ]);

        $this->ensurePaymentOwner($request, $payment);

        $payment = $this->paymentService->process(
            $payment,
            $data['payment_method'],
            $data['phone_number'] ?? null,
            $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Payment completed successfully.',
            'data' => new StudentPaymentResource($payment),
        ]);
    }

    public function cancel(Request $request, StudentPayment $payment): JsonResponse
    {
        $this->ensurePaymentOwner($request, $payment);
        $payment = $this->paymentService->cancel($payment);

        return response()->json([
            'success' => true,
            'message' => 'Payment cancelled',
            'data' => new StudentPaymentResource($payment),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $payments = $this->paymentQuery($request)
            ->whereIn('status', ['completed', 'cancelled'])
            ->latest('id')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return response()->json([
            'success' => true,
            'message' => 'Payment history retrieved',
            'data' => StudentPaymentResource::collection($payments->items()),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'last_page' => $payments->lastPage(),
            ],
        ]);
    }

    public function balance(Request $request): JsonResponse
    {
        $children = $request->user()->children()
            ->with(['invoices' => fn ($query) => $query->orderBy('due_date')->orderBy('id')])
            ->orderBy('full_name')
            ->get();

        $data = $children->map(function (Student $child) {
            $invoices = $child->invoices;

            return [
                'student_id' => $child->id,
                'student_name' => $child->full_name,
                'student_number' => $child->student_number,
                'class' => $child->class,
                'total_invoiced' => (float) $invoices->sum('amount'),
                'total_paid' => (float) $invoices->sum('amount_paid'),
                'outstanding' => (float) $invoices->sum('balance'),
                'invoices' => $invoices->map(fn (Invoice $invoice) => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'description' => $invoice->description,
                    'amount' => $invoice->amount,
                    'amount_paid' => $invoice->amount_paid,
                    'balance' => $invoice->balance,
                    'status' => $invoice->status,
                    'due_date' => $invoice->due_date?->toDateString(),
                    'is_overdue' => $invoice->isOverdue(),
                ])->values(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Outstanding balance retrieved',
            'data' => [
                'total_outstanding' => (float) $data->sum('outstanding'),
                'children' => $data,
            ],
        ]);
    }

    public function methods(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Payment methods retrieved',
            'data' => $this->paymentService->parentMethods(),
        ]);
    }

    public function status(Request $request, string $controlNumber): JsonResponse
    {
        $payment = StudentPayment::query()
            ->where('control_number', $controlNumber)
            ->whereIn('student_id', $this->childIds($request))
            ->with(['student:id,student_number,full_name,class', 'invoice'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'message' => 'Payment status retrieved',
            'data' => array_merge(
                (new StudentPaymentResource($payment))->resolve(),
                ['receipt_url' => "/api/v1/parent/payments/{$payment->id}/receipt"],
            ),
        ]);
    }

    public function receipt(Request $request, StudentPayment $payment): JsonResponse
    {
        $this->ensurePaymentOwner($request, $payment);

        return response()->json([
            'success' => true,
            'message' => 'Payment receipt retrieved',
            'data' => [
                'receipt_number' => 'RCPT-'.$payment->control_number,
                'control_number' => $payment->control_number,
                'student' => $payment->student?->full_name,
                'amount' => $payment->amount,
                'status' => $payment->status,
                'payment_method' => $payment->payment_method,
                'transaction_ref' => $payment->transaction_ref,
                'paid_at' => $payment->completed_at?->toIso8601String(),
            ],
        ]);
    }

    private function paymentQuery(Request $request): Builder
    {
        return StudentPayment::query()
            ->whereIn('student_id', $this->childIds($request))
            ->with(['student:id,student_number,full_name,class', 'invoice:id,invoice_number,amount,balance,status'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('control_number', 'like', "%{$search}%")
                        ->orWhere('transaction_ref', 'like', "%{$search}%")
                        ->orWhereHas('student', fn ($query) => $query->where('full_name', 'like', "%{$search}%"));
                });
            });
    }

    private function childIds(Request $request): Collection
    {
        return $request->user()->children()->pluck('students.id');
    }

    private function ensurePaymentOwner(Request $request, StudentPayment $payment): void
    {
        abort_unless(
            $this->childIds($request)->contains($payment->student_id),
            403,
            'This payment is not linked to your account.',
        );
    }
}
