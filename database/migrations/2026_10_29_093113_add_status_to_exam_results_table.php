<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('exam_results', 'status')) {
            Schema::table('exam_results', function (Blueprint $table) {
                $table->string('status', 20)->default('pending')->after('points');
                $table->timestamp('approved_at')->nullable()->after('status');
                $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::table('exam_results', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['status', 'approved_at', 'approved_by']);
        });
    }
};