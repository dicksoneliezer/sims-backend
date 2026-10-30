<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->role('super_admin')->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_admin_can_update_school_settings(): void
    {
        $this->actingAsAdmin();

        $this->putJson('/api/v1/admin/settings/school', [
            'school_name' => 'Test Secondary School',
            'school_motto' => 'Learn and Grow',
            'academic_year' => '2026',
            'current_term' => 'Term 2',
            'currency' => 'TZS',
        ])->assertOk()
            ->assertJsonPath('data.school_name', 'Test Secondary School')
            ->assertJsonPath('data.current_term', 'Term 2');

        $this->assertDatabaseHas('school_settings', [
            'key' => 'school_name',
            'value' => 'Test Secondary School',
            'group' => 'school',
        ]);

        $this->getJson('/api/v1/admin/settings/school')
            ->assertOk()
            ->assertJsonPath('data.school_motto', 'Learn and Grow');
    }

    public function test_admin_can_manage_grading_system(): void
    {
        $this->actingAsAdmin();

        $created = $this->postJson('/api/v1/admin/settings/grading', [
            'grade' => 'A',
            'min_marks' => 80,
            'max_marks' => 100,
            'points' => 1,
            'remarks' => 'Excellent',
        ])->assertCreated()
            ->assertJsonPath('data.grade', 'A');

        $id = $created->json('data.id');

        $this->putJson("/api/v1/admin/settings/grading/{$id}", [
            'grade' => 'A',
            'min_marks' => 75,
            'max_marks' => 100,
            'points' => 1,
            'remarks' => 'Distinction',
        ])->assertOk()
            ->assertJsonPath('data.min_marks', '75.00');

        $this->getJson('/api/v1/admin/settings/grading')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->deleteJson("/api/v1/admin/settings/grading/{$id}")->assertOk();
        $this->assertDatabaseCount('grading_system', 0);
    }

    public function test_admin_can_manage_academic_calendar(): void
    {
        $this->actingAsAdmin();

        $created = $this->postJson('/api/v1/admin/settings/calendar', [
            'title' => 'Sports Day',
            'description' => 'Annual inter-house sports.',
            'start_date' => '2026-08-20',
            'end_date' => '2026-08-20',
            'type' => 'event',
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Sports Day')
            ->assertJsonPath('data.type', 'event');

        $id = $created->json('data.id');

        $this->putJson("/api/v1/admin/settings/calendar/{$id}", [
            'title' => 'Annual Sports Day',
            'description' => 'Rescheduled event.',
            'start_date' => '2026-08-21',
            'end_date' => '2026-08-21',
            'type' => 'event',
        ])->assertOk()
            ->assertJsonPath('data.title', 'Annual Sports Day');

        $this->getJson('/api/v1/admin/settings/calendar?type=event')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->deleteJson("/api/v1/admin/settings/calendar/{$id}")->assertOk();
        $this->assertDatabaseCount('school_calendar', 0);
    }

    public function test_non_admin_cannot_access_settings(): void
    {
        $teacher = User::factory()->role('teacher')->create();
        Sanctum::actingAs($teacher);

        $this->getJson('/api/v1/admin/settings/school')->assertForbidden();
    }
}
