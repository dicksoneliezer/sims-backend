<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherClass;
use App\Models\TeacherSubject;
use App\Models\Timetable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcademicManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->role('super_admin')->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function makeClass(string $name = 'Form 1A', string $level = 'secondary'): SchoolClass
    {
        return SchoolClass::create(['name' => $name, 'level' => $level, 'capacity' => 40]);
    }

    private function makeSubject(string $code = 'MATH', string $name = 'Mathematics'): Subject
    {
        return Subject::create(['code' => $code, 'name' => $name]);
    }

    public function test_admin_can_manage_subjects(): void
    {
        $this->actingAsSuperAdmin();

        $created = $this->postJson('/api/v1/admin/subjects', [
            'code' => 'MATH',
            'name' => 'Mathematics',
            'level' => 'secondary',
        ])->assertCreated()->assertJsonPath('data.code', 'MATH');

        $id = $created->json('data.id');

        $this->getJson('/api/v1/admin/subjects?search=Math')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->putJson("/api/v1/admin/subjects/{$id}", ['name' => 'Advanced Mathematics'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Advanced Mathematics');

        $this->postJson('/api/v1/admin/subjects', ['code' => 'MATH', 'name' => 'Duplicate'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->deleteJson("/api/v1/admin/subjects/{$id}")->assertOk();
        $this->assertDatabaseMissing('subjects', ['id' => $id]);
    }

    public function test_subject_in_use_cannot_be_deleted(): void
    {
        $this->actingAsSuperAdmin();
        $subject = $this->makeSubject();
        $teacher = User::factory()->role('teacher')->create();
        TeacherSubject::create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);

        $this->deleteJson("/api/v1/admin/subjects/{$subject->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'This subject is in use and cannot be deleted.');
    }

    public function test_admin_can_manage_classes_and_rename_updates_students(): void
    {
        $this->actingAsSuperAdmin();
        $class = $this->makeClass();
        $student = Student::factory()->create(['class' => 'Form 1A', 'class_id' => $class->id]);

        $this->getJson('/api/v1/admin/classes')
            ->assertOk()
            ->assertJsonPath('data.0.students_count', 1);

        $this->putJson("/api/v1/admin/classes/{$class->id}", ['name' => 'Form 1X'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Form 1X');

        $this->assertDatabaseHas('students', ['id' => $student->id, 'class' => 'Form 1X']);
    }

    public function test_class_with_students_cannot_be_deleted(): void
    {
        $this->actingAsSuperAdmin();
        $class = $this->makeClass();
        Student::factory()->create(['class' => 'Form 1A', 'class_id' => $class->id]);

        $this->deleteJson("/api/v1/admin/classes/{$class->id}")->assertStatus(422);
    }

    public function test_admin_can_manage_teachers(): void
    {
        $this->actingAsSuperAdmin();

        $created = $this->postJson('/api/v1/admin/teachers', [
            'username' => 'newteacher',
            'full_name' => 'New Teacher',
            'email' => 'newteacher@sims.co.tz',
            'password' => 'secret123',
        ])->assertCreated()
            ->assertJsonPath('data.role', 'teacher')
            ->assertJsonPath('data.username', 'newteacher');

        $teacher = User::where('username', 'newteacher')->first();
        $this->assertTrue(Hash::check('secret123', $teacher->password));

        $this->putJson("/api/v1/admin/teachers/{$teacher->id}", ['full_name' => 'Renamed Teacher'])
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Renamed Teacher');

        $this->getJson("/api/v1/admin/teachers/{$teacher->id}")
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Renamed Teacher');

        $this->deleteJson("/api/v1/admin/teachers/{$teacher->id}")->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $teacher->id]);
    }

    public function test_non_teacher_user_cannot_be_updated_as_teacher(): void
    {
        $this->actingAsSuperAdmin();
        $admin = User::factory()->role('super_admin')->create();

        $this->getJson("/api/v1/admin/teachers/{$admin->id}")->assertNotFound();
    }

    public function test_admin_can_assign_teacher_to_class_and_subject(): void
    {
        $this->actingAsSuperAdmin();
        $teacher = User::factory()->role('teacher')->create();
        $class = $this->makeClass();
        $subject = $this->makeSubject();

        $this->postJson('/api/v1/admin/teacher-classes', [
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'academic_year' => '2026',
        ])->assertCreated()->assertJsonPath('data.class_name', 'Form 1A');

        $this->postJson('/api/v1/admin/teacher-classes', [
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'academic_year' => '2026',
        ])->assertStatus(422);

        $this->postJson('/api/v1/admin/teacher-subjects', [
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'academic_year' => '2026',
        ])->assertCreated()->assertJsonPath('data.subject_code', 'MATH');

        $this->getJson("/api/v1/admin/teachers/{$teacher->id}")
            ->assertOk()
            ->assertJsonPath('data.classes_count', 1)
            ->assertJsonPath('data.subjects_count', 1);

        $assignment = TeacherClass::where('teacher_id', $teacher->id)->first();
        $this->deleteJson("/api/v1/admin/teacher-classes/{$assignment->id}")->assertOk();
        $this->assertDatabaseMissing('teacher_classes', ['id' => $assignment->id]);
    }

    public function test_timetable_slots_reject_conflicts(): void
    {
        $this->actingAsSuperAdmin();
        $teacher = User::factory()->role('teacher')->create();
        $class = $this->makeClass();
        $otherClass = $this->makeClass('Form 1B');
        $subject = $this->makeSubject();

        $payload = [
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '08:40',
            'term' => 'Term 1',
            'academic_year' => '2026',
        ];

        $this->postJson('/api/v1/admin/timetables', $payload)
            ->assertCreated()
            ->assertJsonPath('data.start_time', '08:00');

        $this->postJson('/api/v1/admin/timetables', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_time');

        $this->postJson('/api/v1/admin/timetables', [
            ...$payload,
            'class_id' => $otherClass->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
    }

    public function test_timetable_update_and_delete(): void
    {
        $this->actingAsSuperAdmin();
        $teacher = User::factory()->role('teacher')->create();
        $class = $this->makeClass();
        $subject = $this->makeSubject();
        $slot = Timetable::create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '08:40',
            'term' => 'Term 1',
            'academic_year' => '2026',
        ]);

        $this->putJson("/api/v1/admin/timetables/{$slot->id}", [
            'end_time' => '09:20',
            'start_time' => '08:40',
        ])->assertOk()->assertJsonPath('data.start_time', '08:40');

        $this->deleteJson("/api/v1/admin/timetables/{$slot->id}")->assertOk();
        $this->assertDatabaseMissing('timetables', ['id' => $slot->id]);
    }

    public function test_teacher_can_view_own_schedule_and_assignments(): void
    {
        $teacher = User::factory()->role('teacher')->create();
        $class = $this->makeClass();
        $subject = $this->makeSubject();
        TeacherClass::create(['teacher_id' => $teacher->id, 'class_id' => $class->id]);
        TeacherSubject::create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'class_id' => $class->id]);
        Timetable::create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '08:40',
        ]);
        Sanctum::actingAs($teacher);

        $this->getJson('/api/v1/teacher/classes')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/teacher/subjects')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/teacher/timetable')->assertOk()->assertJsonCount(1, 'data');

        $this->postJson('/api/v1/admin/teacher-classes', [
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
        ])->assertForbidden();

        $this->postJson('/api/v1/admin/timetables', [
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'day_of_week' => 'Tuesday',
            'start_time' => '08:00',
            'end_time' => '08:40',
        ])->assertForbidden();
    }

    public function test_academic_teacher_can_manage_subjects_but_not_classes(): void
    {
        $academic = User::factory()->role('academic_teacher')->create();
        Sanctum::actingAs($academic);

        $this->postJson('/api/v1/academic/subjects', ['code' => 'PHY', 'name' => 'Physics'])
            ->assertCreated();

        $this->postJson('/api/v1/admin/classes', ['name' => 'Form 5A', 'level' => 'secondary'])
            ->assertForbidden();
    }

    public function test_student_and_parent_can_view_timetable(): void
    {
        $class = $this->makeClass();
        $subject = $this->makeSubject();
        $studentUser = User::factory()->role('student')->create();
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'class' => 'Form 1A',
            'class_id' => $class->id,
        ]);
        $parent = User::factory()->role('parent')->create();
        $student->parents()->attach($parent->id, ['relationship' => 'parent', 'is_primary' => true]);
        $otherParent = User::factory()->role('parent')->create();

        Timetable::create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'day_of_week' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '08:40',
        ]);

        Sanctum::actingAs($studentUser);
        $this->getJson('/api/v1/student/timetable')->assertOk()->assertJsonCount(1, 'data');

        Sanctum::actingAs($parent);
        $this->getJson("/api/v1/parent/children/{$student->id}/timetable")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        Sanctum::actingAs($otherParent);
        $this->getJson("/api/v1/parent/children/{$student->id}/timetable")->assertForbidden();
    }
}
