<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('level', 30)->index();
            $table->string('section', 30)->nullable();
            $table->unsignedInteger('capacity')->default(0);
            $table->foreignId('class_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('student_number', 50)->unique();
            $table->string('full_name', 150);
            $table->string('class', 50)->nullable()->index();
            $table->string('school_level', 30)->nullable()->index();
            $table->string('prem_number', 50)->nullable();
            $table->string('psle_number', 50)->nullable();
            $table->string('index_number', 50)->nullable();
            $table->string('parent_phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('residence', 150)->nullable();
            $table->string('gender', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->date('as_of_date')->nullable();
            $table->string('photo')->nullable();
            $table->boolean('is_active')->default(true)->index();
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('level', 30)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('parent_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('relationship', 30)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['parent_id', 'student_id']);
        });

        Schema::create('teacher_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->string('academic_year', 9)->nullable();
            $table->timestamps();
            $table->unique(['teacher_id', 'class_id', 'academic_year']);
        });

        Schema::create('teacher_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->string('academic_year', 9)->nullable();
            $table->timestamps();
            $table->unique(['teacher_id', 'subject_id', 'class_id', 'academic_year'], 'teacher_subject_unique');
        });

        Schema::create('teacher_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('note');
            $table->string('type', 30)->default('general');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });

        Schema::create('teacher_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 30)->default('present');
            $table->string('photo')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['teacher_id', 'date']);
        });

        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 20)->default('present')->index();
            $table->text('remarks')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'date']);
        });

        Schema::create('exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->string('exam_name', 100);
            $table->string('exam_type', 50)->nullable();
            $table->string('term', 20)->nullable();
            $table->string('academic_year', 9)->nullable();
            $table->decimal('marks', 5, 2);
            $table->string('grade', 10)->nullable();
            $table->integer('points')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['student_id', 'term', 'academic_year']);
        });

        Schema::create('behavior_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date');
            $table->string('category', 100);
            $table->text('description');
            $table->string('severity', 20)->default('low');
            $table->text('action_taken')->nullable();
            $table->timestamps();
        });

        Schema::create('timetables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('day_of_week', 20);
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room', 50)->nullable();
            $table->string('term', 20)->nullable();
            $table->string('academic_year', 9)->nullable();
            $table->timestamps();
        });

        Schema::create('school_calendar', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('type', 30)->default('event');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('syllabus_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('term', 20)->nullable();
            $table->unsignedTinyInteger('week')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('syllabus_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('syllabus_topic_id')->constrained('syllabus_topics')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 30)->default('pending');
            $table->date('completion_date')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['syllabus_topic_id', 'teacher_id']);
        });

        Schema::create('grading_system', function (Blueprint $table) {
            $table->id();
            $table->string('grade', 10);
            $table->decimal('min_marks', 5, 2);
            $table->decimal('max_marks', 5, 2);
            $table->integer('points')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_system');
        Schema::dropIfExists('syllabus_progress');
        Schema::dropIfExists('syllabus_topics');
        Schema::dropIfExists('school_calendar');
        Schema::dropIfExists('timetables');
        Schema::dropIfExists('behavior_records');
        Schema::dropIfExists('exam_results');
        Schema::dropIfExists('attendance');
        Schema::dropIfExists('teacher_attendance');
        Schema::dropIfExists('teacher_notes');
        Schema::dropIfExists('teacher_subjects');
        Schema::dropIfExists('teacher_classes');
        Schema::dropIfExists('parent_students');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('students');
        Schema::dropIfExists('school_classes');
    }
};
