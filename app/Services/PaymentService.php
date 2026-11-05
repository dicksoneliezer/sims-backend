<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Scopes\SchoolScope;
use App\Models\Student;
use App\Models\StudentPayment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public const METHODS = [
        'mpesa' => [
            'name' => 'M-Pesa',
            'logo' => '/assets/mpesa.png',
            'requires_phone' => true,
            'processing_time' => 'Instant',
            'parent' => true,
        ],
        'tigopesa' => [
            'name' => 'Tigo Pesa',
            'logo' => '/assets/tigopesa.png',
            'requires_phone' => true,
            'processing_time' => 'Instant',
            'parent' => true,
        ],
        'airtel' => [
            'name' => 'Airtel Money',
            'logo' => '/assets/airtel.png',
            'requires_phone' => true,
            'processing_time' => 'Instant',
            'parent' => true,
        ],
        'bank' => [
            'name' => 'Bank Transfer',
            'logo' => '/assets/bank.png',
            'requires_phone' => false,
            'processing_time' => '1-2 business days',
            'parent' => true,
        ],
        'cash' => [
            'name' => 'Cash',
            'logo' => null,
            'requires_phone' => false,
            'processing_time' => 'Instant',
            'parent' => true,
        ],
    ];

    public function parentMethods(): array
    {
        return collect(self::METHODS)
            ->filter(fn (array $method) => $method['parent'])
            ->map(fn (array $method, string $code) => [
                'code' => $code,
                'name' => $method['name'],
                'logo' => $method['logo'],
                'is_active' => true,
                'requires_phone' => $method['requires_phone'],
                'processing_time' => $method['processing_time'],
            ])
            ->values()
            ->all();
    }

    public function generateControlNumber(
        Student $student,
        ?Invoice $invoice,
        float $amount,
        string $method,
        ?string $description = null,
        ?string $controlNumber = null,
    ): StudentPayment {
        if (! array_key_exists($method, self::METHODS)) {
            throw ValidationException::withMessages([
                'payment_method' => 'The selected payment method is invalid.',
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'The payment amount must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($student, $invoice, $amount, $method, $description, $controlNumber) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            if ($invoice) {
                $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

                if ((int) $invoice->student_id !== (int) $student->id) {
                    throw ValidationException::withMessages([
                        'invoice_id' => 'The selected invoice does not belong to this student.',
                    ]);
                }

            }

            return StudentPayment::create([
                'student_id' => $student->id,
                'invoice_id' => $invoice?->id,
                'control_number' => $controlNumber ?? StudentPayment::generateControlNumber(),
                'amount' => round($amount, 2),
                'description' => $description ?: ($invoice?->description ?: 'School fees payment'),
                'status' => 'pending',
                'payment_method' => $method,
                'created_at' => now(),
                'expires_at' => now()->addDays(7),
            ]);
        });
    }

    public function process(StudentPayment $payment, string $method, ?string $phoneNumber, User $actor, ?string $externalReference = null): StudentPayment
    {
        abort_unless(in_array($actor->role, ['accountant', 'super_admin'], true), 403, 'Only an accountant can confirm received payments.');

        if (! array_key_exists($method, self::METHODS)) {
            throw ValidationException::withMessages([
                'payment_method' => 'The selected payment method is invalid.',
            ]);
        }

        if (self::METHODS[$method]['requires_phone'] && blank($phoneNumber)) {
            throw ValidationException::withMessages([
                'phone_number' => 'A phone number is required for this payment method.',
            ]);
        }

        return DB::transaction(function () use ($payment, $method, $actor, $externalReference) {
            $student = Student::whereKey($payment->student_id)->lockForUpdate()->firstOrFail();
            $payment = StudentPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->isCompleted()) {
                return $payment->load(['student:id,full_name,student_number,class', 'invoice']);
            }

            if (! $payment->isPending()) {
                throw ValidationException::withMessages([
                    'payment' => 'Only pending payments can be completed.',
                ]);
            }

            if ($payment->isExpired()) {
                throw ValidationException::withMessages([
                    'payment' => 'This control number has expired. Generate a new one to continue.',
                ]);
            }

            $transactionReference = $externalReference ? strtoupper($method).'-S'.$student->school_id.'-'.strtoupper($externalReference) : $this->transactionReference($method);
            if (Transaction::withoutGlobalScope(SchoolScope::class)->where('transaction_ref', $transactionReference)->exists() || ($externalReference && Transaction::where('transaction_ref', strtoupper($method).'-'.strtoupper($externalReference))->exists())) {
                throw ValidationException::withMessages(['reference' => 'This payment reference has already been recorded.']);
            }

            try {
                Transaction::create([
                    'transaction_ref' => $transactionReference,
                    'student_id' => $payment->student_id,
                    'invoice_id' => null,
                    'type' => 'payment',
                    'amount' => $payment->amount,
                    'payment_method' => $method,
                    'description' => $payment->description,
                    'status' => 'completed',
                    'reference' => $payment->control_number,
                    'created_by' => $actor->id,
                ]);
            } catch (UniqueConstraintViolationException $error) {
                throw ValidationException::withMessages(['reference' => 'This payment reference has already been recorded.']);
            }

            $payment->forceFill([
                'status' => 'completed',
                'payment_method' => $method,
                'transaction_ref' => $transactionReference,
                'completed_at' => now(),
            ])->save();

            app(BillingService::class)->applyCredit($student, $actor);

            return $payment->fresh()->load(['student:id,full_name,student_number,class', 'invoice']);
        });
    }

    public function recordManualPayment(
        User $actor,
        Student $student,
        ?Invoice $invoice,
        float $amount,
        string $method,
        ?string $description = null,
        ?string $phoneNumber = null,
        ?string $externalReference = null,
        ?string $requestId = null,
    ): StudentPayment {
        return DB::transaction(function () use ($actor, $student, $invoice, $amount, $method, $description, $phoneNumber, $externalReference, $requestId) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $controlNumber = $requestId ? 'CTRL-MANUAL-'.$requestId : null;
            $existing = $controlNumber ? StudentPayment::where('control_number', $controlNumber)->first() : null;
            if ($existing) {
                if ((int) $existing->student_id !== (int) $student->id || BillingService::cents($existing->amount) !== BillingService::cents($amount) || $existing->payment_method !== $method || (int) $existing->invoice_id !== (int) $invoice?->id) {
                    throw ValidationException::withMessages(['request_id' => 'This receipt request has already been used with different details.']);
                }

                return $this->process($existing, $method, $phoneNumber, $actor, $externalReference);
            }
            if ($controlNumber && StudentPayment::withoutGlobalScope(SchoolScope::class)->where('control_number', $controlNumber)->exists()) {
                throw ValidationException::withMessages(['request_id' => 'This receipt request has already been used.']);
            }
            $payment = $this->generateControlNumber($student, $invoice, $amount, $method, $description, $controlNumber);

            return $this->process($payment, $method, $phoneNumber, $actor, $externalReference);
        }, 3);
    }

    public function cancel(StudentPayment $payment): StudentPayment
    {
        return DB::transaction(function () use ($payment) {
            $payment = StudentPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! $payment->isPending()) {
                throw ValidationException::withMessages([
                    'payment' => 'Only pending payments can be cancelled.',
                ]);
            }

            $payment->forceFill(['status' => 'cancelled'])->save();

            return $payment->fresh()->load(['student:id,full_name,student_number,class', 'invoice']);
        });
    }

    public function instructions(StudentPayment $payment): array
    {
        $instruction = 'Use control number '.$payment->control_number." with the school accountant's official payment details. Payment is confirmed by the accountant after funds are received.";

        return array_fill_keys(array_keys(self::METHODS), $instruction);
    }

    private function transactionReference(string $method): string
    {
        do {
            $reference = strtoupper($method).'-'.strtoupper(Str::random(10));
        } while (Transaction::where('transaction_ref', $reference)->exists());

        return $reference;
    }
}
