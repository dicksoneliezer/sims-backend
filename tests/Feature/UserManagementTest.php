<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->role('super_admin')->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_super_admin_can_list_users_with_pagination(): void
    {
        $this->actingAsSuperAdmin();
        User::factory()->count(3)->create();

        $this->getJson('/api/v1/admin/users?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_super_admin_can_create_user(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/v1/admin/users', [
            'username' => 'newteacher',
            'full_name' => 'New Teacher',
            'email' => 'newteacher@sims.co.tz',
            'role' => 'teacher',
            'password' => 'secret123',
        ])->assertCreated()
            ->assertJsonPath('data.username', 'newteacher')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('users', [
            'username' => 'newteacher',
            'role' => 'teacher',
        ]);
    }

    public function test_create_user_validates_role_and_unique_username(): void
    {
        $this->actingAsSuperAdmin();
        User::factory()->create(['username' => 'existing']);

        $this->postJson('/api/v1/admin/users', [
            'username' => 'existing',
            'full_name' => 'Duplicate',
            'role' => 'not-a-role',
            'password' => 'short',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['username', 'role', 'password']);
    }

    public function test_super_admin_can_update_user(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->role('teacher')->create();

        $this->putJson("/api/v1/admin/users/{$user->id}", [
            'full_name' => 'Updated Name',
            'role' => 'academic_teacher',
        ])->assertOk()
            ->assertJsonPath('data.full_name', 'Updated Name')
            ->assertJsonPath('data.role', 'academic_teacher');
    }

    public function test_super_admin_can_toggle_user_status_and_revoke_tokens(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->create();
        $user->createToken('auth-token');

        $this->patchJson("/api/v1/admin/users/{$user->id}/toggle-active")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_super_admin_cannot_deactivate_self(): void
    {
        $admin = $this->actingAsSuperAdmin();

        $this->patchJson("/api/v1/admin/users/{$admin->id}/toggle-active")
            ->assertStatus(422);
    }

    public function test_super_admin_can_reset_user_password(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->create();

        $this->postJson("/api/v1/admin/users/{$user->id}/reset-password", [
            'password' => 'newsecret123',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'newsecret123',
        ])->assertOk();
    }

    public function test_super_admin_can_delete_another_user(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->create();

        $this->deleteJson("/api/v1/admin/users/{$user->id}")->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        $accountant = User::factory()->role('accountant')->create();
        Sanctum::actingAs($accountant);

        $this->getJson('/api/v1/admin/users')->assertStatus(403);
    }
}
