<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dormitories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('gender', 20)->nullable();
            $table->unsignedInteger('capacity')->default(0);
            $table->foreignId('matron_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dormitory_id')->constrained('dormitories')->cascadeOnDelete();
            $table->string('room_number', 50);
            $table->unsignedInteger('capacity')->default(0);
            $table->unsignedInteger('occupied')->default(0);
            $table->timestamps();
            $table->unique(['dormitory_id', 'room_number']);
        });

        Schema::create('beds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->string('bed_number', 50);
            $table->string('status', 20)->default('available');
            $table->timestamps();
            $table->unique(['room_id', 'bed_number']);
        });

        Schema::create('bed_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bed_id')->constrained('beds')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('vacated_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('roll_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dormitory_id')->constrained('dormitories')->cascadeOnDelete();
            $table->string('session', 20);
            $table->date('date');
            $table->foreignId('taken_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('total_present')->default(0);
            $table->unsignedInteger('total_absent')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['dormitory_id', 'session', 'date']);
        });

        Schema::create('student_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('type', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('destination', 150)->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });

        Schema::create('sick_bay', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->timestamp('admitted_at');
            $table->timestamp('discharged_at')->nullable();
            $table->text('complaint');
            $table->text('treatment')->nullable();
            $table->text('medication')->nullable();
            $table->foreignId('attended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('admitted');
            $table->timestamps();
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('dormitory_id')->nullable()->constrained('dormitories')->nullOnDelete();
            $table->date('incident_date');
            $table->string('category', 100);
            $table->text('description');
            $table->string('severity', 20)->default('low');
            $table->text('action_taken')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('sick_bay');
        Schema::dropIfExists('student_leaves');
        Schema::dropIfExists('roll_calls');
        Schema::dropIfExists('bed_assignments');
        Schema::dropIfExists('beds');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('dormitories');
    }
};
