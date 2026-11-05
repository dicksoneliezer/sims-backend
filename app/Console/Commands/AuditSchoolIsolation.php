<?php

namespace App\Console\Commands;

use App\Services\SchoolTables;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Signature('school:audit')]
#[Description('Check school ownership and cross-school references without changing records')]
class AuditSchoolIsolation extends Command
{
    public function handle(): int
    {
        if (! Schema::hasTable('schools')) {
            $admins = DB::table('users')->where('role', 'super_admin')->count();
            $this->info('Pre-migration school administrators: '.$admins);
            $this->info('Existing users: '.DB::table('users')->count());
            $this->info('Existing students: '.DB::table('students')->count());
            if ($admins > 1) {
                $this->error('An ownership map is required before migrating multiple existing school administrators.');

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        $errors = 0;
        foreach (SchoolTables::TABLES as $table) {
            $records = DB::table($table.' as owned');
            if ($table === 'users') {
                $invalidPlatform = DB::table('users')->where('role', 'system_admin')->whereNotNull('school_id')->count();
                $errors += $invalidPlatform;
                $records->where('owned.role', '!=', 'system_admin');
            }
            $invalid = (clone $records)->leftJoin('schools', 'schools.id', '=', 'owned.school_id')->whereNull('schools.id')->count();
            $errors += $invalid;
            foreach (SchoolTables::foreignKeys($table) as $foreignKey) {
                if (! SchoolTables::contains($foreignKey['foreign_table']) || count($foreignKey['columns']) !== 1) {
                    continue;
                }
                $column = $foreignKey['columns'][0];
                $query = (clone $records)->join($foreignKey['foreign_table'].' as related', 'related.'.$foreignKey['foreign_columns'][0], '=', 'owned.'.$column);
                // Legacy audit actors can be platform administrators with no school.
                if ($foreignKey['foreign_table'] === 'users') {
                    $query->where('related.role', '!=', 'system_admin');
                }
                $crossSchool = $query->whereColumn('owned.school_id', '!=', 'related.school_id')->count();
                if ($crossSchool) {
                    $this->error($table.'.'.$column.': '.$crossSchool.' cross-school references');
                }
                $errors += $crossSchool;
            }
            if ($invalid) {
                $this->error($table.': '.$invalid.' records without a valid school');
            }
        }
        $this->info('Schools: '.DB::table('schools')->count());
        $this->info('School users: '.DB::table('users')->where('role', '!=', 'system_admin')->count());
        $this->info('Students: '.DB::table('students')->count());
        $this->info('Ownership errors: '.$errors);

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
