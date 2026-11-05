<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParentSchoolTest extends TestCase
{
    use RefreshDatabase;

    private function parentFor(School $school): User
    {
        return app(SchoolContext::class)->run($school->id, fn () => User::factory()->role('parent')->create());
    }

    public function test_parent_sees_registered_schools_with_own_school_first_and_only_directory_information(): void
    {
        $own = School::factory()->create(['name' => 'Z Parent School']);
        $other = School::factory()->create(['name' => 'A Other School']);
        app(SchoolContext::class)->run($other->id, function (): void {
            User::factory()->role('super_admin')->create(['full_name' => 'Private Administrator', 'email' => 'private-admin@example.test']);
            Student::factory()->create(['full_name' => 'Private Child']);
        });
        Sanctum::actingAs($this->parentFor($own));

        $response = $this->getJson('/api/v1/parent/schools')->assertOk()
            ->assertJsonPath('data.0.id', $own->id)->assertJsonPath('data.0.is_my_school', true)
            ->assertJsonFragment(['id' => $other->id, 'name' => $other->name, 'code' => $other->code, 'is_my_school' => false]);
        $this->assertSame(['id', 'name', 'code', 'is_my_school'], array_keys($response->json('data.0')));
        $response->assertDontSee('Private Administrator')->assertDontSee('private-admin@example.test')->assertDontSee('Private Child');
    }

    public function test_opening_another_school_is_refused_and_parent_still_sees_only_their_linked_child(): void
    {
        $own = School::factory()->create();
        $other = School::factory()->create();
        $parent = $this->parentFor($own);
        $child = app(SchoolContext::class)->run($own->id, function () use ($parent): Student {
            $child = Student::factory()->create();
            Student::factory()->create();
            $parent->children()->attach($child->id, ['relationship' => 'mother', 'is_primary' => true]);

            return $child;
        });
        $foreignChild = app(SchoolContext::class)->run($other->id, fn () => Student::factory()->create());
        Sanctum::actingAs($parent);

        $this->getJson('/api/v1/parent/schools/'.$other->id)->assertForbidden()->assertJsonPath('message', 'You have no access to this school.');
        $this->getJson('/api/v1/parent/schools/'.$own->id)->assertOk()->assertJsonPath('data.school.id', $own->id)->assertJsonPath('data.dashboard_path', '/parent/dashboard');
        $this->getJson('/api/v1/parent/children')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $child->id);
        $this->getJson('/api/v1/parent/children/'.$foreignChild->id)->assertNotFound();
        $this->getJson('/api/v1/parent/dashboard')->assertOk()->assertJsonPath('data.stats.children_count', 1);
        $this->assertSame($own->id, $parent->fresh()->school_id);
    }

    public function test_school_directory_requires_login_and_the_parent_role(): void
    {
        $school = School::factory()->create();
        $this->getJson('/api/v1/parent/schools')->assertUnauthorized();
        $this->getJson('/api/v1/parent/schools/'.$school->id)->assertUnauthorized();
        $teacher = app(SchoolContext::class)->run($school->id, fn () => User::factory()->role('teacher')->create());
        Sanctum::actingAs($teacher);
        $this->getJson('/api/v1/parent/schools')->assertForbidden();
        $this->getJson('/api/v1/parent/schools/'.$school->id)->assertForbidden();
    }

    public function test_directory_search_and_pagination_preserve_school_identity(): void
    {
        $own = School::factory()->create();
        School::factory()->count(25)->create();
        Sanctum::actingAs($this->parentFor($own));

        $this->getJson('/api/v1/parent/schools')->assertOk()->assertJsonCount(24, 'data')->assertJsonPath('meta.total', 27)->assertJsonPath('data.0.id', $own->id);
        $this->getJson('/api/v1/parent/schools?page=2')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/v1/parent/schools?search='.urlencode($own->code))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_my_school', true);
    }
}
