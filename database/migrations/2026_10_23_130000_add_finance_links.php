<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_payments', function (Blueprint $table) {
            $table->foreignId('invoice_id')
                ->nullable()
                ->after('student_id')
                ->constrained('invoices')
                ->nullOnDelete();
            $table->timestamp('expires_at')->nullable()->after('completed_at');
            $table->index(['status', 'expires_at']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('fee_structure_id')
                ->nullable()
                ->after('student_id')
                ->constrained('fee_structure')
                ->nullOnDelete();
            $table->unique(['student_id', 'fee_structure_id']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['student_id', 'fee_structure_id']);
            $table->dropConstrainedForeignId('fee_structure_id');
        });

        Schema::table('student_payments', function (Blueprint $table) {
            $table->dropIndex(['status', 'expires_at']);
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropColumn('expires_at');
        });
    }
};
