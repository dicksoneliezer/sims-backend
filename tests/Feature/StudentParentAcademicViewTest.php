<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ExamResult;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentParentAcademicViewTest extends TestCase
{
    use RefreshDatabase;

    private function setupFamily(): array
    {
        $class = SchoolClass::create(['name' => 'Form 1A', 'level' => 'secondary', 'capacity' => 40]);
        $subject = Subject::create(['code' => 'MATH', 'name' => 'Mathematics']);
        $studentUser = User::factory()->role('student')->create();
        $parent = User::factory()->role('parent')->create();
        $teacher = User::factory()->role('teacher')->create();
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'full_name' => 'Amina Hassan',
            'class' => $class->name,
            'class_id' => $class->id,
        ]);
        $parent->children()->attach($student->id, ['relationship' => 'mother', 'is_primary' => true]);
        $note = TeacherNote::create([
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
            'title' => 'Reading progress',
            'note' => 'Improved comprehension this week.',
            'type' => 'academic',
        ]);
        ExamResult::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'exam_name' => 'Midterm',
            'term' => 'Term 1',
            'academic_year' => '2026',
            'marks' => 82,
            'grade' => 'A',
        ]);
        Attendance::create([
            'student_id' => $student->id,
            'date' => now()->toDateString(),
            'status' => 'present',
        ]);

        return [$studentUser, $parent, $student, $note];
    }

    public function test_student_can_view_and_read_their_notes(): void
    {
        [$studentUser, , , $note] = $this->setupFamily();
        Sanctum::actingAs($studentUser);

        $this->getJson('/api/v1/student/notes')
            ->assertOk()
            ->assertJsonPath('data.0.id', $note->id)
            ->assertJsonPath('data.0.is_read', false);

        $this->patchJson("/api/v1/student/notes/{$note->id}/read")
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->assertDatabaseHas('teacher_notes', ['id' => $note->id, 'is_read' => true]);
    }

    public function test_student_can_view_results_and_attendance(): void
    {
        [$studentUser] = $this->setupFamily();
        Sanctum::actingAs($studentUser);

        $this->getJson('/api/v1/student/results')
            ->assertOk()
            ->assertJsonPath('data.results.0.marks', '82.00')
            ->assertJsonPath('data.summary.average', 82);

        $this->getJson('/api/v1/student/attendance')
            ->assertOk()
            ->assertJsonPath('data.summary.present', 1);
    }

    public function test_parent_can_view_child_notes_and_results(): void
    {
        [, $parent, $student, $note] = $this->setupFamily();
        Sanctum::actingAs($parent);

        $this->getJson("/api/v1/parent/children/{$student->id}/notes")
            ->assertOk()
            ->assertJsonPath('data.0.id', $note->id);

        $this->getJson("/api/v1/parent/children/{$student->id}/results")
            ->assertOk()
            ->assertJsonPath('data.student.id', $student->id)
            ->assertJsonPath('data.results.0.grade', 'A');

        $this->getJson("/api/v1/parent/children/{$student->id}/attendance")
            ->assertOk()
            ->assertJsonPath('data.summary.total', 1);
    }

    public function test_student_cannot_read_another_students_note(): void
    {
        [$studentUser] = $this->setupFamily();
        $otherStudent = Student::factory()->create();
        $teacher = User::factory()->role('teacher')->create();
        $note = TeacherNote::create([
            'teacher_id' => $teacher->id,
            'student_id' => $otherStudent->id,
            'title' => 'Other note',
            'note' => 'Not for this student.',
            'type' => 'general',
        ]);
        Sanctum::actingAs($studentUser);

        $this->patchJson("/api/v1/student/notes/{$note->id}/read")
            ->assertForbidden();
    }
}
