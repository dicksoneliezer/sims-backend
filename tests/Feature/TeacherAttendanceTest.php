<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeacherClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeacherAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function setupTeacher(): array
    {
        $teacher = User::factory()->role('teacher')->create();
        $class = SchoolClass::create(['name' => 'Form 1A', 'level' => 'secondary', 'capacity' => 40]);
        $student = Student::factory()->create(['class' => $class->name, 'class_id' => $class->id]);
        TeacherClass::create(['teacher_id' => $teacher->id, 'class_id' => $class->id, 'academic_year' => '2026']);
        Sanctum::actingAs($teacher);

        return [$teacher, $class, $student];
    }

    public function test_teacher_can_load_and_save_class_attendance(): void
    {
        [$teacher, $class, $student] = $this->setupTeacher();
        $date = now()->toDateString();

        $this->getJson("/api/v1/teacher/attendance?class_id={$class->id}&date={$date}")
            ->assertOk()
            ->assertJsonPath('data.0.student_id', $student->id)
            ->assertJsonPath('data.0.attendance', null)
            ->assertJsonPath('filters.classes.0.id', $class->id);

        $this->postJson('/api/v1/teacher/attendance', [
            'class_id' => $class->id,
            'date' => $date,
            'records' => [
                ['student_id' => $student->id, 'status' => 'late', 'remarks' => 'Traffic'],
            ],
        ])->assertOk()->assertJsonPath('data.records_count', 1);

        $this->assertDatabaseHas('attendance', [
            'student_id' => $student->id,
            'status' => 'late',
            'marked_by' => $teacher->id,
        ]);
        $this->assertTrue(Attendance::where('student_id', $student->id)->whereDate('date', $date)->exists());

        $this->getJson("/api/v1/teacher/attendance?class_id={$class->id}&date={$date}")
            ->assertOk()
            ->assertJsonPath('data.0.attendance.status', 'late');
    }

    public function test_teacher_cannot_mark_attendance_for_unassigned_class(): void
    {
        [$teacher, $class, $student] = $this->setupTeacher();
        $otherClass = SchoolClass::create(['name' => 'Form 9Z', 'level' => 'secondary', 'capacity' => 40]);

        $this->postJson('/api/v1/teacher/attendance', [
            'class_id' => $otherClass->id,
            'date' => now()->toDateString(),
            'records' => [
                ['student_id' => $student->id, 'status' => 'present'],
            ],
        ])->assertForbidden();

        $this->getJson("/api/v1/teacher/attendance?class_id={$otherClass->id}")
            ->assertForbidden();
    }

    public function test_teacher_can_view_attendance_report(): void
    {
        [$teacher, $class, $student] = $this->setupTeacher();
        Attendance::create([
            'student_id' => $student->id,
            'date' => now()->toDateString(),
            'status' => 'absent',
            'marked_by' => $teacher->id,
        ]);

        $this->getJson("/api/v1/teacher/attendance/report?class_id={$class->id}")
            ->assertOk()
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.absent', 1)
            ->assertJsonPath('summary.by_student.0.student_id', $student->id);
    }
}
