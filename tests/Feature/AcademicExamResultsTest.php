<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\GradingSystem;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcademicExamResultsTest extends TestCase
{
    use RefreshDatabase;

    private function setupAcademic(): array
    {
        $academic = User::factory()->role('academic_teacher')->create();
        $class = SchoolClass::create(['name' => 'Form 1A', 'level' => 'secondary', 'capacity' => 40]);
        $subject = Subject::create(['code' => 'MATH', 'name' => 'Mathematics']);
        $student = Student::factory()->create(['class' => $class->name, 'class_id' => $class->id]);
        GradingSystem::create(['grade' => 'A', 'min_marks' => 80, 'max_marks' => 100, 'points' => 1, 'remarks' => 'Excellent']);
        GradingSystem::create(['grade' => 'B', 'min_marks' => 65, 'max_marks' => 79.99, 'points' => 2, 'remarks' => 'Good']);
        GradingSystem::create(['grade' => 'F', 'min_marks' => 0, 'max_marks' => 64.99, 'points' => 5, 'remarks' => 'Needs work']);
        Sanctum::actingAs($academic);

        return [$academic, $class, $subject, $student];
    }

    public function test_academic_can_manage_exams_and_results(): void
    {
        [$academic, $class, $subject, $student] = $this->setupAcademic();

        $created = $this->postJson('/api/v1/academic/exams', [
            'name' => 'Midterm',
            'type' => 'exam',
            'term' => 'Term 1',
            'academic_year' => '2026',
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-19',
            'status' => 'active',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Midterm')
            ->assertJsonPath('data.created_by', $academic->id);

        $examId = $created->json('data.id');

        $this->getJson('/api/v1/academic/exams?search=Mid')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->putJson("/api/v1/academic/exams/{$examId}", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $result = $this->postJson('/api/v1/academic/results', [
            'exam_id' => $examId,
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'marks' => 88,
            'remarks' => 'Excellent work',
        ])->assertCreated()
            ->assertJsonPath('data.marks', 88)
            ->assertJsonPath('data.grade', 'A')
            ->assertJsonPath('data.exam_name', 'Midterm');

        $resultId = $result->json('data.id');

        $this->getJson("/api/v1/academic/exams/{$examId}/results?class_id={$class->id}")
            ->assertOk()
            ->assertJsonPath('summary.results_count', 1)
            ->assertJsonPath('data.0.student.full_name', $student->full_name);

        $this->putJson("/api/v1/academic/results/{$resultId}", ['marks' => 70, 'remarks' => 'Revised'])
            ->assertOk()
            ->assertJsonPath('data.marks', 70)
            ->assertJsonPath('data.grade', 'B');

        $this->deleteJson("/api/v1/academic/exams/{$examId}")
            ->assertStatus(422);

        $this->deleteJson("/api/v1/academic/results/{$resultId}")->assertOk();
        $this->deleteJson("/api/v1/academic/exams/{$examId}")->assertOk();
    }

    public function test_academic_can_view_results_analysis_and_student_progress(): void
    {
        [, $class, $subject, $student] = $this->setupAcademic();
        $exam = Exam::create([
            'name' => 'Midterm',
            'type' => 'exam',
            'term' => 'Term 1',
            'academic_year' => '2026',
            'status' => 'completed',
        ]);
        ExamResult::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'exam_name' => $exam->name,
            'exam_type' => $exam->type,
            'term' => $exam->term,
            'academic_year' => $exam->academic_year,
            'marks' => 88,
            'grade' => 'A',
        ]);

        $this->getJson("/api/v1/academic/results-analysis?exam_id={$exam->id}&class_id={$class->id}")
            ->assertOk()
            ->assertJsonPath('data.summary.results_count', 1)
            ->assertJsonPath('data.summary.average', 88)
            ->assertJsonPath('data.subject_performance.0.code', 'MATH')
            ->assertJsonPath('data.top_students.0.student_id', $student->id);

        $this->getJson("/api/v1/academic/student-progress/{$student->id}")
            ->assertOk()
            ->assertJsonPath('data.student.id', $student->id)
            ->assertJsonPath('data.summary.average', 88)
            ->assertJsonPath('data.subjects.0.code', 'MATH');
    }
}
