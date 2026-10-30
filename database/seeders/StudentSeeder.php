<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $students = [
            ['student_number' => 'SIMS-2026-0001', 'full_name' => 'Amina Hassan', 'class' => 'Form 1A', 'school_level' => 'secondary', 'gender' => 'female', 'parent_phone' => '+255712345001', 'residence' => 'Mikocheni'],
            ['student_number' => 'SIMS-2026-0002', 'full_name' => 'Baraka Mushi', 'class' => 'Form 1A', 'school_level' => 'secondary', 'gender' => 'male', 'parent_phone' => '+255712345002', 'residence' => 'Mwananyamala'],
            ['student_number' => 'SIMS-2026-0003', 'full_name' => 'Neema Joseph', 'class' => 'Form 2B', 'school_level' => 'secondary', 'gender' => 'female', 'parent_phone' => '+255712345003', 'residence' => 'Sinza'],
            ['student_number' => 'SIMS-2026-0004', 'full_name' => 'Juma Ally', 'class' => 'Form 2B', 'school_level' => 'secondary', 'gender' => 'male', 'parent_phone' => '+255712345004', 'residence' => 'Kijitonyama'],
            ['student_number' => 'SIMS-2026-0005', 'full_name' => 'Grace Mwita', 'class' => 'Standard 5A', 'school_level' => 'primary', 'gender' => 'female', 'parent_phone' => '+255712345005', 'residence' => 'Mbagala'],
            ['student_number' => 'SIMS-2026-0006', 'full_name' => 'Daniel Kimaro', 'class' => 'Standard 5A', 'school_level' => 'primary', 'gender' => 'male', 'parent_phone' => '+255712345006', 'residence' => 'Tabata'],
            ['student_number' => 'SIMS-2026-0007', 'full_name' => 'Fatuma Said', 'class' => 'Form 3C', 'school_level' => 'secondary', 'gender' => 'female', 'parent_phone' => '+255712345007', 'residence' => 'Upanga'],
            ['student_number' => 'SIMS-2026-0008', 'full_name' => 'Peter Msigwa', 'class' => 'Form 4D', 'school_level' => 'secondary', 'gender' => 'male', 'parent_phone' => '+255712345008', 'residence' => 'Kinondoni'],
        ];

        foreach ($students as $data) {
            Student::updateOrCreate(
                ['student_number' => $data['student_number']],
                $data + ['opening_balance' => 0, 'is_active' => true, 'as_of_date' => now()->toDateString()],
            );
        }

        $studentUser = User::where('username', 'student')->first();
        if ($studentUser) {
            Student::where('student_number', 'SIMS-2026-0001')->update(['user_id' => $studentUser->id]);
        }

        $parentUser = User::where('username', 'parent')->first();
        if ($parentUser) {
            $parentUser->children()->syncWithoutDetaching([
                Student::where('student_number', 'SIMS-2026-0001')->value('id') => ['relationship' => 'mother', 'is_primary' => true],
                Student::where('student_number', 'SIMS-2026-0003')->value('id') => ['relationship' => 'mother', 'is_primary' => false],
            ]);
        }
    }
}
