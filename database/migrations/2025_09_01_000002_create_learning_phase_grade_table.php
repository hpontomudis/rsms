<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which grades sit in which learning phase.
 *
 * Deliberately a mapping table rather than a learning_phase_id column on
 * `grades`, for the same reason english_programme_grade exists: the Foundation
 * grade table describes what a grade IS, not which curricular frameworks
 * happen to classify it. A grade already carries an English programme
 * applicability the same way.
 *
 * UNIQUE(grade_id) -- Rahai's rule today is that a grade belongs to at most
 * one phase. A phase holds many grades; Phase C holds Year 5 and Year 6.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_phase_grade', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_phase_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique('grade_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_phase_grade');
    }
};
