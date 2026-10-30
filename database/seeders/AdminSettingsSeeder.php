<?php

namespace Database\Seeders;

use App\Models\GradingSystem;
use App\Models\SchoolCalendar;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'school_name' => 'SIMS Secondary School',
            'school_motto' => 'Knowledge, Integrity and Service',
            'school_address' => 'P.O. Box 123, Dar es Salaam, Tanzania',
            'school_phone' => '+255 22 123 4567',
            'school_email' => 'info@sims.ac.tz',
            'school_website' => 'https://sims.ac.tz',
            'head_teacher_name' => 'Dr. Sarah Mwinyi',
            'academic_year' => '2026',
            'current_term' => 'Term 1',
            'currency' => 'TZS',
            'timezone' => 'Africa/Dar_es_Salaam',
        ];

        foreach ($settings as $key => $value) {
            SchoolSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => 'school']
            );
        }

        $grades = [
            ['A', 80, 100, 1, 'Excellent'],
            ['B', 70, 79.99, 2, 'Very good'],
            ['C', 60, 69.99, 3, 'Good'],
            ['D', 50, 59.99, 4, 'Average'],
            ['E', 40, 49.99, 5, 'Below average'],
            ['F', 0, 39.99, 6, 'Fail'],
        ];

        foreach ($grades as [$grade, $min, $max, $points, $remarks]) {
            GradingSystem::updateOrCreate(
                ['grade' => $grade],
                ['min_marks' => $min, 'max_marks' => $max, 'points' => $points, 'remarks' => $remarks]
            );
        }

        $admin = User::where('role', 'super_admin')->first();
        $year = now()->year;

        $events = [
            ['Term 1 Opening', 'First day of Term 1 for all students.', "$year-01-08", "$year-01-08", 'term'],
            ['Midterm Examinations', 'Midterm examination week.', "$year-03-02", "$year-03-06", 'exam'],
            ['Easter Holiday', 'School closes for Easter break.', "$year-04-03", "$year-04-12", 'holiday'],
            ['Term 1 Closing', 'End of Term 1 and reporting day.', "$year-06-12", "$year-06-12", 'term'],
            ['Term 2 Opening', 'First day of Term 2.', "$year-07-06", "$year-07-06", 'term'],
            ['Annual Parents Meeting', 'Parents meeting and academic review.', "$year-08-15", "$year-08-15", 'meeting'],
            ['Pre-National Examinations', 'Mock examinations for candidate classes.', "$year-09-07", "$year-09-18", 'exam'],
            ['Term 2 Closing', 'End of Term 2.', "$year-11-27", "$year-11-27", 'term'],
        ];

        foreach ($events as [$title, $description, $start, $end, $type]) {
            SchoolCalendar::updateOrCreate(
                ['title' => $title, 'start_date' => $start],
                [
                    'description' => $description,
                    'end_date' => $end,
                    'type' => $type,
                    'created_by' => $admin?->id,
                ],
            );
        }
    }
}
