<?php

namespace App\Services;

use Illuminate\Support\Facades\Schema;

class SchoolTables
{
    public const TABLES = [
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

    private static array $foreignKeys = [];

    public static function contains(string $table): bool
    {
        return in_array($table, self::TABLES, true);
    }

    public static function foreignKeys(string $table): array
    {
        return self::$foreignKeys[$table] ??= Schema::getForeignKeys($table);
    }
}
