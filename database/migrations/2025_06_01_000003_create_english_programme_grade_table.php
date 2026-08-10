<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which grades a programme applies to.
 *
 * UNIQUE(grade_id) -- not a composite -- because a grade belongs to AT MOST ONE
 * English programme. A globally unique grade_id already prevents a duplicate
 * (programme, grade) pair, so a composite unique would be redundant.
 *
 * Grades with no row here (KG1/KG2, Year 10-12) simply have no proficiency
 * programme; Senior High teaches English as an ordinary class-based subject.
 *
 * Kept as its own table rather than a column on `grades` so an English-specific
 * concept doesn't get welded onto a shared Foundation table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('english_programme_grade', function (Blueprint $table) {
            $table->id();
            $table->foreignId('english_programme_id')->constrained('english_programmes')->restrictOnDelete();
            $table->foreignId('grade_id')->constrained('grades')->restrictOnDelete();
            $table->timestamps();

            $table->unique('grade_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('english_programme_grade');
    }
};
