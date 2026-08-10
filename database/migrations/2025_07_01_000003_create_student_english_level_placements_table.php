<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A student's ASSESSED English proficiency over time.
 *
 * This answers "what level is this student at?", which is a different
 * question from "which group are they sitting in?" (teaching_group_student).
 * The two are deliberately independent: a student assessed at Blue may
 * still be attending Green A, and changing one must never silently rewrite
 * the other.
 *
 * No grade or programme column: both are derivable from the student's
 * administrative class, and copying them here would let them drift.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_english_level_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('english_level_id')->constrained()->restrictOnDelete();
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->date('assessed_on')->nullable();
            $table->string('placement_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // One open placement per student; any number of closed ones behind it.
        DB::statement(
            'CREATE UNIQUE INDEX student_english_level_placements_open_unique
             ON student_english_level_placements (student_id)
             WHERE ended_on IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('student_english_level_placements');
    }
};
