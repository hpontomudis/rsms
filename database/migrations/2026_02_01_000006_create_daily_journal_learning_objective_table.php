<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which objectives were ACTUALLY addressed in a session.
 *
 * Deliberately its own table rather than inherited from the module, because the
 * whole point of a journal is that plan and actual may differ: a module planned
 * TP1 and TP2, the lesson got through TP1. Inferring the actual from the plan
 * would destroy exactly the information this layer exists to hold, so the two
 * sets are independent and the journal's need not be a subset of the module's.
 *
 * Same mirrored-anchor integrity as the module's objective links.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_journal_learning_objective', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('daily_journal_id');
            $table->unsignedBigInteger('learning_objective_id');
            $table->unsignedBigInteger('curriculum_scope_id');
            $table->unsignedBigInteger('subject_id');
            $table->timestamps();

            $table->unique(['daily_journal_id', 'learning_objective_id'], 'journal_objective_unique');

            $table->foreign(['daily_journal_id', 'curriculum_scope_id', 'subject_id'], 'journal_objective_journal_anchor_foreign')
                ->references(['id', 'curriculum_scope_id', 'subject_id'])
                ->on('daily_journals')->restrictOnDelete();

            $table->foreign(['learning_objective_id', 'curriculum_scope_id', 'subject_id'], 'journal_objective_objective_anchor_foreign')
                ->references(['id', 'curriculum_scope_id', 'subject_id'])
                ->on('learning_objectives')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_journal_learning_objective');
    }
};
