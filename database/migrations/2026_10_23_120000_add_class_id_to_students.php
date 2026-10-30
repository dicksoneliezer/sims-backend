<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('class_id')
                ->nullable()
                ->after('class')
                ->constrained('school_classes')
                ->nullOnDelete();
        });

        DB::table('students')
            ->whereNotNull('class')
            ->select(['id', 'class'])
            ->orderBy('id')
            ->chunkById(200, function ($students) {
                foreach ($students as $student) {
                    $classId = DB::table('school_classes')->where('name', $student->class)->value('id');

                    if ($classId) {
                        DB::table('students')->where('id', $student->id)->update(['class_id' => $classId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('class_id');
        });
    }
};
