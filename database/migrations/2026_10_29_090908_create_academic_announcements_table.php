<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('academic_announcements')) {
            Schema::create('academic_announcements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
                $table->string('title', 150);
                $table->text('body');
                $table->string('audience', 50)->default('all_teachers');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_announcements');
    }
};