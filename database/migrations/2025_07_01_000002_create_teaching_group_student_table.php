<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Effective-dated group membership, following the pattern proven on
 * class_subject in Step 0.
 *
 * Deliberately NOT the flat unique(group, student) used by class_student:
 * a student may legitimately leave a group and come back later
 * (Green -> Blue -> Green), and that history has to survive. The partial
 * unique index permits exactly one OPEN membership per group+student
 * alongside any number of closed ones, and behaves identically on
 * PostgreSQL and SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_group_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_group_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'ended_on']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX teaching_group_student_open_unique
             ON teaching_group_student (teaching_group_id, student_id)
             WHERE ended_on IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_group_student');
    }
};
