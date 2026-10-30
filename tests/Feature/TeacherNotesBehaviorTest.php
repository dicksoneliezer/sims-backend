<?php

namespace Tests\Feature;

use App\Models\BehaviorRecord;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeacherClass;
use App\Models\TeacherNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeacherNotesBehaviorTest extends TestCase
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

    public function test_teacher_can_manage_notes_for_assigned_students(): void
    {
        [$teacher, , $student] = $this->setupTeacher();

        $created = $this->postJson('/api/v1/teacher/notes', [
            'student_id' => $student->id,
            'title' => 'Reading progress',
            'note' => 'Improved comprehension this week.',
            'type' => 'academic',
        ])->assertCreated()
            ->assertJsonPath('data.student_id', $student->id)
            ->assertJsonPath('data.is_read', false);

        $noteId = $created->json('data.id');

        $this->getJson("/api/v1/teacher/notes?student_id={$student->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->putJson("/api/v1/teacher/notes/{$noteId}", ['title' => 'Updated reading progress'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated reading progress');

        $this->deleteJson("/api/v1/teacher/notes/{$noteId}")->assertOk();
        $this->assertDatabaseMissing('teacher_notes', ['id' => $noteId]);
    }

    public function test_teacher_can_manage_behavior_records_for_assigned_students(): void
    {
        [$teacher, , $student] = $this->setupTeacher();
        $date = now()->subDay()->toDateString();

        $created = $this->postJson('/api/v1/teacher/behavior', [
            'student_id' => $student->id,
            'date' => $date,
            'category' => 'Class participation',
            'description' => 'Helped a classmate with an exercise.',
            'severity' => 'low',
            'action_taken' => 'Recognised in class.',
        ])->assertCreated()
            ->assertJsonPath('data.severity', 'low');

        $behaviorId = $created->json('data.id');

        $this->getJson("/api/v1/teacher/behavior?student_id={$student->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->putJson("/api/v1/teacher/behavior/{$behaviorId}", ['severity' => 'medium'])
            ->assertOk()
            ->assertJsonPath('data.severity', 'medium');

        $this->deleteJson("/api/v1/teacher/behavior/{$behaviorId}")->assertOk();
        $this->assertDatabaseMissing('behavior_records', ['id' => $behaviorId]);
    }

    public function test_teacher_cannot_manage_another_teachers_records(): void
    {
        [$teacher, $class, $student] = $this->setupTeacher();
        $otherTeacher = User::factory()->role('teacher')->create();
        $note = TeacherNote::create([
            'teacher_id' => $otherTeacher->id,
            'student_id' => $student->id,
            'title' => 'Private note',
            'note' => 'Only for the other teacher.',
            'type' => 'general',
        ]);
        $behavior = BehaviorRecord::create([
            'teacher_id' => $otherTeacher->id,
            'student_id' => $student->id,
            'date' => now()->toDateString(),
            'category' => 'Conduct',
            'description' => 'Record owned by another teacher.',
            'severity' => 'low',
        ]);

        $this->putJson("/api/v1/teacher/notes/{$note->id}", ['title' => 'Hijacked'])
            ->assertForbidden();

        $this->deleteJson("/api/v1/teacher/behavior/{$behavior->id}")
            ->assertForbidden();

        $outsiderClass = SchoolClass::create(['name' => 'Form 8X', 'level' => 'secondary', 'capacity' => 20]);
        $outsider = Student::factory()->create(['class' => $outsiderClass->name, 'class_id' => $outsiderClass->id]);

        $this->postJson('/api/v1/teacher/notes', [
            'student_id' => $outsider->id,
            'title' => 'Outsider',
            'note' => 'Should not be allowed.',
            'type' => 'general',
        ])->assertForbidden();
    }
}
