<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\StudentPayment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminFinanceTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->role('super_admin')->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_admin_can_view_finance_overview(): void
    {
        $this->actingAsAdmin();
        $student = Student::factory()->create();

        $invoice = Invoice::create([
            'invoice_number' => 'INV-2026-8001',
            'student_id' => $student->id,
            'amount' => 1000,
            'amount_paid' => 400,
            'balance' => 600,
            'status' => 'partial',
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        StudentPayment::create([
            'student_id' => $student->id,
            'invoice_id' => $invoice->id,
            'control_number' => 'CTRL-20261023-0001',
            'amount' => 400,
            'status' => 'completed',
            'payment_method' => 'mpesa',
            'completed_at' => now(),
        ]);

        StudentPayment::create([
            'student_id' => $student->id,
            'invoice_id' => $invoice->id,
            'control_number' => 'CTRL-20261023-0002',
            'amount' => 100,
            'status' => 'pending',
            'payment_method' => 'bank',
        ]);

        Expense::create([
            'expense_number' => 'EXP-2026-8001',
            'category' => 'Utilities',
            'amount' => 150,
            'expense_date' => now()->toDateString(),
        ]);

        Transaction::create([
            'transaction_ref' => 'TXN-TEST-0001',
            'student_id' => $student->id,
            'invoice_id' => $invoice->id,
            'type' => 'payment',
            'amount' => 400,
            'payment_method' => 'mpesa',
            'status' => 'completed',
        ]);

        $this->getJson('/api/v1/admin/finance/overview')
            ->assertOk()
            ->assertJsonPath('data.total_invoiced', 1000)
            ->assertJsonPath('data.total_collected', 400)
            ->assertJsonPath('data.total_outstanding', 600)
            ->assertJsonPath('data.total_expenses', 150)
            ->assertJsonPath('data.pending_payments', 1)
            ->assertJsonPath('data.collection_rate', 40)
            ->assertJsonPath('data.recent_transactions.0.transaction_ref', 'TXN-TEST-0001');
    }

    public function test_admin_can_read_finance_lists(): void
    {
        $this->actingAsAdmin();
        $student = Student::factory()->create();

        Invoice::create([
            'invoice_number' => 'INV-2026-8002',
            'student_id' => $student->id,
            'amount' => 500,
            'amount_paid' => 0,
            'balance' => 500,
            'status' => 'unpaid',
        ]);

        Expense::create([
            'expense_number' => 'EXP-2026-8002',
            'category' => 'Supplies',
            'amount' => 200,
            'expense_date' => now()->toDateString(),
        ]);

        $this->getJson('/api/v1/admin/finance/invoices')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/admin/finance/debts')->assertOk()->assertJsonPath('summary.invoice_count', 1);
        $this->getJson('/api/v1/admin/finance/expenses')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/admin/finance/transactions')->assertOk()->assertJsonPath('meta.total', 0);
    }
}
