<?php

namespace Database\Seeders;

use App\Models\BehaviorRecord;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\SyllabusProgress;
use App\Models\SyllabusTopic;
use App\Models\TeacherNote;
use App\Models\TeacherSubject;
use App\Models\User;
use App\Services\GradingService;
use Illuminate\Database\Seeder;

class TeachingSeeder extends Seeder
{
    public function run(): void
    {
        $academic = User::where('username', 'academic')->first();
        $teacher = User::where('username', 'teacher')->first();
        $grading = app(GradingService::class);

        $exams = [
            ['name' => 'Midterm', 'type' => 'exam', 'term' => 'Term 1', 'academic_year' => '2026', 'start_date' => '2026-06-15', 'end_date' => '2026-06-19', 'status' => 'completed'],
            ['name' => 'CA Test 1', 'type' => 'ca', 'term' => 'Term 1', 'academic_year' => '2026', 'start_date' => '2026-05-12', 'end_date' => '2026-05-12', 'status' => 'active'],
            ['name' => 'Final Examination', 'type' => 'exam', 'term' => 'Term 2', 'academic_year' => '2026', 'start_date' => '2026-11-16', 'end_date' => '2026-11-27', 'status' => 'draft'],
        ];

        foreach ($exams as $exam) {
            Exam::updateOrCreate(
                ['name' => $exam['name'], 'term' => $exam['term'], 'academic_year' => $exam['academic_year']],
                $exam + ['created_by' => $academic?->id],
            );
        }

        $midterm = Exam::where('name', 'Midterm')->where('term', 'Term 1')->first();
        $ca = Exam::where('name', 'CA Test 1')->where('term', 'Term 1')->first();

        if ($midterm) {
            ExamResult::whereNull('exam_id')
                ->where('exam_name', 'Midterm')
                ->where('term', 'Term 1')
                ->where('academic_year', '2026')
                ->update(['exam_id' => $midterm->id]);
        }

        if ($ca && $teacher) {
            $assignments = TeacherSubject::with(['subject:id,code', 'schoolClass:id,name'])
                ->where('teacher_id', $teacher->id)
                ->whereIn('class_id', SchoolClass::whereIn('name', ['Form 1A', 'Form 3C'])->pluck('id'))
                ->get();

            foreach ($assignments as $assignment) {
                $students = Student::where('class_id', $assignment->class_id)->orderBy('id')->get();

                foreach ($students as $index => $student) {
                    $marks = min(98, 55 + (($student->id * 7 + $assignment->subject_id * 11 + $index * 3) % 41));
                    $grade = $grading->grade($marks);

                    ExamResult::updateOrCreate(
                        ['exam_id' => $ca->id, 'student_id' => $student->id, 'subject_id' => $assignment->subject_id],
                        [
                            'exam_name' => $ca->name,
                            'exam_type' => $ca->type,
                            'term' => $ca->term,
                            'academic_year' => $ca->academic_year,
                            'marks' => $marks,
                            'grade' => $grade['grade'],
                            'points' => $grade['points'],
                            'remarks' => $grade['remarks'],
                            'entered_by' => $teacher->id,
                        ],
                    );
                }
            }
        }

        if ($teacher) {
            $students = Student::orderBy('id')->limit(4)->get();

            foreach ($students as $index => $student) {
                TeacherNote::updateOrCreate(
                    ['teacher_id' => $teacher->id, 'student_id' => $student->id, 'title' => 'Term 1 progress note'],
                    [
                        'note' => $index === 0
                            ? 'Strong performance in Mathematics. Continue practising word problems.'
                            : 'Good participation in class. Needs to improve consistency in assignments.',
                        'type' => $index === 1 ? 'behavior' : 'academic',
                        'is_read' => $index > 1,
                    ],
                );
            }

            foreach ($students->take(3) as $index => $student) {
                BehaviorRecord::updateOrCreate(
                    ['student_id' => $student->id, 'teacher_id' => $teacher->id, 'date' => now()->subDays(5 + $index)->toDateString(), 'category' => $index === 0 ? 'Leadership' : 'Class participation'],
                    [
                        'description' => $index === 0
                            ? 'Helped organise the class reading group.'
                            : 'Was disruptive during a group activity and later apologised.',
                        'severity' => $index === 0 ? 'low' : 'medium',
                        'action_taken' => $index === 0 ? 'Recognised in class.' : 'Held a short counselling session.',
                    ],
                );
            }
        }

        $assignments = TeacherSubject::with(['subject:id,code,name', 'schoolClass:id,name'])->get();

        foreach ($assignments->unique(fn ($assignment) => $assignment->subject_id.'-'.$assignment->class_id) as $assignment) {
            foreach ([['Foundations and review', 1], ['Core concepts', 2], ['Application and practice', 3]] as $topicIndex => [$title, $week]) {
                $topic = SyllabusTopic::updateOrCreate(
                    [
                        'subject_id' => $assignment->subject_id,
                        'class_id' => $assignment->class_id,
                        'title' => $title,
                        'term' => 'Term 1',
                    ],
                    [
                        'description' => 'Term 1 syllabus coverage for '.$assignment->subject?->name.' in '.$assignment->schoolClass?->name.'.',
                        'week' => $week,
                        'sort_order' => $topicIndex + 1,
                    ],
                );

                if ($assignment->teacher_id) {
                    SyllabusProgress::updateOrCreate(
                        ['syllabus_topic_id' => $topic->id, 'teacher_id' => $assignment->teacher_id],
                        [
                            'status' => $topicIndex === 0 ? 'completed' : ($topicIndex === 1 ? 'in_progress' : 'not_started'),
                            'completion_date' => $topicIndex === 0 ? now()->subWeeks(3)->toDateString() : null,
                            'remarks' => $topicIndex === 0 ? 'Covered and assessed.' : null,
                        ],
                    );
                }
            }
        }
    }
}
