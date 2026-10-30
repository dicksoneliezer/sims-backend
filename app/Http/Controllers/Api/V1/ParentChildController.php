<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\StudentPaymentResource;
use App\Http\Resources\StudentResource;
use App\Http\Resources\TeacherNoteResource;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentChildController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $children = $request->user()->children()->orderBy('full_name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Children retrieved',
            'data' => StudentResource::collection($children),
        ]);
    }

    public function show(Request $request, Student $student): JsonResponse
    {
        $this->ensureChild($request, $student);
        $student->load('parents');

        return response()->json([
            'success' => true,
            'message' => 'Child retrieved',
            'data' => new StudentResource($student),
        ]);
    }

    public function results(Request $request, Student $student, StudentController $studentController): JsonResponse
    {
        $this->ensureChild($request, $student);

        return $studentController->results($request, $student);
    }

    public function attendance(Request $request, Student $student, StudentController $studentController): JsonResponse
    {
        $this->ensureChild($request, $student);

        return $studentController->attendance($request, $student);
    }

    public function notes(Request $request, Student $student): JsonResponse
    {
        $this->ensureChild($request, $student);

        $notes = $student->teacherNotes()
            ->with('teacher:id,full_name')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Child notes retrieved',
            'data' => TeacherNoteResource::collection($notes),
        ]);
    }

    public function invoices(Request $request, Student $student): JsonResponse
    {
        $this->ensureChild($request, $student);

        $invoices = $student->invoices()
            ->with('feeStructure:id,name,term,academic_year')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Child invoices retrieved',
            'data' => InvoiceResource::collection($invoices),
            'summary' => [
                'total_invoiced' => (float) $invoices->sum('amount'),
                'total_paid' => (float) $invoices->sum('amount_paid'),
                'total_outstanding' => (float) $invoices->sum('balance'),
            ],
        ]);
    }

    public function payments(Request $request, Student $student): JsonResponse
    {
        $this->ensureChild($request, $student);

        $payments = $student->payments()
            ->with('invoice:id,invoice_number,amount,balance,status')
            ->latest('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Child payments retrieved',
            'data' => StudentPaymentResource::collection($payments),
        ]);
    }

    private function ensureChild(Request $request, Student $student): void
    {
        abort_unless(
            $request->user()->children()->whereKey($student->id)->exists(),
            403,
            'This student is not linked to your account.',
        );
    }
}
