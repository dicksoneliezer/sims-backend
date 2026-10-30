<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dormitory_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dormitory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('inspection_date');
            $table->unsignedTinyInteger('hygiene_score')->default(0);     // 0-100
            $table->unsignedTinyInteger('cleanliness_score')->default(0); // 0-100
            $table->text('issues_found')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending'); // pending, passed, failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dormitory_inspections');
    }
};