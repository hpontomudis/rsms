<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which assessment activities were actually used in a session.
 *
 * Records only THAT an assessment happened. Every mark stays in
 * assessment_results, which remains the single score store.
 *
 * The mirrored class_subject_id lets two composite keys prove the assessment
 * belongs to the same teaching assignment as the journal, so a Green English
 * journal cannot cite a Year 5 Mathematics assessment. Both columns are NOT
 * NULL, so the check is never skipped.
 *
 * The consequence is deliberate: an assessment belongs to one assignment, so a
 * successor cannot cite a predecessor's assessment. Unlike a module -- a
 * reusable plan -- an assessment is a specific historical event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_journal_assessment', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('daily_journal_id');
            $table->unsignedBigInteger('assessment_id');
            $table->unsignedBigInteger('class_subject_id');
            $table->timestamps();

            $table->unique(['daily_journal_id', 'assessment_id'], 'journal_assessment_unique');

            $table->foreign(['daily_journal_id', 'class_subject_id'], 'journal_assessment_journal_anchor_foreign')
                ->references(['id', 'class_subject_id'])
                ->on('daily_journals')->restrictOnDelete();

            $table->foreign(['assessment_id', 'class_subject_id'], 'journal_assessment_assessment_anchor_foreign')
                ->references(['id', 'class_subject_id'])
                ->on('assessments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_journal_assessment');
    }
};
