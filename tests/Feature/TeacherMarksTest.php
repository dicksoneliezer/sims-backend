<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\GradingSystem;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherClass;
use App\Models\TeacherSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeacherMarksTest extends TestCase
{
    use RefreshDatabase;

    private function setupTeacher(): array
    {
        $teacher = User::factory()->role('teacher')->create();
        $class = SchoolClass::create(['name' => 'Form 1A', 'level' => 'secondary', 'capacity' => 40]);
        $subject = Subject::create(['code' => 'MATH', 'name' => 'Mathematics']);
        $student = Student::factory()->create(['class' => $class->name, 'class_id' => $class->id]);
        $exam = Exam::create([
            'name' => 'Midterm',
            'type' => 'exam',
            'term' => 'Term 1',
            'academic_year' => '2026',
            'status' => 'active',
            'created_by' => $teacher->id,
        ]);
        GradingSystem::create(['grade' => 'A', 'min_marks' => 80, 'max_marks' => 100, 'points' => 1, 'remarks' => 'Excellent']);
        GradingSystem::create(['grade' => 'B', 'min_marks' => 65, 'max_marks' => 79.99, 'points' => 2, 'remarks' => 'Good']);
        GradingSystem::create(['grade' => 'F', 'min_marks' => 0, 'max_marks' => 64.99, 'points' => 5, 'remarks' => 'Needs work']);
        TeacherClass::create(['teacher_id' => $teacher->id, 'class_id' => $class->id, 'academic_year' => '2026']);
        TeacherSubject::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'academic_year' => '2026',
        ]);
        Sanctum::actingAs($teacher);

        return [$teacher, $class, $subject, $student, $exam];
    }

    public function test_teacher_can_enter_and_update_class_marks(): void
    {
        [$teacher, $class, $subject, $student, $exam] = $this->setupTeacher();

        $created = $this->postJson('/api/v1/teacher/marks', [
            'exam_id' => $exam->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'entries' => [
                ['student_id' => $student->id, 'marks' => 85, 'remarks' => 'Top score'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.0.marks', 85)
            ->assertJsonPath('data.0.grade', 'A');

        $resultId = $created->json('data.0.id');

        $this->assertDatabaseHas('exam_results', [
            'id' => $resultId,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'entered_by' => $teacher->id,
        ]);

        $this->getJson("/api/v1/teacher/marks?exam_id={$exam->id}&class_id={$class->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('filters.classes.0.id', $class->id);

        $this->putJson("/api/v1/teacher/marks/{$resultId}", ['marks' => 70, 'remarks' => 'Revised'])
            ->assertOk()
            ->assertJsonPath('data.marks', 70)
            ->assertJsonPath('data.grade', 'B');

        $this->deleteJson("/api/v1/teacher/marks/{$resultId}")->assertOk();
        $this->assertDatabaseMissing('exam_results', ['id' => $resultId]);
    }

    public function test_teacher_can_view_class_results(): void
    {
        [$teacher, $class, $subject, $student, $exam] = $this->setupTeacher();
        ExamResult::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'exam_name' => $exam->name,
            'exam_type' => $exam->type,
            'term' => $exam->term,
            'academic_year' => $exam->academic_year,
            'marks' => 85,
            'grade' => 'A',
            'entered_by' => $teacher->id,
        ]);

        $this->getJson("/api/v1/teacher/class-results?class_id={$class->id}&exam_id={$exam->id}")
            ->assertOk()
            ->assertJsonPath('data.summary.average', 85)
            ->assertJsonPath('data.summary.pass_rate', 100)
            ->assertJsonPath('data.students.0.full_name', $student->full_name)
            ->assertJsonPath('data.subjects.0.code', 'MATH');
    }

    public function test_teacher_cannot_enter_marks_for_unassigned_subject(): void
    {
        [$teacher, $class, , $student, $exam] = $this->setupTeacher();
        $otherSubject = Subject::create(['code' => 'BIO', 'name' => 'Biology']);

        $this->postJson('/api/v1/teacher/marks', [
            'exam_id' => $exam->id,
            'class_id' => $class->id,
            'subject_id' => $otherSubject->id,
            'entries' => [
                ['student_id' => $student->id, 'marks' => 80],
            ],
        ])->assertForbidden();
    }
}
