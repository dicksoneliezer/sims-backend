<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Student;
use App\Models\StudentPayment;
use App\Models\Transaction;
use App\Models\User;
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
            'parent' => false,
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

        return DB::transaction(function () use ($student, $invoice, $amount, $method, $description) {
            if ($invoice) {
                $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

                if ((int) $invoice->student_id !== (int) $student->id) {
                    throw ValidationException::withMessages([
                        'invoice_id' => 'The selected invoice does not belong to this student.',
                    ]);
                }

                if ((float) $invoice->balance <= 0) {
                    throw ValidationException::withMessages([
                        'invoice_id' => 'This invoice has already been fully paid.',
                    ]);
                }

                if ($amount > (float) $invoice->balance) {
                    throw ValidationException::withMessages([
                        'amount' => 'The payment amount cannot exceed the invoice balance.',
                    ]);
                }
            }

            return StudentPayment::create([
                'student_id' => $student->id,
                'invoice_id' => $invoice?->id,
                'control_number' => StudentPayment::generateControlNumber(),
                'amount' => round($amount, 2),
                'description' => $description ?: ($invoice?->description ?: 'School fees payment'),
                'status' => 'pending',
                'payment_method' => $method,
                'created_at' => now(),
                'expires_at' => now()->addDays(7),
            ]);
        });
    }

    public function process(StudentPayment $payment, string $method, ?string $phoneNumber, User $actor): StudentPayment
    {
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

        return DB::transaction(function () use ($payment, $method, $actor) {
            $payment = StudentPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

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

            $invoice = null;

            if ($payment->invoice_id) {
                $invoice = Invoice::whereKey($payment->invoice_id)->lockForUpdate()->firstOrFail();

                if ((float) $invoice->balance < (float) $payment->amount) {
                    throw ValidationException::withMessages([
                        'amount' => 'The invoice balance is lower than the payment amount.',
                    ]);
                }
            }

            $transactionReference = $this->transactionReference($method);

            Transaction::create([
                'transaction_ref' => $transactionReference,
                'student_id' => $payment->student_id,
                'invoice_id' => $payment->invoice_id,
                'type' => 'payment',
                'amount' => $payment->amount,
                'payment_method' => $method,
                'description' => $payment->description,
                'status' => 'completed',
                'reference' => $payment->control_number,
                'created_by' => $actor->id,
            ]);

            if ($invoice) {
                $amountPaid = round((float) $invoice->amount_paid + (float) $payment->amount, 2);
                $balance = round((float) $invoice->amount - $amountPaid, 2);

                $invoice->forceFill([
                    'amount_paid' => $amountPaid,
                    'balance' => max($balance, 0),
                    'status' => $balance <= 0 ? 'paid' : 'partial',
                ])->save();
            }

            $payment->forceFill([
                'status' => 'completed',
                'payment_method' => $method,
                'transaction_ref' => $transactionReference,
                'completed_at' => now(),
            ])->save();

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
    ): StudentPayment {
        $payment = $this->generateControlNumber($student, $invoice, $amount, $method, $description);

        return $this->process($payment, $method, $phoneNumber, $actor);
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
        return [
            'mpesa' => 'Pay Number: 123456, Amount: '.number_format((float) $payment->amount, 2, '.', ''),
            'tigopesa' => 'Pay Number: 789012, Amount: '.number_format((float) $payment->amount, 2, '.', ''),
            'airtel' => 'Pay Number: 456789, Amount: '.number_format((float) $payment->amount, 2, '.', ''),
            'bank' => 'Use control number '.$payment->control_number.' as the bank transfer reference.',
        ];
    }

    private function transactionReference(string $method): string
    {
        do {
            $reference = strtoupper($method).'-'.strtoupper(Str::random(10));
        } while (Transaction::where('transaction_ref', $reference)->exists());

        return $reference;
    }
}
