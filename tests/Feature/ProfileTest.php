<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_and_update_profile(): void
    {
        $user = User::factory()->role('teacher')->create(['full_name' => 'Old Name']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Old Name');

        $this->putJson('/api/v1/profile', [
            'full_name' => 'New Name',
            'email' => 'new-profile@sims.co.tz',
            'phone' => '+255712345678',
        ])->assertOk()
            ->assertJsonPath('data.full_name', 'New Name')
            ->assertJsonPath('data.email', 'new-profile@sims.co.tz');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'full_name' => 'New Name']);
    }

    public function test_user_can_upload_avatar_and_change_password(): void
    {
        Storage::fake('public');
        $user = User::factory()->role('teacher')->create([
            'password' => Hash::make('oldpassword'),
        ]);
        Sanctum::actingAs($user);

        $this->post('/api/v1/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotNull($user->fresh()->profile_pic);

        $this->postJson('/api/v1/profile/password', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ])->assertOk()
            ->assertJsonPath('message', 'Password changed. Please log in again.');

        $this->assertTrue(Hash::check('newpassword', $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_profile_update_validates_email_uniqueness_and_password_confirmation(): void
    {
        $user = User::factory()->role('student')->create();
        $other = User::factory()->role('student')->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/profile', ['email' => $other->email])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->postJson('/api/v1/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->postJson('/api/v1/profile/password', [
            'current_password' => 'password',
            'password' => 'newpassword',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }
}
