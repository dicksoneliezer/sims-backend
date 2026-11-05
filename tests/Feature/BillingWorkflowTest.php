<?php

namespace Tests\Feature;

use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BillingService;
use App\Services\InvoiceService;
use App\Services\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function accountant(): User
    {
        $user = User::factory()->role('accountant')->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function bill(Student $student, int|float $amount, string $period, string $due): Invoice
    {
        return Invoice::create(['student_id' => $student->id, 'invoice_number' => 'TEST-'.fake()->uuid(), 'period' => $period, 'amount' => $amount, 'amount_paid' => 0, 'balance' => $amount, 'status' => 'unpaid', 'due_date' => $due]);
    }

    public function test_bulk_create_can_reuse_a_fee_for_different_periods_and_retry_without_duplicates(): void
    {
        $this->accountant();
        $class = SchoolClass::create(['name' => 'Form 1', 'level' => 'secondary']);
        Student::factory()->count(2)->create(['class_id' => $class->id]);
        $fee = FeeStructure::create(['name' => 'Tuition', 'class_id' => $class->id, 'amount' => 1500000, 'is_active' => true]);
        $payload = ['class_id' => $class->id, 'fee_structure_id' => $fee->id, 'period' => 'Term 1 2026', 'due_date' => '2026-01-31'];
        $this->postJson('/api/v1/accountant/invoices/generate', $payload)->assertCreated()->assertJsonPath('data.created', 2);
        $this->postJson('/api/v1/accountant/invoices/generate', $payload)->assertCreated()->assertJsonPath('data.skipped', 2);
        $this->postJson('/api/v1/accountant/invoices/generate', array_replace($payload, ['period' => 'Term 2 2026']))->assertCreated()->assertJsonPath('data.created', 2);
        $this->assertDatabaseCount('invoices', 4);
        $this->assertDatabaseHas('invoices', ['period' => 'Term 2 2026', 'fee_structure_id' => $fee->id]);
    }

    public function test_prepayment_allocates_fifo_across_years_and_rolls_unused_credit_into_the_next_bill(): void
    {
        $accountant = $this->accountant();
        $student = Student::factory()->create();
        $later = $this->bill($student, 2000000, 'Term 1 2027', '2027-01-31');
        $first = $this->bill($student, 4000000, 'Term 1 2026', '2026-01-31');
        $response = $this->postJson('/api/v1/accountant/payments', ['student_id' => $student->id, 'amount' => 6040000, 'payment_method' => 'cash'])->assertCreated();
        $this->assertSame('paid', $first->fresh()->status);
        $this->assertSame('paid', $later->fresh()->status);
        $this->assertSame([$first->id, $later->id], Transaction::where('type', 'bill_allocation')->orderBy('id')->pluck('invoice_id')->all());
        $summary = app(BillingService::class)->summary($student);
        $this->assertEquals(40000, $summary['net_balance']);
        $this->assertSame('overpaid', $summary['balance_status']);
        $new = app(InvoiceService::class)->createInvoice(['student_id' => $student->id, 'amount' => 50000, 'period' => 'Term 2 2027', 'due_date' => '2027-06-30'], $accountant);
        $this->assertSame('partial', $new->status);
        $this->assertSame('40000.00', $new->amount_paid);
        $this->assertEquals(-10000, app(BillingService::class)->summary($student)['net_balance']);
        $this->assertEquals(6040000, Transaction::where('type', 'payment')->sum('amount'));
        $this->assertDatabaseCount('student_payments', 1);
        $this->getJson('/api/v1/accountant/billing?period=Term%202%202027')->assertOk()->assertJsonPath('summary.total_payments', 40000)->assertJsonPath('data.0.payment_ids.0', $response->json('data.control_number'));
    }

    public function test_partial_exact_and_decimal_payments_keep_balances_and_revenue_correct(): void
    {
        $this->accountant();
        $student = Student::factory()->create();
        $bill = $this->bill($student, 6000000.30, 'Term 1 2026', '2026-01-31');
        $this->assertEquals(-6000000.30, app(BillingService::class)->summary($student)['net_balance']);
        foreach ([0.10, 0.20] as $amount) {
            $this->postJson('/api/v1/accountant/payments', ['student_id' => $student->id, 'amount' => $amount, 'payment_method' => 'cash'])->assertCreated();
        }
        $this->assertSame('6000000.00', $bill->fresh()->balance);
        $this->assertSame('partial', $bill->fresh()->status);
        $this->postJson('/api/v1/accountant/payments', ['student_id' => $student->id, 'amount' => 6000000, 'payment_method' => 'cash'])->assertCreated();
        $this->assertSame('paid', $bill->fresh()->status);
        $this->assertEquals(0, app(BillingService::class)->summary($student)['net_balance']);
        $this->postJson('/api/v1/accountant/payments', ['student_id' => $student->id, 'amount' => 0.001, 'payment_method' => 'cash'])->assertUnprocessable();
    }

    public function test_parent_cannot_self_confirm_and_accountant_confirmation_is_idempotent(): void
    {
        $parent = User::factory()->role('parent')->create();
        $student = Student::factory()->create();
        $parent->children()->attach($student->id);
        $bill = $this->bill($student, 100, 'Term 1 2026', '2026-01-31');
        Sanctum::actingAs($parent);
        $control = $this->postJson('/api/v1/parent/payments/control-number', ['student_id' => $student->id, 'amount' => 140, 'payment_method' => 'bank'])->assertCreated();
        $id = $control->json('data.id');
        $this->postJson('/api/v1/parent/payments/'.$id.'/pay', ['payment_method' => 'bank'])->assertUnprocessable();
        $this->assertSame('unpaid', $bill->fresh()->status);
        $this->postJson('/api/v1/accountant/control-numbers/'.$id.'/confirm', ['payment_method' => 'bank'])->assertForbidden();
        $this->accountant();
        $payload = ['payment_method' => 'bank', 'reference' => 'BANKRECEIPT001'];
        $this->postJson('/api/v1/accountant/control-numbers/'.$id.'/confirm', $payload)->assertOk();
        $this->postJson('/api/v1/accountant/control-numbers/'.$id.'/confirm', $payload)->assertOk();
        $this->assertSame(1, Transaction::where('type', 'payment')->count());
        $this->assertEquals(40, app(BillingService::class)->summary($student)['credit_balance']);
        $this->postJson('/api/v1/accountant/payments', ['student_id' => $student->id, 'amount' => 140, ...$payload])->assertUnprocessable()->assertJsonValidationErrors('reference');
        $this->assertDatabaseCount('student_payments', 1);
    }

    public function test_sibling_credit_transfer_conserves_money_and_cannot_cross_families(): void
    {
        $this->accountant();
        $parent = User::factory()->role('parent')->create();
        $source = Student::factory()->create();
        $target = Student::factory()->create();
        $stranger = Student::factory()->create();
        $parent->children()->attach([$source->id, $target->id]);
        $targetBill = $this->bill($target, 100, 'Term 1 2026', '2026-01-31');
        $this->postJson('/api/v1/accountant/payments', ['student_id' => $source->id, 'amount' => 140, 'payment_method' => 'cash'])->assertCreated();
        Sanctum::actingAs($parent);
        $payload = ['source_student_id' => $source->id, 'target_student_id' => $target->id, 'amount' => 100, 'reason' => 'Sibling tuition', 'request_id' => '122abdb7-f422-454f-9eab-e941d7622f9c'];
        $this->postJson('/api/v1/parent/billing/transfer-credit', $payload)->assertOk();
        $this->postJson('/api/v1/parent/billing/transfer-credit', $payload)->assertOk();
        $this->assertSame(1, Transaction::where('type', 'credit_transfer_out')->count());
        $this->assertSame('paid', $targetBill->fresh()->status);
        $this->assertEquals(40, app(BillingService::class)->summary($source)['credit_balance']);
        $this->assertEquals(0, app(BillingService::class)->summary($target)['net_balance']);
        $this->postJson('/api/v1/parent/billing/transfer-credit', array_replace($payload, ['amount' => 41]))->assertUnprocessable();
        $this->postJson('/api/v1/parent/billing/transfer-credit', array_replace($payload, ['target_student_id' => $stranger->id, 'amount' => 10]))->assertForbidden();
        $this->assertEquals(140, StudentPayment::where('status', 'completed')->sum('amount'));
        $this->assertEquals(Transaction::where('type', 'credit_transfer_out')->sum('amount'), Transaction::where('type', 'credit_transfer_in')->sum('amount'));
    }

    public function test_student_sees_only_own_bills_and_can_request_prepayment_without_any_existing_bill(): void
    {
        $user = User::factory()->role('student')->create();
        $student = Student::factory()->create(['user_id' => $user->id]);
        $other = Student::factory()->create();
        $foreignBill = $this->bill($other, 100, 'Term 1 2026', '2026-01-31');
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/student/billing')->assertOk()->assertJsonCount(0, 'data.invoices')->assertJsonPath('data.summary.net_balance', 0);
        $this->postJson('/api/v1/student/billing/control-number', ['amount' => 1000, 'payment_method' => 'bank'])->assertCreated()->assertJsonPath('data.student_id', $student->id)->assertJsonPath('data.status', 'pending');
        $this->postJson('/api/v1/student/billing/control-number', ['amount' => 100, 'invoice_id' => $foreignBill->id, 'payment_method' => 'bank'])->assertNotFound();
    }

    public function test_billing_reports_and_manual_payments_cannot_cross_schools(): void
    {
        $this->accountant();
        $student = Student::factory()->create();
        $this->bill($student, 100, 'Term 1 2026', '2026-01-31');
        $other = School::factory()->create();
        $foreign = app(SchoolContext::class)->run($other->id, function (): Student {
            $student = Student::factory()->create();
            $this->bill($student, 999999, 'Term 1 2026', '2026-01-31');

            return $student;
        });
        $this->getJson('/api/v1/accountant/billing')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('summary.total_bills', 100)->assertJsonPath('summary.owing_students', 1);
        $this->postJson('/api/v1/accountant/payments', ['student_id' => $foreign->id, 'amount' => 100, 'payment_method' => 'cash'])->assertUnprocessable();
    }

    public function test_retried_cash_receipt_does_not_duplicate_money_and_report_can_find_credit_without_bills(): void
    {
        $this->accountant();
        $student = Student::factory()->create(['full_name' => 'Advance Student']);
        $data = ['student_id' => $student->id, 'amount' => 40000, 'payment_method' => 'cash', 'request_id' => '499f7db0-5b37-423f-a80e-8565eaacfd77'];
        $first = $this->postJson('/api/v1/accountant/payments', $data)->assertCreated();
        $this->postJson('/api/v1/accountant/payments', $data)->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));
        $this->postJson('/api/v1/accountant/payments', array_replace($data, ['amount' => 50000]))->assertUnprocessable()->assertJsonValidationErrors('request_id');
        $this->assertDatabaseCount('student_payments', 1);
        $this->getJson('/api/v1/accountant/billing?search=Advance')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('summary.total_overpaid', 40000)->assertJsonPath('summary.total_payments', 40000);
        $this->artisan('billing:reconcile', ['--check' => true])->assertSuccessful();
    }

    public function test_parent_child_and_student_profile_summaries_agree_after_confirmation(): void
    {
        $accountant = $this->accountant();
        $user = User::factory()->role('student')->create();
        $child = Student::factory()->create(['user_id' => $user->id]);
        $parent = User::factory()->role('parent')->create();
        $parent->children()->attach($child->id);
        $this->bill($child, 100, 'Term 1 2026', '2026-01-31');
        $this->postJson('/api/v1/accountant/payments', ['student_id' => $child->id, 'amount' => 140, 'payment_method' => 'cash'])->assertCreated();
        Sanctum::actingAs($parent);
        $this->getJson('/api/v1/parent/children/'.$child->id.'/invoices')->assertOk()->assertJsonPath('summary.net_balance', 40);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/student/billing')->assertOk()->assertJsonPath('data.summary.net_balance', 40)->assertJsonPath('data.invoices.0.period', 'Term 1 2026');
        $this->getJson('/api/v1/student/profile')->assertOk()->assertJsonPath('financial_summary.net_balance', 40);
        $this->getJson('/api/v1/student/dashboard')->assertOk()->assertJsonPath('data.financial_summary.net_balance', 40);
        $this->artisan('billing:reconcile', ['--check' => true])->assertSuccessful();
    }

    public function test_external_receipt_numbers_are_unique_within_the_school_and_can_repeat_in_another_school(): void
    {
        $this->accountant();
        $first = Student::factory()->create();
        $this->postJson('/api/v1/accountant/payments', ['student_id' => $first->id, 'amount' => 100, 'payment_method' => 'bank', 'reference' => 'R/2026/01'])->assertCreated();
        $school = School::factory()->create();
        [$other, $accountant] = app(SchoolContext::class)->run($school->id, fn () => [Student::factory()->create(), User::factory()->role('accountant')->create()]);
        Sanctum::actingAs($accountant);
        $this->postJson('/api/v1/accountant/payments', ['student_id' => $other->id, 'amount' => 200, 'payment_method' => 'bank', 'reference' => 'R/2026/01'])->assertCreated();
        $this->postJson('/api/v1/accountant/payments', ['student_id' => $other->id, 'amount' => 200, 'payment_method' => 'bank', 'reference' => 'R/2026/01'])->assertUnprocessable();
        $this->assertDatabaseCount('student_payments', 2);
    }

    public function test_students_with_billing_records_are_retained_instead_of_erasing_financial_history(): void
    {
        $student = Student::factory()->create();
        $bill = $this->bill($student, 100, 'Term 1 2026', '2026-01-31');
        Sanctum::actingAs(User::factory()->role('super_admin')->create());
        $this->deleteJson('/api/v1/admin/students/'.$student->id)->assertUnprocessable();
        $this->assertDatabaseHas('students', ['id' => $student->id]);
        $this->assertDatabaseHas('invoices', ['id' => $bill->id, 'amount' => 100]);
        $this->putJson('/api/v1/admin/students/'.$student->id, ['is_active' => false])->assertOk();
        $this->assertDatabaseHas('invoices', ['id' => $bill->id]);
    }
}
