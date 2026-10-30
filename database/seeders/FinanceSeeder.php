<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentPayment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class FinanceSeeder extends Seeder
{
    public function run(): void
    {
        $accountant = User::where('role', 'accountant')->first();
        $classes = SchoolClass::all()->keyBy('name');

        $fees = [
            ['Form 1A', 'Form 1A Term 1 Tuition', 500000],
            ['Form 2B', 'Form 2B Term 1 Tuition', 450000],
            ['Form 3C', 'Form 3C Term 1 Tuition', 480000],
            ['Standard 5A', 'Standard 5A Term 1 Tuition', 300000],
        ];

        $feeModels = [];

        foreach ($fees as [$className, $name, $amount]) {
            $class = $classes[$className] ?? null;

            if (! $class) {
                continue;
            }

            $feeModels[$className] = FeeStructure::updateOrCreate(
                ['name' => $name],
                [
                    'class_id' => $class->id,
                    'school_level' => $class->level,
                    'term' => 'Term 1',
                    'academic_year' => '2026',
                    'amount' => $amount,
                    'description' => 'Tuition and school services for Term 1, 2026.',
                    'is_active' => true,
                ],
            );
        }

        $students = Student::whereIn('student_number', [
            'SIMS-2026-0001',
            'SIMS-2026-0002',
            'SIMS-2026-0003',
            'SIMS-2026-0004',
            'SIMS-2026-0005',
            'SIMS-2026-0007',
        ])->get()->keyBy('student_number');

        $invoices = [
            ['INV-2026-0001', 'SIMS-2026-0001', 'Form 1A', 500000, 200000, now()->addDays(10)->toDateString()],
            ['INV-2026-0002', 'SIMS-2026-0003', 'Form 2B', 450000, 0, now()->subDays(5)->toDateString()],
            ['INV-2026-0003', 'SIMS-2026-0002', 'Form 1A', 500000, 500000, now()->subDays(15)->toDateString()],
            ['INV-2026-0004', 'SIMS-2026-0005', 'Standard 5A', 300000, 100000, now()->addDays(20)->toDateString()],
            ['INV-2026-0005', 'SIMS-2026-0004', 'Form 2B', 450000, 0, now()->subDays(2)->toDateString()],
            ['INV-2026-0006', 'SIMS-2026-0007', 'Form 3C', 480000, 0, now()->addDays(14)->toDateString()],
        ];

        $invoiceModels = [];

        foreach ($invoices as [$number, $studentNumber, $className, $amount, $paid, $dueDate]) {
            $student = $students[$studentNumber] ?? null;
            $fee = $feeModels[$className] ?? null;

            if (! $student || ! $fee) {
                continue;
            }

            $balance = max($amount - $paid, 0);

            $invoiceModels[$number] = Invoice::updateOrCreate(
                ['invoice_number' => $number],
                [
                    'student_id' => $student->id,
                    'fee_structure_id' => $fee->id,
                    'description' => $fee->name.' Term 1 2026',
                    'amount' => $amount,
                    'amount_paid' => $paid,
                    'balance' => $balance,
                    'status' => $balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
                    'due_date' => $dueDate,
                    'issued_by' => $accountant?->id,
                ],
            );
        }

        $payments = [
            ['CTRL-SEED-0001', 'INV-2026-0001', 'SIMS-2026-0001', 200000, 'bank', 'completed', now()->subDays(2)],
            ['CTRL-SEED-0002', 'INV-2026-0003', 'SIMS-2026-0002', 500000, 'mpesa', 'completed', now()->subDays(1)],
            ['CTRL-SEED-0003', 'INV-2026-0004', 'SIMS-2026-0005', 100000, 'bank', 'completed', now()->subDays(4)],
            ['CTRL-SEED-0004', 'INV-2026-0002', 'SIMS-2026-0003', 100000, 'mpesa', 'pending', null],
        ];

        foreach ($payments as $index => [$controlNumber, $invoiceNumber, $studentNumber, $amount, $method, $status, $completedAt]) {
            $invoice = $invoiceModels[$invoiceNumber] ?? null;
            $student = $students[$studentNumber] ?? null;

            if (! $invoice || ! $student) {
                continue;
            }

            $payment = StudentPayment::updateOrCreate(
                ['control_number' => $controlNumber],
                [
                    'student_id' => $student->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $amount,
                    'description' => $invoice->description,
                    'status' => $status,
                    'transaction_ref' => $status === 'completed' ? 'TXN-SEED-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT) : null,
                    'payment_method' => $method,
                    'created_at' => now()->subDays(5),
                    'completed_at' => $completedAt,
                    'expires_at' => now()->addDays(5),
                ],
            );

            if ($status === 'completed') {
                Transaction::updateOrCreate(
                    ['transaction_ref' => $payment->transaction_ref],
                    [
                        'student_id' => $student->id,
                        'invoice_id' => $invoice->id,
                        'type' => 'payment',
                        'amount' => $amount,
                        'payment_method' => $method,
                        'description' => $invoice->description,
                        'status' => 'completed',
                        'reference' => $controlNumber,
                        'created_by' => $accountant?->id,
                        'created_at' => $completedAt,
                        'updated_at' => $completedAt,
                    ],
                );
            }
        }

        $expenses = [
            ['EXP-2026-0001', 'Utilities', 'Monthly water and electricity', 85000, now()->subDays(10)->toDateString(), 'TANESCO / DAWASA', 'bank'],
            ['EXP-2026-0002', 'Supplies', 'Classroom stationery and printing', 120000, now()->subDays(7)->toDateString(), 'Stationery Mart', 'cash'],
            ['EXP-2026-0003', 'Maintenance', 'Repair of classroom windows', 65000, now()->subDays(3)->toDateString(), 'Fundi Juma', 'cash'],
        ];

        foreach ($expenses as [$number, $category, $description, $amount, $date, $paidTo, $method]) {
            Expense::updateOrCreate(
                ['expense_number' => $number],
                [
                    'category' => $category,
                    'description' => $description,
                    'amount' => $amount,
                    'expense_date' => $date,
                    'paid_to' => $paidTo,
                    'payment_method' => $method,
                    'created_by' => $accountant?->id,
                ],
            );
        }
    }
}
