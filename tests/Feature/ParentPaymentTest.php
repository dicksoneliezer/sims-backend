<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Student;
use App\Models\StudentPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParentPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function makeFamily(): array
    {
        $parent = User::factory()->role('parent')->create(['full_name' => 'Hassan Ali']);
        $child = Student::factory()->create(['full_name' => 'Amina Hassan']);
        $otherChild = Student::factory()->create(['full_name' => 'Neema Joseph']);
        $parent->children()->attach($child->id, ['relationship' => 'father', 'is_primary' => true]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-2026-5001',
            'student_id' => $child->id,
            'amount' => 1000,
            'amount_paid' => 0,
            'balance' => 1000,
            'status' => 'unpaid',
            'due_date' => now()->addDays(10)->toDateString(),
        ]);

        return [$parent, $child, $otherChild, $invoice];
    }

    public function test_parent_can_view_payment_methods_including_cash_at_the_school_office(): void
    {
        [$parent] = $this->makeFamily();
        Sanctum::actingAs($parent);

        $response = $this->getJson('/api/v1/parent/payments/methods')
            ->assertOk()
            ->assertJsonCount(5, 'data');

        $codes = collect($response->json('data'))->pluck('code')->all();

        $this->assertContains('mpesa', $codes);
        $this->assertContains('bank', $codes);
        $this->assertContains('cash', $codes);
    }

    public function test_parent_can_generate_control_number_only_for_own_child(): void
    {
        [$parent, $child, $otherChild, $invoice] = $this->makeFamily();
        Sanctum::actingAs($parent);

        $created = $this->postJson('/api/v1/parent/payments/control-number', [
            'student_id' => $child->id,
            'invoice_id' => $invoice->id,
            'amount' => 400,
            'payment_method' => 'mpesa',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.amount', '400.00')
            ->assertJsonStructure(['data' => ['control_number', 'expires_at', 'payment_instructions' => ['mpesa']]]);

        $this->assertStringStartsWith('CTRL-', $created->json('data.control_number'));

        $this->postJson('/api/v1/parent/payments/control-number', [
            'student_id' => $otherChild->id,
            'invoice_id' => $invoice->id,
            'amount' => 100,
            'payment_method' => 'mpesa',
        ])->assertForbidden();

        $this->postJson('/api/v1/parent/payments/control-number', [
            'student_id' => $child->id,
            'invoice_id' => $invoice->id,
            'amount' => 2000,
            'payment_method' => 'mpesa',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_accountant_confirms_parent_payment_and_invoice_is_updated(): void
    {
        [$parent, $child, , $invoice] = $this->makeFamily();
        Sanctum::actingAs($parent);

        $control = $this->postJson('/api/v1/parent/payments/control-number', [
            'student_id' => $child->id,
            'invoice_id' => $invoice->id,
            'amount' => 400,
            'payment_method' => 'bank',
        ])->assertCreated();

        $paymentId = $control->json('data.id');

        $this->postJson("/api/v1/parent/payments/{$paymentId}/pay", ['payment_method' => 'bank'])->assertUnprocessable()->assertJsonValidationErrors('payment');
        Sanctum::actingAs(User::factory()->role('accountant')->create());
        $paid = $this->postJson("/api/v1/accountant/control-numbers/{$paymentId}/confirm", [
            'payment_method' => 'bank',
        ])->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.transaction_ref', fn ($value) => is_string($value) && str_starts_with($value, 'BANK-'));

        $this->assertNotNull($paid->json('data.completed_at'));
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'amount_paid' => 400,
            'balance' => 600,
            'status' => 'partial',
        ]);

        Sanctum::actingAs($parent);
        $this->getJson('/api/v1/parent/payments/balance')
            ->assertOk()
            ->assertJsonPath('data.total_outstanding', 600)
            ->assertJsonPath('data.children.0.outstanding', 600);

        $this->getJson('/api/v1/parent/payments/history')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'completed');

        $controlNumber = $control->json('data.control_number');

        $this->getJson("/api/v1/parent/payments/status/{$controlNumber}")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.receipt_url', "/api/v1/parent/payments/{$paymentId}/receipt");

        $this->getJson("/api/v1/parent/payments/{$paymentId}/receipt")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.control_number', $controlNumber);
    }

    public function test_parent_can_cancel_pending_control_number(): void
    {
        [$parent, $child, , $invoice] = $this->makeFamily();
        Sanctum::actingAs($parent);

        $payment = StudentPayment::create([
            'student_id' => $child->id,
            'invoice_id' => $invoice->id,
            'control_number' => 'CTRL-CANCEL-0001',
            'amount' => 100,
            'status' => 'pending',
            'payment_method' => 'mpesa',
            'created_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);

        $this->postJson("/api/v1/parent/payments/{$payment->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->postJson("/api/v1/parent/payments/{$payment->id}/cancel")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment');
    }

    public function test_expired_control_number_cannot_be_paid(): void
    {
        [$parent, $child, , $invoice] = $this->makeFamily();
        Sanctum::actingAs($parent);

        $payment = StudentPayment::create([
            'student_id' => $child->id,
            'invoice_id' => $invoice->id,
            'control_number' => 'CTRL-EXPIRED-0001',
            'amount' => 100,
            'status' => 'pending',
            'payment_method' => 'mpesa',
            'created_at' => now()->subDays(10),
            'expires_at' => now()->subDays(3),
        ]);

        $this->postJson("/api/v1/parent/payments/{$payment->id}/pay", [
            'payment_method' => 'mpesa',
            'phone_number' => '+255712345001',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('payment');
    }

    public function test_parent_cannot_access_another_parents_payment(): void
    {
        [$parent, $child, , $invoice] = $this->makeFamily();
        $otherParent = User::factory()->role('parent')->create();

        $payment = StudentPayment::create([
            'student_id' => $child->id,
            'invoice_id' => $invoice->id,
            'control_number' => 'CTRL-OTHER-0001',
            'amount' => 100,
            'status' => 'pending',
            'payment_method' => 'mpesa',
            'created_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);

        Sanctum::actingAs($otherParent);

        $this->getJson("/api/v1/parent/payments/{$payment->id}")->assertForbidden();
        $this->postJson("/api/v1/parent/payments/{$payment->id}/pay", [
            'payment_method' => 'mpesa',
            'phone_number' => '+255712345001',
        ])->assertForbidden();

        $this->getJson('/api/v1/parent/payments')->assertOk()->assertJsonPath('meta.total', 0);

        Sanctum::actingAs($parent);

        $this->getJson('/api/v1/parent/payments')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_parent_can_view_child_invoices_and_payments(): void
    {
        [$parent, $child, $otherChild, $invoice] = $this->makeFamily();
        Sanctum::actingAs($parent);

        $this->getJson("/api/v1/parent/children/{$child->id}/invoices")
            ->assertOk()
            ->assertJsonPath('summary.total_outstanding', 1000)
            ->assertJsonPath('data.0.invoice_number', 'INV-2026-5001');

        $this->getJson("/api/v1/parent/children/{$otherChild->id}/invoices")->assertForbidden();

        StudentPayment::create([
            'student_id' => $child->id,
            'invoice_id' => $invoice->id,
            'control_number' => 'CTRL-CHILD-0001',
            'amount' => 100,
            'status' => 'pending',
            'payment_method' => 'mpesa',
            'created_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);

        $this->getJson("/api/v1/parent/children/{$child->id}/payments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.control_number', 'CTRL-CHILD-0001');
    }
}
