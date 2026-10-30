<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('parent_messages')) {
            Schema::create('parent_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parent_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('student_id')->nullable()->constrained('students')->onDelete('set null');
                $table->foreignId('sender_id')->nullable()->constrained('users')->onDelete('set null');
                $table->string('subject', 150);
                $table->text('message');
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_messages');
    }
};