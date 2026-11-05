<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ExamResult;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->role('super_admin')->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_admin_can_list_and_filter_students(): void
    {
        $this->actingAsSuperAdmin();
        Student::factory()->create(['full_name' => 'Amina Hassan', 'class' => 'Form 1A', 'school_level' => 'secondary']);
        Student::factory()->create(['full_name' => 'Baraka Mushi', 'class' => 'Standard 5A', 'school_level' => 'primary']);
        Student::factory()->inactive()->create(['full_name' => 'Amina Juma', 'class' => 'Form 4D']);

        $this->getJson('/api/v1/admin/students?search=Amina&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/v1/admin/students?'.http_build_query(['class' => 'Standard 5A']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.full_name', 'Baraka Mushi');

        $this->getJson('/api/v1/admin/students?is_active=0')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/admin/students')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total', 'last_page'], 'filters' => ['classes']]);
    }

    public function test_admin_can_create_student_with_generated_number(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/admin/students', [
            'full_name' => 'Neema Joseph',
            'class' => 'Form 2B',
            'school_level' => 'secondary',
            'gender' => 'female',
            'parent_phone' => '+255712345678',
            'opening_balance' => 150000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.full_name', 'Neema Joseph')
            ->assertJsonPath('data.is_active', true);

        $this->assertStringStartsWith('SIMS-', $response->json('data.student_number'));
        $this->assertDatabaseHas('students', ['full_name' => 'Neema Joseph', 'class' => 'Form 2B']);
    }

    public function test_student_validation_rejects_duplicates_and_invalid_values(): void
    {
        $this->actingAsSuperAdmin();
        Student::factory()->create(['student_number' => 'SIMS-2026-0001']);

        $this->postJson('/api/v1/admin/students', [
            'full_name' => '',
            'student_number' => 'SIMS-2026-0001',
            'school_level' => 'university',
            'gender' => 'other',
            'email' => 'not-an-email',
            'date_of_birth' => 'tomorrow',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['full_name', 'student_number', 'school_level', 'gender', 'email', 'date_of_birth']);
    }

    public function test_admin_can_view_update_and_delete_student(): void
    {
        $this->actingAsSuperAdmin();
        $student = Student::factory()->create(['full_name' => 'Old Name']);

        $this->getJson("/api/v1/admin/students/{$student->id}")
            ->assertOk()
            ->assertJsonPath('data.student_number', $student->student_number);

        $this->putJson("/api/v1/admin/students/{$student->id}", [
            'full_name' => 'New Name',
            'class' => 'Form 3C',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.full_name', 'New Name')
            ->assertJsonPath('data.class', 'Form 3C')
            ->assertJsonPath('data.is_active', false);

        $this->deleteJson("/api/v1/admin/students/{$student->id}")->assertOk();
        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }

    public function test_admin_can_upload_and_remove_student_photo(): void
    {
        Storage::fake('local');
        $this->actingAsSuperAdmin();

        $response = $this->post('/api/v1/admin/students', [
            'full_name' => 'Photo Student',
            'photo' => UploadedFile::fake()->image('student.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $path = $response->json('data.photo');
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);

        $this->putJson("/api/v1/admin/students/{$response->json('data.id')}", [
            'remove_photo' => true,
        ])->assertOk();

        Storage::disk('local')->assertMissing($path);
    }

    public function test_student_results_endpoint_returns_summary(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $student = Student::factory()->create();
        $subject = Subject::create(['code' => 'MATH', 'name' => 'Mathematics']);

        ExamResult::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'exam_name' => 'Midterm',
            'term' => 'Term 1',
            'academic_year' => '2026',
            'marks' => 80,
            'entered_by' => $admin->id,
        ]);
        ExamResult::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'exam_name' => 'Final',
            'term' => 'Term 2',
            'academic_year' => '2026',
            'marks' => 90,
            'entered_by' => $admin->id,
        ]);

        $this->getJson("/api/v1/admin/students/{$student->id}/results")
            ->assertOk()
            ->assertJsonPath('data.summary.count', 2)
            ->assertJsonPath('data.summary.average', 85)
            ->assertJsonPath('data.summary.highest', 90)
            ->assertJsonPath('data.results.0.subject.code', 'MATH');
    }

    public function test_student_attendance_endpoint_returns_summary(): void
    {
        $this->actingAsSuperAdmin();
        $student = Student::factory()->create();

        Attendance::create(['student_id' => $student->id, 'date' => '2026-10-20', 'status' => 'present']);
        Attendance::create(['student_id' => $student->id, 'date' => '2026-10-21', 'status' => 'absent']);
        Attendance::create(['student_id' => $student->id, 'date' => '2026-10-22', 'status' => 'late']);

        $this->getJson("/api/v1/admin/students/{$student->id}/attendance")
            ->assertOk()
            ->assertJsonPath('data.summary.total', 3)
            ->assertJsonPath('data.summary.present', 1)
            ->assertJsonPath('data.summary.absent', 1)
            ->assertJsonPath('data.summary.late', 1);
    }

    public function test_teacher_can_view_students_but_not_create_them(): void
    {
        $teacher = User::factory()->role('teacher')->create();
        $class = SchoolClass::create(['name' => 'Form 1A', 'level' => 'secondary', 'capacity' => 40]);
        TeacherClass::create(['teacher_id' => $teacher->id, 'class_id' => $class->id, 'academic_year' => '2026']);
        Student::factory()->count(2)->create(['class' => 'Form 1A', 'class_id' => $class->id]);
        Student::factory()->create(['class' => 'Form 2B']);
        Sanctum::actingAs($teacher);

        $this->getJson('/api/v1/teacher/students')->assertOk()->assertJsonCount(2, 'data');

        $this->postJson('/api/v1/admin/students', ['full_name' => 'Nope'])->assertStatus(403);
    }

    public function test_unassigned_teacher_sees_no_students(): void
    {
        $teacher = User::factory()->role('teacher')->create();
        Sanctum::actingAs($teacher);
        Student::factory()->count(3)->create();

        $this->getJson('/api/v1/teacher/students')->assertOk()->assertJsonCount(0, 'data');

        $student = Student::first();
        $this->getJson("/api/v1/teacher/students/{$student->id}")->assertStatus(403);
    }

    public function test_parent_can_only_access_linked_children(): void
    {
        $parent = User::factory()->role('parent')->create();
        $child = Student::factory()->create();
        $other = Student::factory()->create();
        $parent->children()->attach($child->id, ['relationship' => 'mother', 'is_primary' => true]);
        Sanctum::actingAs($parent);

        $this->getJson('/api/v1/parent/children')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $child->id);

        $this->getJson("/api/v1/parent/children/{$child->id}")->assertOk();
        $this->getJson("/api/v1/parent/children/{$other->id}")->assertStatus(403);
    }

    public function test_student_can_view_own_profile(): void
    {
        $user = User::factory()->role('student')->create();
        $student = Student::factory()->create(['user_id' => $user->id, 'full_name' => 'Linked Student']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/student/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $student->id)
            ->assertJsonPath('data.full_name', 'Linked Student');
    }
}
