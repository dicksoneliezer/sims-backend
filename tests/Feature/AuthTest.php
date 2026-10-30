<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->role('teacher')->create([
            'username' => 'teacher1',
            'password' => 'secret123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'teacher1',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.username', 'teacher1')
            ->assertJsonPath('data.user.role', 'teacher')
            ->assertJsonStructure(['data' => ['token', 'token_type', 'user']]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'login_attempts' => 0,
        ]);
        $this->assertNotNull($user->fresh()->last_login);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create(['username' => 'teacher1']);

        $this->postJson('/api/v1/auth/login', [
            'username' => 'teacher1',
            'password' => 'wrong-password',
        ])->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->inactive()->create([
            'username' => 'inactive1',
            'password' => 'secret123',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'username' => 'inactive1',
            'password' => 'secret123',
        ])->assertStatus(403);
    }

    public function test_account_locks_after_repeated_failures(): void
    {
        $user = User::factory()->create([
            'username' => 'locked1',
            'password' => 'secret123',
            'login_attempts' => User::MAX_LOGIN_ATTEMPTS - 1,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'username' => 'locked1',
            'password' => 'wrong-password',
        ])->assertStatus(401);

        $user->refresh();

        $this->assertTrue($user->isLocked());

        $this->postJson('/api/v1/auth/login', [
            'username' => 'locked1',
            'password' => 'secret123',
        ])->assertStatus(423);
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = User::factory()->role('parent')->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.role', 'parent')
            ->assertJsonMissingPath('data.password');
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token');

        $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_role_middleware_blocks_other_roles(): void
    {
        $teacher = User::factory()->role('teacher')->create();
        Sanctum::actingAs($teacher);

        $this->getJson('/api/v1/admin/dashboard')->assertStatus(403);
        $this->getJson('/api/v1/teacher/dashboard')->assertOk();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }
}
