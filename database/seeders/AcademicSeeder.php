<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\ExamResult;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherClass;
use App\Models\TeacherSubject;
use App\Models\Timetable;
use App\Models\User;
use Illuminate\Database\Seeder;

class AcademicSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            ['code' => 'MATH', 'name' => 'Mathematics', 'level' => 'secondary'],
            ['code' => 'ENG', 'name' => 'English', 'level' => null],
            ['code' => 'KIS', 'name' => 'Kiswahili', 'level' => null],
            ['code' => 'BIO', 'name' => 'Biology', 'level' => 'secondary'],
            ['code' => 'GEO', 'name' => 'Geography', 'level' => 'secondary'],
            ['code' => 'ICT', 'name' => 'Information Technology', 'level' => null],
            ['code' => 'PHY', 'name' => 'Physics', 'level' => 'secondary'],
        ];

        foreach ($subjects as $subject) {
            Subject::updateOrCreate(['code' => $subject['code']], $subject + ['is_active' => true]);
        }

        $teachers = User::whereIn('username', ['teacher', 'teacher2', 'teacher3'])->get()->keyBy('username');

        $classes = [
            ['name' => 'Form 1A', 'level' => 'secondary', 'section' => 'A', 'capacity' => 40, 'teacher' => 'teacher'],
            ['name' => 'Form 1B', 'level' => 'secondary', 'section' => 'B', 'capacity' => 40, 'teacher' => 'teacher2'],
            ['name' => 'Form 2B', 'level' => 'secondary', 'section' => 'B', 'capacity' => 40, 'teacher' => 'teacher3'],
            ['name' => 'Form 3C', 'level' => 'secondary', 'section' => 'C', 'capacity' => 40, 'teacher' => 'teacher'],
            ['name' => 'Form 4D', 'level' => 'secondary', 'section' => 'D', 'capacity' => 40, 'teacher' => 'teacher2'],
            ['name' => 'Standard 5A', 'level' => 'primary', 'section' => 'A', 'capacity' => 35, 'teacher' => 'teacher3'],
        ];

        foreach ($classes as $class) {
            SchoolClass::updateOrCreate(
                ['name' => $class['name']],
                [
                    'level' => $class['level'],
                    'section' => $class['section'],
                    'capacity' => $class['capacity'],
                    'class_teacher_id' => $teachers[$class['teacher']]?->id,
                    'is_active' => true,
                ],
            );
        }

        $classModels = SchoolClass::all()->keyBy('name');
        $subjectModels = Subject::all()->keyBy('code');

        $classAssignments = [
            ['teacher', 'Form 1A'],
            ['teacher', 'Form 3C'],
            ['teacher2', 'Form 1B'],
            ['teacher2', 'Form 4D'],
            ['teacher3', 'Form 2B'],
            ['teacher3', 'Standard 5A'],
        ];

        foreach ($classAssignments as [$teacher, $className]) {
            TeacherClass::updateOrCreate([
                'teacher_id' => $teachers[$teacher]?->id,
                'class_id' => $classModels[$className]?->id,
                'academic_year' => '2026',
            ]);
        }

        $subjectAssignments = [
            ['teacher', 'MATH', 'Form 1A'],
            ['teacher', 'ENG', 'Form 1A'],
            ['teacher', 'MATH', 'Form 3C'],
            ['teacher2', 'BIO', 'Form 1B'],
            ['teacher2', 'BIO', 'Form 2B'],
            ['teacher2', 'GEO', 'Form 1B'],
            ['teacher3', 'KIS', 'Form 2B'],
            ['teacher3', 'ENG', 'Form 2B'],
            ['teacher3', 'ENG', 'Standard 5A'],
            ['teacher3', 'KIS', 'Standard 5A'],
        ];

        foreach ($subjectAssignments as [$teacher, $subjectCode, $className]) {
            TeacherSubject::updateOrCreate([
                'teacher_id' => $teachers[$teacher]?->id,
                'subject_id' => $subjectModels[$subjectCode]?->id,
                'class_id' => $classModels[$className]?->id,
                'academic_year' => '2026',
            ]);
        }

        $slots = [
            ['Form 1A', 'MATH', 'teacher', 'Monday', '08:00', '08:40', 'Room 1'],
            ['Form 1A', 'ENG', 'teacher', 'Monday', '08:40', '09:20', 'Room 1'],
            ['Form 1A', 'BIO', 'teacher2', 'Tuesday', '08:00', '08:40', 'Lab 1'],
            ['Form 1A', 'GEO', 'teacher2', 'Wednesday', '08:00', '08:40', 'Room 1'],
            ['Form 1A', 'KIS', 'teacher3', 'Thursday', '08:00', '08:40', 'Room 1'],
            ['Form 2B', 'BIO', 'teacher2', 'Monday', '08:00', '08:40', 'Lab 1'],
            ['Form 2B', 'KIS', 'teacher3', 'Monday', '08:40', '09:20', 'Room 2'],
            ['Form 2B', 'MATH', 'teacher', 'Tuesday', '08:00', '08:40', 'Room 2'],
            ['Form 2B', 'ENG', 'teacher3', 'Wednesday', '08:00', '08:40', 'Room 2'],
            ['Form 2B', 'GEO', 'teacher2', 'Thursday', '08:00', '08:40', 'Room 2'],
            ['Form 3C', 'MATH', 'teacher', 'Wednesday', '09:20', '10:00', 'Room 3'],
            ['Form 3C', 'ICT', 'teacher', 'Friday', '08:00', '08:40', 'Computer Lab'],
            ['Standard 5A', 'ENG', 'teacher3', 'Tuesday', '09:20', '10:00', 'Room 5'],
            ['Standard 5A', 'KIS', 'teacher3', 'Friday', '09:20', '10:00', 'Room 5'],
        ];

        foreach ($slots as [$className, $subjectCode, $teacher, $day, $start, $end, $room]) {
            Timetable::updateOrCreate(
                [
                    'class_id' => $classModels[$className]?->id,
                    'day_of_week' => $day,
                    'start_time' => $start,
                ],
                [
                    'subject_id' => $subjectModels[$subjectCode]?->id,
                    'teacher_id' => $teachers[$teacher]?->id,
                    'end_time' => $end,
                    'room' => $room,
                    'term' => 'Term 1',
                    'academic_year' => '2026',
                ],
            );
        }

        Student::whereNotNull('class')->get()->each(function (Student $student) use ($classModels) {
            $class = $classModels[$student->class] ?? null;

            if ($class) {
                $student->forceFill(['class_id' => $class->id])->save();
            }
        });

        $teacher = $teachers['teacher'];
        $students = Student::orderBy('id')->limit(3)->get();
        $allSubjects = Subject::orderBy('id')->get();
        $marks = ['MATH' => [78, 85, 92], 'ENG' => [66, 74, 81], 'KIS' => [71, 79, 88], 'BIO' => [83, 69, 75], 'GEO' => [59, 73, 84]];

        foreach ($students as $index => $student) {
            foreach ($allSubjects as $subject) {
                ExamResult::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'subject_id' => $subject->id,
                        'exam_name' => 'Midterm',
                        'term' => 'Term 1',
                        'academic_year' => '2026',
                    ],
                    [
                        'marks' => $marks[$subject->code][$index] ?? 70,
                        'entered_by' => $teacher?->id,
                    ],
                );
            }

            for ($daysAgo = 9; $daysAgo >= 0; $daysAgo--) {
                $date = now()->subDays($daysAgo)->toDateString();
                $status = $daysAgo === 3 ? 'absent' : ($daysAgo === 6 ? 'late' : 'present');

                Attendance::updateOrCreate(
                    ['student_id' => $student->id, 'date' => $date],
                    ['status' => $status, 'marked_by' => $teacher?->id],
                );
            }
        }
    }
}
