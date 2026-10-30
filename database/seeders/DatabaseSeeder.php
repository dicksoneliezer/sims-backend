<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserRoleSeeder::class,
            StudentSeeder::class,
            AcademicSeeder::class,
            FinanceSeeder::class,
            InventorySeeder::class,
            AdminSettingsSeeder::class,
            TeachingSeeder::class,
            MatronSeeder::class,   // <-- MPYA (inaenda mwisho kwa sababu inahitaji students)
        ]);
    }
}