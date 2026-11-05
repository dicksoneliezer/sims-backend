<?php

namespace Tests\Feature;

use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinanceManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAccountant(): User
    {
        $accountant = User::factory()->role('accountant')->create();
        Sanctum::actingAs($accountant);

        return $accountant;
    }

    public function test_accountant_can_create_update_and_delete_invoices(): void
    {
        $this->actingAsAccountant();
        $student = Student::factory()->create();

        $created = $this->postJson('/api/v1/accountant/invoices', [
            'student_id' => $student->id,
            'description' => 'Term 1 tuition',
            'amount' => 1000,
            'due_date' => now()->addDays(10)->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('data.status', 'unpaid')
            ->assertJsonPath('data.balance', '1000.00')
            ->assertJsonPath('data.student.full_name', $student->full_name);

        $invoiceId = $created->json('data.id');

        $this->putJson("/api/v1/accountant/invoices/{$invoiceId}", ['amount' => 800])
            ->assertOk()
            ->assertJsonPath('data.amount', '800.00')
            ->assertJsonPath('data.balance', '800.00');

        $this->getJson('/api/v1/accountant/invoices?status=unpaid')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson("/api/v1/accountant/invoices/{$invoiceId}")
            ->assertOk()
            ->assertJsonPath('data.description', 'Term 1 tuition');

        $unpaidInvoice = Invoice::create([
            'invoice_number' => 'INV-2026-7001',
            'student_id' => $student->id,
            'amount' => 200,
            'amount_paid' => 0,
            'balance' => 200,
            'status' => 'unpaid',
        ]);

        $this->deleteJson("/api/v1/accountant/invoices/{$unpaidInvoice->id}")->assertOk();
        $this->assertDatabaseMissing('invoices', ['id' => $unpaidInvoice->id]);
    }

    public function test_invoice_with_payments_cannot_be_deleted_or_reduced_below_paid_amount(): void
    {
        $this->actingAsAccountant();
        $student = Student::factory()->create();

        $invoice = Invoice::create([
            'invoice_number' => 'INV-2026-7002',
            'student_id' => $student->id,
            'amount' => 1000,
            'amount_paid' => 0,
            'balance' => 1000,
            'status' => 'unpaid',
        ]);

        $this->postJson('/api/v1/accountant/payments', [
            'student_id' => $student->id,
            'invoice_id' => $invoice->id,
            'amount' => 400,
            'payment_method' => 'cash',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.amount', '400.00');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'amount_paid' => 400,
            'balance' => 600,
            'status' => 'partial',
        ]);

        $this->assertSame(1, Transaction::where('type', 'payment')->count());
        $this->assertEquals(400, Transaction::where('type', 'bill_allocation')->sum('amount'));

        $this->putJson("/api/v1/accountant/invoices/{$invoice->id}", ['amount' => 300])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->deleteJson("/api/v1/accountant/invoices/{$invoice->id}")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_bulk_invoice_generation_is_idempotent(): void
    {
        $this->actingAsAccountant();
        $class = SchoolClass::create(['name' => 'Form 1A', 'level' => 'secondary', 'capacity' => 40]);
        Student::factory()->count(2)->create(['class_id' => $class->id, 'class' => 'Form 1A']);

        FeeStructure::create([
            'name' => 'Form 1A Term 1 Tuition',
            'class_id' => $class->id,
            'school_level' => 'secondary',
            'term' => 'Term 1',
            'academic_year' => '2026',
            'amount' => 500000,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/accountant/invoices/generate', [
            'class_id' => $class->id,
            'due_date' => now()->addDays(14)->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.skipped', 0);

        $this->postJson('/api/v1/accountant/invoices/generate', [
            'class_id' => $class->id,
        ])->assertCreated()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.skipped', 2);

        $this->assertDatabaseCount('invoices', 2);
    }

    public function test_accountant_can_manage_expenses(): void
    {
        $this->actingAsAccountant();

        $created = $this->postJson('/api/v1/accountant/expenses', [
            'category' => 'Utilities',
            'description' => 'Water bill',
            'amount' => 75000,
            'expense_date' => now()->toDateString(),
            'paid_to' => 'DAWASA',
            'payment_method' => 'bank',
        ])->assertCreated()
            ->assertJsonPath('data.category', 'Utilities')
            ->assertJsonPath('data.expense_number', 'EXP-'.now()->format('Y').'-0001');

        $expenseId = $created->json('data.id');

        $this->getJson('/api/v1/accountant/expenses?search=Water')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->putJson("/api/v1/accountant/expenses/{$expenseId}", ['amount' => 80000])
            ->assertOk()
            ->assertJsonPath('data.amount', '80000.00');

        $this->deleteJson("/api/v1/accountant/expenses/{$expenseId}")->assertOk();
        $this->assertDatabaseMissing('expenses', ['id' => $expenseId]);
    }

    public function test_accountant_can_view_debts_transactions_control_numbers_and_reports(): void
    {
        $this->actingAsAccountant();
        $student = Student::factory()->create(['full_name' => 'Amina Hassan']);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-2026-7003',
            'student_id' => $student->id,
            'amount' => 1000,
            'amount_paid' => 0,
            'balance' => 1000,
            'status' => 'unpaid',
            'due_date' => now()->subDays(4)->toDateString(),
        ]);

        $this->getJson('/api/v1/accountant/debts')
            ->assertOk()
            ->assertJsonPath('summary.total_outstanding', 1000)
            ->assertJsonPath('summary.overdue_count', 1)
            ->assertJsonPath('data.0.invoice_number', 'INV-2026-7003');

        $this->postJson('/api/v1/accountant/control-numbers', [
            'student_id' => $student->id,
            'invoice_id' => $invoice->id,
            'amount' => 500,
            'payment_method' => 'mpesa',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonStructure(['data' => ['control_number', 'expires_at', 'payment_instructions' => ['mpesa']]]);

        $this->getJson('/api/v1/accountant/control-numbers?status=pending')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->postJson('/api/v1/accountant/payments', [
            'student_id' => $student->id,
            'invoice_id' => $invoice->id,
            'amount' => 500,
            'payment_method' => 'bank',
        ])->assertCreated();

        $this->getJson('/api/v1/accountant/transactions')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.type', 'payment');

        $this->postJson('/api/v1/accountant/expenses', [
            'category' => 'Supplies',
            'amount' => 25000,
            'expense_date' => now()->toDateString(),
        ])->assertCreated();

        $this->getJson('/api/v1/accountant/reports/financial')
            ->assertOk()
            ->assertJsonPath('data.total_invoiced', 1000)
            ->assertJsonPath('data.total_collected', 500)
            ->assertJsonPath('data.total_outstanding', 500)
            ->assertJsonPath('data.total_expenses', 25000)
            ->assertJsonPath('data.net_income', -24500);
    }

    public function test_non_accountant_cannot_access_accountant_finance_endpoints(): void
    {
        $teacher = User::factory()->role('teacher')->create();
        Sanctum::actingAs($teacher);

        $this->getJson('/api/v1/accountant/invoices')->assertForbidden();
        $this->postJson('/api/v1/accountant/payments', [])->assertForbidden();
        $this->getJson('/api/v1/accountant/expenses')->assertForbidden();
    }
}
