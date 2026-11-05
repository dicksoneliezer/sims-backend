<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SchoolIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function userFor(School $school, string $role = 'super_admin'): User
    {
        return app(SchoolContext::class)->run($school->id, fn () => User::factory()->role($role)->create());
    }

    private function studentFor(School $school): Student
    {
        return app(SchoolContext::class)->run($school->id, fn () => Student::factory()->create());
    }

    public function test_technical_admin_provisions_a_school_and_its_admin_with_the_same_school_id(): void
    {
        $tech = User::factory()->role('system_admin')->create();
        Sanctum::actingAs($tech);

        $response = $this->postJson('/api/v1/system/schools', [
            'name' => 'New School', 'admin' => ['full_name' => 'School Administrator', 'username' => 'new-school-admin', 'password' => 'securepassword', 'password_confirmation' => 'securepassword', 'role' => 'system_admin', 'school_id' => 99999],
        ])->assertCreated()->assertJsonPath('data.admin.role', 'super_admin')->assertJsonMissingPath('data.admin.password');

        $schoolId = $response->json('data.school.id');
        $response->assertJsonPath('data.admin.school_id', $schoolId);
        $this->assertDatabaseHas('schools', ['id' => $schoolId, 'name' => 'New School']);
        $this->assertDatabaseHas('school_settings', ['school_id' => $schoolId, 'key' => 'school_name', 'value' => 'New School']);
        $this->assertDatabaseHas('users', ['id' => $response->json('data.admin.id'), 'school_id' => $schoolId, 'role' => 'super_admin']);
    }

    public static function schoolRoles(): array
    {
        return array_combine(array_diff(User::ROLES, ['system_admin']), array_map(fn ($role) => [$role], array_diff(User::ROLES, ['system_admin'])));
    }

    #[DataProvider('schoolRoles')]
    public function test_school_users_cannot_register_schools_or_list_other_school_administrators(string $role): void
    {
        Sanctum::actingAs($this->userFor(School::factory()->create(), $role));

        $this->getJson('/api/v1/system/schools')->assertForbidden();
        $this->postJson('/api/v1/system/schools', ['name' => 'Unauthorised School'])->assertForbidden();
        $this->assertDatabaseMissing('schools', ['name' => 'Unauthorised School']);
    }

    public function test_login_infers_the_school_from_the_account_without_a_school_id(): void
    {
        $school = School::factory()->create();
        $teacher = $this->userFor($school, 'teacher');
        app(SchoolContext::class)->set(null, false);

        $response = $this->postJson('/api/v1/auth/login', ['username' => $teacher->username, 'password' => 'password'])
            ->assertOk()->assertJsonPath('data.user.school_id', $school->id)->assertJsonPath('data.user.school.code', $school->code);
        $this->withToken($response->json('data.token'))->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.school_id', $school->id);
    }

    #[DataProvider('schoolRoles')]
    public function test_each_school_role_logs_into_its_own_dashboard_without_a_school_prompt(string $role): void
    {
        $school = School::factory()->create();
        $user = $this->userFor($school, $role);
        app(SchoolContext::class)->set(null, false);

        $response = $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'password'])
            ->assertOk()->assertJsonPath('data.user.school_id', $school->id)->assertJsonPath('data.user.dashboard_path', $user->dashboardPath());
        $this->withToken($response->json('data.token'))->getJson('/api/v1'.$user->dashboardPath().'/dashboard')->assertOk();
    }

    public function test_school_audit_detects_a_cross_school_reference(): void
    {
        $school = School::factory()->create();
        $other = School::factory()->create();
        $student = $this->studentFor($school);
        $foreignClass = app(SchoolContext::class)->run($other->id, fn () => SchoolClass::create(['name' => 'Foreign class', 'level' => 'secondary']));

        $this->artisan('school:audit')->assertSuccessful();
        DB::table('students')->where('id', $student->id)->update(['class_id' => $foreignClass->id]);
        $this->artisan('school:audit')->assertFailed();
    }

    public function test_repeated_student_numbers_use_distinct_login_names_and_keep_parent_contacts_separate(): void
    {
        $school = School::factory()->create();
        $other = School::factory()->create();
        $foreignUser = app(SchoolContext::class)->run($other->id, fn () => User::factory()->role('student')->create(['username' => 'ST001']));
        app(SchoolContext::class)->run($other->id, fn () => Student::factory()->create(['student_number' => 'ST001', 'user_id' => $foreignUser->id]));
        Sanctum::actingAs($this->userFor($school));
        $response = $this->postJson('/api/v1/admin/students', ['student_number' => 'ST001', 'full_name' => 'My student', 'parent_phone' => '123456789'])
            ->assertCreated()->assertJsonPath('data.student_number', 'ST001');

        $this->assertNotSame('ST001', $response->json('data.username'));
        $this->assertDatabaseHas('users', ['id' => $response->json('data.user_id'), 'school_id' => $school->id, 'phone' => null]);
        $this->assertDatabaseHas('students', ['id' => $response->json('data.id'), 'school_id' => $school->id, 'parent_phone' => '123456789']);
        $this->postJson('/api/v1/admin/students', ['student_number' => 'ST002', 'full_name' => 'My second student', 'parent_phone' => '123456789'])->assertCreated();
    }

    public function test_school_lists_dashboards_and_searches_exclude_another_school(): void
    {
        $school = School::factory()->create();
        $other = School::factory()->create();
        $admin = $this->userFor($school);
        $this->userFor($other, 'teacher');
        $student = $this->studentFor($school);
        $foreignStudent = $this->studentFor($other);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/students')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $student->id);
        $this->getJson('/api/v1/admin/dashboard')->assertOk()->assertJsonPath('data.stats.total_students', 1)->assertJsonPath('data.stats.total_users', 1);
        $this->getJson('/api/v1/admin/students?search='.urlencode($foreignStudent->student_number))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_cross_school_read_update_and_delete_urls_return_404_without_changing_data(): void
    {
        $school = School::factory()->create();
        $other = School::factory()->create();
        $foreignUser = $this->userFor($other, 'teacher');
        $foreignStudent = $this->studentFor($other);
        Sanctum::actingAs($this->userFor($school));

        $this->getJson('/api/v1/admin/students/'.$foreignStudent->id)->assertNotFound();
        $this->putJson('/api/v1/admin/users/'.$foreignUser->id, ['full_name' => 'Tampered'])->assertNotFound();
        $this->deleteJson('/api/v1/admin/students/'.$foreignStudent->id)->assertNotFound();
        $this->assertDatabaseHas('users', ['id' => $foreignUser->id, 'full_name' => $foreignUser->full_name]);
        $this->assertDatabaseHas('students', ['id' => $foreignStudent->id]);
    }

    public function test_new_users_inherit_the_admins_school_and_cannot_supply_another_school_or_privileged_role(): void
    {
        $school = School::factory()->create();
        Sanctum::actingAs($this->userFor($school));
        $payload = ['username' => 'newteacher', 'full_name' => 'New Teacher', 'password' => 'securepassword', 'role' => 'teacher'];

        $this->postJson('/api/v1/admin/users', $payload)->assertCreated()->assertJsonPath('data.school_id', $school->id);
        $this->assertDatabaseHas('users', ['username' => 'newteacher', 'school_id' => $school->id]);
        $this->postJson('/api/v1/admin/users', [...$payload, 'username' => 'forged', 'school_id' => 99999])->assertUnprocessable();
        $this->postJson('/api/v1/admin/users', [...$payload, 'username' => 'platform', 'role' => 'system_admin'])->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->postJson('/api/v1/admin/users', [...$payload, 'username' => 'schooladmin', 'role' => 'super_admin'])->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->assertDatabaseMissing('users', ['username' => 'forged']);
        $this->assertDatabaseMissing('users', ['username' => 'platform']);
        $this->assertDatabaseMissing('users', ['username' => 'schooladmin']);
    }

    public function test_foreign_class_and_parent_child_ids_are_rejected_before_creating_accounts(): void
    {
        $school = School::factory()->create();
        $other = School::factory()->create();
        $foreignClass = app(SchoolContext::class)->run($other->id, fn () => SchoolClass::create(['name' => 'Other Class', 'level' => 'secondary']));
        $foreignStudent = $this->studentFor($other);
        Sanctum::actingAs($this->userFor($school));
        $before = DB::table('users')->count();

        $this->postJson('/api/v1/admin/students', ['full_name' => 'Forged Student', 'class_id' => $foreignClass->id])->assertUnprocessable()->assertJsonValidationErrors('class_id');
        $this->postJson('/api/v1/admin/parents', ['username' => 'forged-parent', 'full_name' => 'Forged Parent', 'password' => 'securepassword', 'children' => [['student_id' => $foreignStudent->id]]])->assertUnprocessable()->assertJsonValidationErrors('children.0.student_id');
        $this->assertSame($before, DB::table('users')->count());
        $this->assertDatabaseMissing('students', ['full_name' => 'Forged Student']);
    }

    public function test_subject_codes_and_school_settings_are_independent_between_schools(): void
    {
        $school = School::factory()->create();
        $other = School::factory()->create();
        app(SchoolContext::class)->run($other->id, fn () => Subject::create(['code' => 'MATH', 'name' => 'Other Mathematics']));
        Sanctum::actingAs($this->userFor($school));

        $this->postJson('/api/v1/admin/subjects', ['code' => 'MATH', 'name' => 'My Mathematics'])->assertCreated();
        $this->putJson('/api/v1/admin/settings/school', ['school_name' => 'My School'])->assertOk();
        $this->getJson('/api/v1/admin/subjects')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'My Mathematics');
        $this->assertDatabaseHas('school_settings', ['school_id' => $school->id, 'key' => 'school_name', 'value' => 'My School']);
        $this->assertDatabaseMissing('school_settings', ['school_id' => $other->id, 'key' => 'school_name', 'value' => 'My School']);
    }

    public function test_financial_dashboard_totals_include_only_the_logged_in_school(): void
    {
        $school = School::factory()->create();
        $other = School::factory()->create();
        foreach ([[$school, 100], [$other, 900]] as [$owner, $amount]) {
            $student = $this->studentFor($owner);
            app(SchoolContext::class)->run($owner->id, fn () => Invoice::create(['invoice_number' => 'INV-SAME', 'student_id' => $student->id, 'amount' => $amount, 'amount_paid' => 0, 'balance' => $amount, 'status' => 'unpaid']));
        }
        Sanctum::actingAs($this->userFor($school, 'accountant'));

        $this->getJson('/api/v1/accountant/dashboard')->assertOk()->assertJsonPath('data.stats.total_invoiced', 100);
        $this->getJson('/api/v1/accountant/invoices')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.amount', '100.00');
    }

    public function test_disabling_a_school_blocks_existing_tokens_and_new_logins(): void
    {
        $school = School::factory()->create();
        $teacher = $this->userFor($school, 'teacher');
        $school->update(['is_active' => false]);
        Sanctum::actingAs($teacher);

        $this->getJson('/api/v1/teacher/dashboard')->assertForbidden();
        $this->postJson('/api/v1/auth/login', ['username' => $teacher->username, 'password' => 'password'])->assertForbidden();
    }

    public function test_school_photos_require_authentication_and_cannot_be_read_by_another_school(): void
    {
        Storage::fake('local');
        $school = School::factory()->create();
        $other = School::factory()->create();
        $owner = $this->userFor($other, 'teacher');
        $path = 'schools/'.$other->id.'/avatars/photo.png';
        Storage::disk('local')->put($path, 'private-photo');
        app(SchoolContext::class)->run($other->id, fn () => $owner->forceFill(['profile_pic' => $path])->save());

        $this->get('/api/v1/school-files/avatars/'.$owner->id, ['Accept' => 'application/json'])->assertUnauthorized();
        $this->get('/api/v1/school-files/avatars/'.$owner->id)->assertUnauthorized();
        $this->get('/api/v1/system/schools')->assertUnauthorized();
        Sanctum::actingAs($this->userFor($school));
        $this->get('/api/v1/school-files/avatars/'.$owner->id)->assertNotFound();
        Sanctum::actingAs($owner);
        $response = $this->get('/api/v1/school-files/avatars/'.$owner->id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    }

    public function test_replacing_a_profile_photo_changes_its_authenticated_url_for_school_and_platform_users(): void
    {
        Storage::fake('local');
        foreach (['teacher', 'system_admin'] as $role) {
            $user = $role === 'system_admin' ? User::factory()->role($role)->create() : $this->userFor(School::factory()->create(), $role);
            Sanctum::actingAs($user);
            $first = $this->post('/api/v1/profile/avatar', ['avatar' => UploadedFile::fake()->image('first.jpg')])->assertOk();
            $second = $this->post('/api/v1/profile/avatar', ['avatar' => UploadedFile::fake()->image('second.jpg')])->assertOk();
            $this->assertNotSame($first->json('data.profile_pic_url'), $second->json('data.profile_pic_url'));
            $this->get($second->json('data.profile_pic_url'))->assertOk();
            Storage::disk('local')->assertMissing($first->json('data.profile_pic'));
            Storage::disk('local')->assertExists($second->json('data.profile_pic'));
        }
    }

    public function test_login_identifier_collisions_are_rejected_across_schools_and_fields(): void
    {
        $school = School::factory()->create();
        $other = School::factory()->create();
        app(SchoolContext::class)->run($other->id, fn () => User::factory()->role('teacher')->create(['phone' => '123456789']));
        Sanctum::actingAs($this->userFor($school));

        $this->postJson('/api/v1/admin/users', ['username' => '123456789', 'full_name' => 'Ambiguous Login', 'password' => 'securepassword', 'role' => 'teacher'])->assertUnprocessable()->assertJsonValidationErrors('username');
        $this->assertDatabaseMissing('users', ['username' => '123456789']);
    }

    public function test_existing_public_photos_are_copied_and_verified_before_public_copies_are_removed(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $school = School::factory()->create();
        $user = $this->userFor($school, 'teacher');
        Storage::disk('public')->put('avatars/legacy.png', 'original-bytes');
        app(SchoolContext::class)->run($school->id, fn () => $user->forceFill(['profile_pic' => 'avatars/legacy.png'])->save());

        $this->artisan('school:migrate-files', ['--remove-public' => true])->assertSuccessful();

        $newPath = DB::table('users')->where('id', $user->id)->value('profile_pic');
        $this->assertSame('original-bytes', Storage::disk('local')->get($newPath));
        Storage::disk('public')->assertMissing('avatars/legacy.png');
        Storage::disk('local')->assertExists('school-file-archive/avatars/legacy.png');
    }

    public function test_missing_school_context_cannot_read_or_create_school_records(): void
    {
        $school = School::factory()->create();
        $this->studentFor($school);
        app(SchoolContext::class)->set(null);

        $this->assertSame(0, Student::count());
        $this->expectException(ValidationException::class);
        Student::factory()->create();
    }

    public function test_database_snapshot_can_restore_school_records_and_ownership(): void
    {
        $school = School::factory()->create();
        $user = $this->userFor($school, 'teacher');
        $before = glob(storage_path('app/private/backups/*.sql.gz')) ?: [];
        $this->artisan('school:backup-database')->assertSuccessful();
        $created = array_values(array_diff(glob(storage_path('app/private/backups/*.sql.gz')) ?: [], $before));
        $this->assertCount(1, $created);
        try {
            $sql = gzdecode(file_get_contents($created[0]));
            $restored = new \PDO('sqlite::memory:');
            $restored->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $restored->exec($sql);
            $row = $restored->query('SELECT school_id, username FROM users WHERE id = '.(int) $user->id)->fetch(\PDO::FETCH_ASSOC);
            $this->assertSame($school->id, (int) $row['school_id']);
            $this->assertSame($user->username, $row['username']);
            $this->assertSame(DB::table('users')->count(), (int) $restored->query('SELECT count(*) FROM users')->fetchColumn());
        } finally {
            unlink($created[0]);
        }
    }
}
