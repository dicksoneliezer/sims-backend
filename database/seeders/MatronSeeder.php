<?php

namespace Database\Seeders;

use App\Models\Bed;
use App\Models\BedAssignment;
use App\Models\Dormitory;
use App\Models\Incident;
use App\Models\Room;
use App\Models\RollCall;
use App\Models\SickBay;
use App\Models\Student;
use App\Models\StudentLeave;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class MatronSeeder extends Seeder
{
    public function run(): void
    {
        // =========================================
        // 1. DORMITORIES
        // =========================================
        $maleDorm = Dormitory::create([
            'name' => 'Kilimanjaro Boys',
            'gender' => 'male',
            'capacity' => 40,
            'matron_id' => 9, // matron (Boarding Matron)
            'description' => 'Male boarding dormitory - ground floor and first floor.',
            'is_active' => true,
        ]);

        $femaleDorm = Dormitory::create([
            'name' => 'Serengeti Girls',
            'gender' => 'female',
            'capacity' => 40,
            'matron_id' => 9,
            'description' => 'Female boarding dormitory - ground floor and first floor.',
            'is_active' => true,
        ]);

        // =========================================
        // 2. ROOMS (4 per dormitory)
        // =========================================
        $maleRooms = [];
        $femaleRooms = [];

        foreach (range(1, 4) as $i) {
            $maleRooms[] = Room::create([
                'dormitory_id' => $maleDorm->id,
                'room_number' => 'B-' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'capacity' => 10,
                'occupied' => 0,
            ]);

            $femaleRooms[] = Room::create([
                'dormitory_id' => $femaleDorm->id,
                'room_number' => 'G-' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'capacity' => 10,
                'occupied' => 0,
            ]);
        }

        // =========================================
        // 3. BEDS (10 per room = 80 total)
        // =========================================
        $allBeds = [];

        foreach (array_merge($maleRooms, $femaleRooms) as $room) {
            foreach (range(1, 10) as $b) {
                $allBeds[] = Bed::create([
                    'room_id' => $room->id,
                    'bed_number' => 'B' . $b,
                    'status' => 'available',
                ]);
            }
        }

        // =========================================
        // 4. BED ASSIGNMENTS (assign 8 students)
        // =========================================
        $students = Student::where('is_active', true)->orderBy('id')->take(8)->get();
        $matron = User::where('role', 'matron')->first();

        foreach ($students as $index => $student) {
            $bed = $allBeds[$index];

            BedAssignment::create([
                'bed_id' => $bed->id,
                'student_id' => $student->id,
                'assigned_at' => Carbon::now()->subDays(10 - $index),
                'vacated_at' => null,
                'assigned_by' => $matron?->id,
            ]);

            $bed->update(['status' => 'occupied']);
            $bed->room->increment('occupied');
        }

        // =========================================
        // 5. ROLL CALLS (7 days × 3 sessions × 2 dorms = 42)
        // =========================================
        $sessions = ['morning', 'evening', 'night'];

        foreach (range(0, 6) as $daysAgo) {
            $date = Carbon::now()->subDays($daysAgo)->toDateString();

            foreach ([$maleDorm, $femaleDorm] as $dorm) {
                foreach ($sessions as $session) {
                    $present = rand(8, 20);
                    $absent = rand(0, 3);

                    RollCall::create([
                        'dormitory_id' => $dorm->id,
                        'session' => $session,
                        'date' => $date,
                        'taken_by' => $matron?->id,
                        'total_present' => $present,
                        'total_absent' => $absent,
                        'notes' => $absent > 0 ? 'Some students were in sick bay.' : null,
                    ]);
                }
            }
        }

        // =========================================
        // 6. SICK BAY (3 cases)
        // =========================================
        SickBay::create([
            'student_id' => $students[0]->id,
            'admitted_at' => Carbon::now()->subDays(2),
            'discharged_at' => Carbon::now()->subDay(),
            'complaint' => 'Headache and fever.',
            'treatment' => 'Given paracetamol and rest.',
            'medication' => 'Paracetamol 500mg',
            'attended_by' => $matron?->id,
            'status' => 'discharged',
        ]);

        SickBay::create([
            'student_id' => $students[1]->id,
            'admitted_at' => Carbon::now()->subHours(20),
            'discharged_at' => null,
            'complaint' => 'Stomach pain.',
            'treatment' => 'Observed and given ORS.',
            'medication' => 'ORS sachets',
            'attended_by' => $matron?->id,
            'status' => 'admitted',
        ]);

        SickBay::create([
            'student_id' => $students[2]->id,
            'admitted_at' => Carbon::now()->subHours(5),
            'discharged_at' => null,
            'complaint' => 'Sprained ankle during sports.',
            'treatment' => 'Ice pack and bandage applied.',
            'medication' => null,
            'attended_by' => $matron?->id,
            'status' => 'admitted',
        ]);

        // =========================================
        // 7. INCIDENTS (3 cases)
        // =========================================
        Incident::create([
            'student_id' => $students[0]->id,
            'dormitory_id' => $maleDorm->id,
            'incident_date' => Carbon::now()->subDays(3)->toDateString(),
            'category' => 'Late return',
            'description' => 'Student returned to dormitory 30 minutes after curfew.',
            'severity' => 'low',
            'action_taken' => 'Verbal warning issued.',
            'reported_by' => $matron?->id,
            'status' => 'resolved',
        ]);

        Incident::create([
            'student_id' => $students[3]->id,
            'dormitory_id' => $femaleDorm->id,
            'incident_date' => Carbon::now()->subDays(2)->toDateString(),
            'category' => 'Noise complaint',
            'description' => 'Loud music after lights out.',
            'severity' => 'medium',
            'action_taken' => 'Music device confiscated for 24 hours.',
            'reported_by' => $matron?->id,
            'status' => 'investigating',
        ]);

        Incident::create([
            'student_id' => null,
            'dormitory_id' => $maleDorm->id,
            'incident_date' => Carbon::now()->subDay()->toDateString(),
            'category' => 'Damaged property',
            'description' => 'Broken window in room B-02.',
            'severity' => 'high',
            'action_taken' => null,
            'reported_by' => $matron?->id,
            'status' => 'open',
        ]);

        // =========================================
        // 8. STUDENT LEAVES (3 cases)
        // =========================================
        StudentLeave::create([
            'student_id' => $students[0]->id,
            'type' => 'home',
            'start_date' => Carbon::now()->subDays(7)->toDateString(),
            'end_date' => Carbon::now()->subDays(5)->toDateString(),
            'destination' => 'Dar es Salaam',
            'reason' => 'Family event.',
            'approved_by' => $matron?->id,
            'status' => 'completed',
        ]);

        StudentLeave::create([
            'student_id' => $students[4]->id,
            'type' => 'sick',
            'start_date' => Carbon::now()->subDays(2)->toDateString(),
            'end_date' => Carbon::now()->addDays(1)->toDateString(),
            'destination' => 'Home - Mwanza',
            'reason' => 'Recovering from malaria.',
            'approved_by' => $matron?->id,
            'status' => 'approved',
        ]);

        StudentLeave::create([
            'student_id' => $students[5]->id,
            'type' => 'emergency',
            'start_date' => Carbon::now()->subDays(5)->toDateString(),
            'end_date' => Carbon::now()->subDays(3)->toDateString(),
            'destination' => 'Muhimbili Hospital',
            'reason' => 'Family emergency.',
            'approved_by' => null,
            'status' => 'pending',
        ]);

        $this->command->info('✅ Matron seeder completed:');
        $this->command->info('   - 2 dormitories');
        $this->command->info('   - 8 rooms (4 per dormitory)');
        $this->command->info('   - 80 beds');
        $this->command->info('   - 8 bed assignments');
        $this->command->info('   - 42 roll calls (7 days × 3 sessions × 2 dorms)');
        $this->command->info('   - 3 sick bay records');
        $this->command->info('   - 3 incidents');
        $this->command->info('   - 3 student leaves');
    }
}