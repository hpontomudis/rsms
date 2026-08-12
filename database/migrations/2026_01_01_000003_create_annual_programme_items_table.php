<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One pathway item allocated to one reporting period, with an optional total
 * teaching-time budget for that period.
 *
 * PERIOD ALLOCATION LIVES HERE AND NOWHERE ELSE. This is the single source of
 * truth for "which semester does this objective belong to". The semester
 * programme answers only "which week inside that semester", so the two layers
 * never restate each other's fact.
 *
 * planned_lesson_periods is the TOTAL JP budget for this item in this period --
 * not a per-week slot. It is nullable because RSMS has no timetable engine to
 * validate it against; when supplied it must be positive, which the service
 * enforces (unsignedSmallInteger already rules out negatives).
 *
 * No copied objective, outcome or pathway text: display derives through
 * learning_pathway_item -> learning_objective.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annual_programme_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('annual_programme_id');
            $table->unsignedBigInteger('learning_pathway_item_id');
            // Mirrored so composite keys can prove the item belongs to the
            // programme's pathway and the period to the programme's year.
            $table->unsignedBigInteger('learning_pathway_id');
            $table->unsignedBigInteger('academic_year_id');
            $table->unsignedBigInteger('academic_period_id');
            $table->unsignedSmallInteger('planned_lesson_periods')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // A pathway item is allocated at most once per annual programme.
            $table->unique(['annual_programme_id', 'learning_pathway_item_id'], 'annual_item_pathway_item_unique');

            $table->foreign(['annual_programme_id', 'academic_year_id'], 'annual_item_programme_year_foreign')
                ->references(['id', 'academic_year_id'])->on('annual_programmes')->restrictOnDelete();

            $table->foreign(['annual_programme_id', 'learning_pathway_id'], 'annual_item_programme_pathway_foreign')
                ->references(['id', 'learning_pathway_id'])->on('annual_programmes')->restrictOnDelete();

            $table->foreign(['learning_pathway_item_id', 'learning_pathway_id'], 'annual_item_pathway_anchor_foreign')
                ->references(['id', 'learning_pathway_id'])->on('learning_pathway_items')->restrictOnDelete();

            // The period must belong to the programme's own academic year.
            $table->foreign(['academic_period_id', 'academic_year_id'], 'annual_item_period_year_foreign')
                ->references(['id', 'academic_year_id'])->on('academic_periods')->restrictOnDelete();
        });

        // Anchor for the semester item's composite key: it is what makes
        // "you may only schedule items this programme assigned to this period"
        // a database rule rather than a hope.
        DB::statement(
            'CREATE UNIQUE INDEX annual_programme_items_period_anchor_unique
             ON annual_programme_items (id, annual_programme_id, academic_period_id)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('annual_programme_items');
    }
};
