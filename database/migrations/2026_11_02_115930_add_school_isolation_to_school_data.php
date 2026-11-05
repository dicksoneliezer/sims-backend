<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'academic_announcements',
        'asset_maintenance',
        'assets',
        'attendance',
        'backup_records',
        'bank_statements',
        'bank_transactions',
        'bed_assignments',
        'beds',
        'behavior_records',
        'daily_settlements',
        'departments',
        'dormitories',
        'dormitory_inspections',
        'exam_results',
        'exams',
        'expenses',
        'fee_structure',
        'gallery',
        'grading_system',
        'incidents',
        'inventory_items',
        'invoices',
        'low_stock_alerts',
        'parent_messages',
        'parent_students',
        'reconciliation_reports',
        'revenue_categories',
        'roll_calls',
        'rooms',
        'school_calendar',
        'school_classes',
        'school_settings',
        'sick_bay',
        'smart_cards',
        'stock_transactions',
        'student_leaves',
        'student_payments',
        'students',
        'subjects',
        'syllabus_progress',
        'syllabus_topics',
        'system_logs',
        'system_settings',
        'teacher_attendance',
        'teacher_classes',
        'teacher_notes',
        'teacher_subjects',
        'timetables',
        'transactions',
        'users',
    ];

    public function up(): void
    {
        if (DB::table('users')->where('role', 'super_admin')->count() > 1) {
            throw new RuntimeException('Multiple existing school administrators require an explicit ownership map before this migration.');
        }

        Schema::create('schools', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 50)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $legacyName = DB::table('school_settings')->where('key', 'school_name')->value('value') ?: 'Existing School';
        $schoolId = DB::table('schools')->insertGetId([
            'name' => $legacyName, 'code' => 'SCH-000001', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('school_id')->nullable()->constrained('schools')->restrictOnDelete();
                $table->index(['school_id', 'id']);
            });
            DB::table($name)->update(['school_id' => $schoolId]);

            if ($name === 'users') {
                DB::table($name)->where('role', 'system_admin')->update(['school_id' => null]);

                continue;
            }

            foreach (Schema::getIndexes($name) as $index) {
                if ($index['unique'] && ! $index['primary'] && count($index['columns']) === 1 && ! in_array($index['columns'][0], ['user_id', 'control_number', 'card_number', 'transaction_ref'], true)) {
                    $column = $index['columns'][0];
                    Schema::table($name, function (Blueprint $table) use ($name, $index, $column): void {
                        $table->dropUnique($index['name']);
                        $table->unique(['school_id', $column], 'school_'.substr(hash('sha256', $name.'.'.$column), 0, 16).'_unique');
                    });
                }
            }
            Schema::table($name, function (Blueprint $table): void {
                $table->unsignedBigInteger('school_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('School isolation must not be rolled back by merging schools. Restore the verified pre-migration backup instead.');
    }
};
