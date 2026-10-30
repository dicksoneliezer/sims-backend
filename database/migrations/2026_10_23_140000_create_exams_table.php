<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('type', 20)->default('exam')->index();
            $table->string('term', 20)->nullable()->index();
            $table->string('academic_year', 9)->nullable()->index();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('exam_results', function (Blueprint $table) {
            $table->foreignId('exam_id')->nullable()->after('id')->constrained('exams')->nullOnDelete();
        });

        $groups = DB::table('exam_results')
            ->select('exam_name', 'exam_type', 'term', 'academic_year')
            ->distinct()
            ->get();

        foreach ($groups as $group) {
            $examId = DB::table('exams')->insertGetId([
                'name' => $group->exam_name,
                'type' => $group->exam_type ?: 'exam',
                'term' => $group->term,
                'academic_year' => $group->academic_year,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('exam_results')
                ->where('exam_name', $group->exam_name)
                ->where(function ($query) use ($group) {
                    $group->exam_type
                        ? $query->where('exam_type', $group->exam_type)
                        : $query->whereNull('exam_type');
                })
                ->where(function ($query) use ($group) {
                    $group->term
                        ? $query->where('term', $group->term)
                        : $query->whereNull('term');
                })
                ->where(function ($query) use ($group) {
                    $group->academic_year
                        ? $query->where('academic_year', $group->academic_year)
                        : $query->whereNull('academic_year');
                })
                ->update(['exam_id' => $examId]);
        }
    }

    public function down(): void
    {
        Schema::table('exam_results', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exam_id');
        });

        Schema::dropIfExists('exams');
    }
};
