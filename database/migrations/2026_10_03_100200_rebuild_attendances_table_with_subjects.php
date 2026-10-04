<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WARNING: this DELETES all existing attendance rows.
 * Old rows have no subject, so they cannot be kept. Students, users and
 * everything else are untouched. Attendance is now one row per
 * student + subject + date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('attendances');

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            // restrict: a subject that already has attendance cannot be deleted by accident.
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->enum('status', ['present', 'absent'])->default('present');
            $table->timestamps();

            $table->unique(['student_id', 'subject_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('status', ['present', 'absent'])->default('present');
            $table->timestamps();

            $table->unique(['student_id', 'date']);
        });
    }
};
