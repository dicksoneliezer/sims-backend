<?php

namespace Tests\Feature;

use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->role('super_admin')->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_admin_can_manage_parents_and_link_children(): void
    {
        $this->actingAsSuperAdmin();
        $student = Student::factory()->create(['full_name' => 'Amina Hassan']);
        $secondStudent = Student::factory()->create(['full_name' => 'Neema Joseph']);

        $created = $this->postJson('/api/v1/admin/parents', [
            'username' => 'newparent',
            'full_name' => 'Hassan Ali',
            'email' => 'hassan@example.com',
            'phone' => '+255712345001',
            'password' => 'secret123',
            'children' => [
                ['student_id' => $student->id, 'relationship' => 'father', 'is_primary' => true],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.role', 'parent')
            ->assertJsonPath('data.children_count', 1)
            ->assertJsonPath('data.children.0.full_name', 'Amina Hassan');

        $parent = User::where('username', 'newparent')->first();
        $this->assertTrue(Hash::check('secret123', $parent->password));

        $this->getJson('/api/v1/admin/parents?search=Hassan')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.children_count', 1);

        $this->putJson("/api/v1/admin/parents/{$parent->id}", [
            'full_name' => 'Hassan Updated',
            'children' => [
                ['student_id' => $student->id, 'relationship' => 'father', 'is_primary' => true],
                ['student_id' => $secondStudent->id, 'relationship' => 'guardian', 'is_primary' => false],
            ],
        ])->assertOk()
            ->assertJsonPath('data.full_name', 'Hassan Updated')
            ->assertJsonPath('data.children_count', 2);

        $this->getJson("/api/v1/admin/parents/{$parent->id}")
            ->assertOk()
            ->assertJsonPath('data.children_count', 2);

        $this->deleteJson("/api/v1/admin/parents/{$parent->id}")->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $parent->id]);
        $this->assertDatabaseMissing('parent_students', ['parent_id' => $parent->id]);
    }

    public function test_non_parent_user_cannot_be_managed_as_parent(): void
    {
        $this->actingAsSuperAdmin();
        $teacher = User::factory()->role('teacher')->create();

        $this->getJson("/api/v1/admin/parents/{$teacher->id}")->assertNotFound();
        $this->putJson("/api/v1/admin/parents/{$teacher->id}", ['full_name' => 'Nope'])->assertNotFound();
        $this->deleteJson("/api/v1/admin/parents/{$teacher->id}")->assertNotFound();
    }

    public function test_only_super_admin_can_manage_parents_and_accountants(): void
    {
        $accountant = User::factory()->role('accountant')->create();
        Sanctum::actingAs($accountant);

        $this->getJson('/api/v1/admin/parents')->assertForbidden();
        $this->getJson('/api/v1/admin/accountants')->assertForbidden();
    }

    public function test_admin_can_manage_accountants(): void
    {
        $this->actingAsSuperAdmin();

        $created = $this->postJson('/api/v1/admin/accountants', [
            'username' => 'newaccountant',
            'full_name' => 'New Accountant',
            'email' => 'accountant@example.com',
            'password' => 'secret123',
            'nin_number' => '19900101-12345-00001-01',
        ])->assertCreated()
            ->assertJsonPath('data.role', 'accountant')
            ->assertJsonPath('data.username', 'newaccountant');

        $accountant = User::where('username', 'newaccountant')->first();

        $this->putJson("/api/v1/admin/accountants/{$accountant->id}", ['full_name' => 'Renamed Accountant'])
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Renamed Accountant');

        $this->getJson("/api/v1/admin/accountants/{$accountant->id}")
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Renamed Accountant');

        $this->deleteJson("/api/v1/admin/accountants/{$accountant->id}")->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $accountant->id]);
    }

    public function test_admin_can_manage_fee_structures_and_guard_deletion(): void
    {
        $this->actingAsSuperAdmin();
        $class = SchoolClass::create(['name' => 'Form 1A', 'level' => 'secondary', 'capacity' => 40]);
        $student = Student::factory()->create(['class_id' => $class->id, 'class' => 'Form 1A']);

        $created = $this->postJson('/api/v1/admin/fee-structure', [
            'name' => 'Form 1A Term 1 Tuition',
            'class_id' => $class->id,
            'school_level' => 'secondary',
            'term' => 'Term 1',
            'academic_year' => '2026',
            'amount' => 500000,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Form 1A Term 1 Tuition')
            ->assertJsonPath('data.school_class.name', 'Form 1A');

        $feeId = $created->json('data.id');

        $this->getJson('/api/v1/admin/fee-structure?search=Tuition')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->putJson("/api/v1/admin/fee-structure/{$feeId}", ['amount' => 550000])
            ->assertOk()
            ->assertJsonPath('data.amount', '550000.00');

        Invoice::create([
            'invoice_number' => 'INV-2026-9001',
            'student_id' => $student->id,
            'fee_structure_id' => $feeId,
            'amount' => 550000,
            'amount_paid' => 0,
            'balance' => 550000,
            'status' => 'unpaid',
        ]);

        $this->deleteJson("/api/v1/admin/fee-structure/{$feeId}")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_fee_structure_can_be_deleted_when_unused(): void
    {
        $this->actingAsSuperAdmin();
        $fee = FeeStructure::create([
            'name' => 'Unused Fee',
            'amount' => 1000,
            'is_active' => true,
        ]);

        $this->deleteJson("/api/v1/admin/fee-structure/{$fee->id}")->assertOk();
        $this->assertDatabaseMissing('fee_structure', ['id' => $fee->id]);
    }
}
