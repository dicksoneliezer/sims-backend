<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ExamResult;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_generate_cross_domain_report(): void
    {
        $admin = User::factory()->role('super_admin')->create();
        Sanctum::actingAs($admin);

        $students = Student::factory()->count(2)->create();
        $subject = Subject::create(['code' => 'MAT', 'name' => 'Mathematics', 'is_active' => true]);

        Attendance::create([
            'student_id' => $students[0]->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'marked_by' => $admin->id,
        ]);

        Attendance::create([
            'student_id' => $students[1]->id,
            'date' => now()->toDateString(),
            'status' => 'absent',
            'marked_by' => $admin->id,
        ]);

        ExamResult::create([
            'student_id' => $students[0]->id,
            'subject_id' => $subject->id,
            'exam_name' => 'Midterm',
            'term' => 'Term 1',
            'academic_year' => '2026',
            'marks' => 80,
        ]);

        $this->getJson('/api/v1/admin/reports/overview')
            ->assertOk()
            ->assertJsonPath('data.enrollment.total_students', 2)
            ->assertJsonPath('data.enrollment.active_students', 2)
            ->assertJsonPath('data.attendance.total_records', 2)
            ->assertJsonPath('data.attendance.present', 1)
            ->assertJsonPath('data.attendance.absent', 1)
            ->assertJsonPath('data.attendance.attendance_rate', 50)
            ->assertJsonPath('data.academics.results.0.entries', 1)
            ->assertJsonPath('data.academics.results.0.average_marks', 80)
            ->assertJsonPath('data.users.total', 1)
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'enrollment' => ['by_class', 'by_gender'],
                    'attendance',
                    'academics',
                    'finance',
                    'inventory',
                    'users',
                ],
            ]);
    }
}
