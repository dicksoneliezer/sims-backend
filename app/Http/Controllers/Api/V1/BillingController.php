<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\StudentPaymentResource;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\StudentPayment;
use App\Services\BillingService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    public function report(Request $request, BillingService $billing): JsonResponse
    {
        $query = Invoice::query()
            ->when($request->filled('period'), fn ($q) => $q->where('period', $request->string('period')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('search'), function ($q) use ($request): void {
                $search = $request->string('search')->toString();
                $q->where(fn ($q) => $q->where('invoice_number', 'like', "%{$search}%")->orWhereHas('student', fn ($q) => $q->where('full_name', 'like', "%{$search}%")->orWhere('student_number', 'like', "%{$search}%")));
            });
        $filtered = $request->filled('period') || $request->filled('status');
        $ids = $filtered ? (clone $query)->distinct()->pluck('student_id')->all() : Student::query()->when($request->filled('search'), function ($q) use ($request): void {
            $search = $request->string('search')->toString();
            $q->where(fn ($q) => $q->where('full_name', 'like', "%{$search}%")->orWhere('student_number', 'like', "%{$search}%")->orWhereHas('invoices', fn ($q) => $q->where('invoice_number', 'like', "%{$search}%")));
        })->pluck('id')->all();
        $summaries = collect($billing->summaries($ids));
        $totals = (clone $query)->selectRaw('student_id, SUM(amount) as billed, SUM(amount_paid) as paid, SUM(balance) as debt')->groupBy('student_id')->get();
        $invoices = (clone $query)->with(['student:id,student_number,full_name,class', 'feeStructure', 'transactions' => fn ($q) => $q->whereIn('type', ['payment', 'bill_allocation'])])->orderByDesc('created_at')->orderByDesc('id')->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return response()->json([
            'success' => true, 'data' => InvoiceResource::collection($invoices->items()),
            'meta' => ['current_page' => $invoices->currentPage(), 'last_page' => $invoices->lastPage(), 'total' => $invoices->total()],
            'periods' => Invoice::select('period')->distinct()->orderByDesc('period')->pluck('period'),
            'summary' => [
                'total_bills' => (float) $totals->sum('billed'), 'total_payments' => $filtered ? (float) $totals->sum('paid') : $summaries->sum('total_payments'),
                'total_debt' => (float) $totals->sum('debt'), 'total_overpaid' => $summaries->sum('credit_balance'),
                'paying_students' => $totals->filter(fn ($row) => (float) $row->paid > 0 && (float) $row->debt > 0)->count(),
                'owing_students' => $totals->filter(fn ($row) => (float) $row->debt > 0)->count(),
                'settled_students' => $totals->filter(fn ($row) => (float) $row->debt <= 0)->count(),
                'overpaid_students' => $summaries->where('credit_balance', '>', 0)->count(),
                'payments_are_allocations' => $filtered,
            ],
        ]);
    }

    public function feeStructures(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => FeeStructure::where('is_active', true)->orderBy('name')->get(['id', 'name', 'class_id', 'school_level', 'amount', 'term', 'academic_year'])]);
    }

    public function confirm(Request $request, StudentPayment $payment, PaymentService $payments): JsonResponse
    {
        $data = $request->validate(['payment_method' => ['required', Rule::in(array_keys(PaymentService::METHODS))], 'phone_number' => ['nullable', 'string', 'max:30'], 'reference' => ['nullable', 'string', 'max:60']]);
        $payment = $payments->process($payment, $data['payment_method'], $data['phone_number'] ?? null, $request->user(), $data['reference'] ?? null);

        return response()->json(['success' => true, 'message' => 'Payment confirmed and allocated.', 'data' => new StudentPaymentResource($payment)]);
    }

    public function transfer(Request $request, BillingService $billing): JsonResponse
    {
        $data = $request->validate(['request_id' => ['nullable', 'uuid'], 'source_student_id' => ['required', 'integer', 'exists:students,id'], 'target_student_id' => ['required', 'integer', 'different:source_student_id', 'exists:students,id'], 'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999999.99'], 'reason' => ['required', 'string', 'max:500']]);
        $result = $billing->transfer(Student::findOrFail($data['source_student_id']), Student::findOrFail($data['target_student_id']), (float) $data['amount'], $request->user(), $data['reason'], $data['request_id'] ?? null);

        return response()->json(['success' => true, 'message' => 'Credit transferred.', 'data' => $result]);
    }

    public function student(Request $request, BillingService $billing): JsonResponse
    {
        $student = Student::where('user_id', $request->user()->id)->firstOrFail();

        return response()->json(['success' => true, 'data' => [
            'student_id' => $student->id, 'summary' => $billing->summary($student),
            'invoices' => InvoiceResource::collection($student->invoices()->orderBy('due_date')->orderBy('id')->get()),
            'payments' => StudentPaymentResource::collection($student->payments()->orderByDesc('id')->get()),
        ]]);
    }

    public function studentControlNumber(Request $request, PaymentService $payments): JsonResponse
    {
        $data = $request->validate(['invoice_id' => ['nullable', 'integer', 'exists:invoices,id'], 'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999999.99'], 'payment_method' => ['required', Rule::in(array_keys(PaymentService::METHODS))]]);
        $student = Student::where('user_id', $request->user()->id)->firstOrFail();
        $invoice = empty($data['invoice_id']) ? null : Invoice::where('student_id', $student->id)->findOrFail($data['invoice_id']);
        $payment = $payments->generateControlNumber($student, $invoice, (float) $data['amount'], $data['payment_method']);

        return response()->json(['success' => true, 'data' => new StudentPaymentResource($payment)], 201);
    }
}
