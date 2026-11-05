<?php

namespace Database\Seeders;

use App\Models\School;
use App\Services\SchoolContext;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(SchoolContext::class)->set((int) School::query()->oldest('id')->value('id'));
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
