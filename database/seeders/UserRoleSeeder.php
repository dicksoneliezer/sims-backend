<?php

namespace Database\Seeders;

use App\Models\Scopes\SchoolScope;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserRoleSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['username' => 'admin', 'full_name' => 'Super Administrator', 'email' => 'admin@sims.co.tz', 'role' => 'super_admin', 'password' => 'admin123'],
            ['username' => 'sysadmin', 'full_name' => 'System Administrator', 'email' => 'sysadmin@sims.co.tz', 'role' => 'system_admin', 'password' => 'sysadmin123'],
            ['username' => 'academic', 'full_name' => 'Academic Teacher', 'email' => 'academic@sims.co.tz', 'role' => 'academic_teacher', 'password' => 'academic123'],
            ['username' => 'teacher', 'full_name' => 'Class Teacher', 'email' => 'teacher@sims.co.tz', 'role' => 'teacher', 'password' => 'teacher123'],
            ['username' => 'teacher2', 'full_name' => 'Science Teacher', 'email' => 'teacher2@sims.co.tz', 'role' => 'teacher', 'password' => 'teacher123'],
            ['username' => 'teacher3', 'full_name' => 'Languages Teacher', 'email' => 'teacher3@sims.co.tz', 'role' => 'teacher', 'password' => 'teacher123'],
            ['username' => 'accountant', 'full_name' => 'School Accountant', 'email' => 'accountant@sims.co.tz', 'role' => 'accountant', 'password' => 'accountant123'],
            ['username' => 'storekeeper', 'full_name' => 'Store Keeper', 'email' => 'storekeeper@sims.co.tz', 'role' => 'store_keeper', 'password' => 'store123'],
            ['username' => 'matron', 'full_name' => 'Boarding Matron', 'email' => 'matron@sims.co.tz', 'role' => 'matron', 'password' => 'matron123'],
            ['username' => 'student', 'full_name' => 'Demo Student', 'email' => 'student@sims.co.tz', 'role' => 'student', 'password' => 'student123'],
            ['username' => 'parent', 'full_name' => 'Demo Parent', 'email' => 'parent@sims.co.tz', 'role' => 'parent', 'password' => 'parent123'],
        ];

        foreach ($users as $user) {
            ($user['role'] === 'system_admin' ? User::withoutGlobalScope(SchoolScope::class) : User::query())->updateOrCreate(
                ['username' => $user['username']],
                $user + ['is_active' => true],
            );
        }
    }
}
