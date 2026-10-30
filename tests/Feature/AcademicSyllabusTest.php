<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcademicSyllabusTest extends TestCase
{
    use RefreshDatabase;

    private function setupAcademic(): array
    {
        $academic = User::factory()->role('academic_teacher')->create();
        $class = SchoolClass::create(['name' => 'Form 1A', 'level' => 'secondary', 'capacity' => 40]);
        $subject = Subject::create(['code' => 'MATH', 'name' => 'Mathematics']);
        Sanctum::actingAs($academic);

        return [$academic, $class, $subject];
    }

    public function test_academic_can_manage_syllabus_and_track_progress(): void
    {
        [$academic, $class, $subject] = $this->setupAcademic();

        $created = $this->postJson('/api/v1/academic/syllabus', [
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'title' => 'Algebraic expressions',
            'description' => 'Simplify and factorise expressions.',
            'term' => 'Term 1',
            'week' => 2,
            'sort_order' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Algebraic expressions')
            ->assertJsonPath('data.subject.code', 'MATH');

        $topicId = $created->json('data.id');

        $this->getJson("/api/v1/academic/syllabus?class_id={$class->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('filters.classes.0.id', $class->id);

        $this->putJson("/api/v1/academic/syllabus/{$topicId}", ['week' => 3])
            ->assertOk()
            ->assertJsonPath('data.week', 3);

        $this->postJson('/api/v1/academic/syllabus-progress', [
            'syllabus_topic_id' => $topicId,
            'status' => 'completed',
            'remarks' => 'Covered and assessed.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.teacher_id', $academic->id);

        $this->assertDatabaseHas('syllabus_progress', [
            'syllabus_topic_id' => $topicId,
            'teacher_id' => $academic->id,
            'status' => 'completed',
        ]);

        $this->getJson("/api/v1/academic/syllabus-progress?class_id={$class->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.topic.title', 'Algebraic expressions');

        $this->deleteJson("/api/v1/academic/syllabus/{$topicId}")->assertOk();
        $this->assertDatabaseMissing('syllabus_topics', ['id' => $topicId]);
        $this->assertDatabaseMissing('syllabus_progress', ['syllabus_topic_id' => $topicId]);
    }

    public function test_syllabus_topic_requires_subject_and_class(): void
    {
        $this->setupAcademic();

        $this->postJson('/api/v1/academic/syllabus', [
            'title' => 'Missing relations',
            'term' => 'Term 1',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['subject_id', 'class_id']);
    }
}
