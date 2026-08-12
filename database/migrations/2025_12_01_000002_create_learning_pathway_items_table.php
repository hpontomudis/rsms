<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One objective's place in a pathway's sequence.
 *
 * `position` is the AUTHORITATIVE instructional order and is independent of
 * learning_objectives.reference_order -- the same objectives may legitimately
 * appear in a different order in a different pathway. There is deliberately no
 * parent_item_id, next_item_id or prerequisite edge: a different valid ordering
 * is a different pathway, not a branch inside this one.
 *
 * curriculum_scope_id and subject_id are mirrored so composite foreign keys can
 * force the item, its pathway and its objective to share an anchor. All columns
 * NOT NULL, so the database refuses a cross-phase, cross-subject or
 * national/English cross-scope item outright.
 *
 * No direct CP link: traceability runs pathway -> item -> TP -> CP, and a
 * second path could only disagree with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_pathway_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learning_pathway_id');
            $table->unsignedBigInteger('learning_objective_id');
            $table->unsignedBigInteger('curriculum_scope_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedSmallInteger('position');
            // Sequencing rationale -- why this objective sits here. Never a
            // copy of the objective's own text.
            $table->text('notes')->nullable();
            $table->timestamps();

            // An objective appears at most once. A pathway is an ordered set of
            // goals, not a schedule of every occasion one is revisited --
            // revisiting belongs to the semester plan and to teaching modules.
            $table->unique(['learning_pathway_id', 'learning_objective_id'], 'pathway_item_objective_unique');

            $table->foreign(['learning_pathway_id', 'curriculum_scope_id', 'subject_id'], 'pathway_item_pathway_anchor_foreign')
                ->references(['id', 'curriculum_scope_id', 'subject_id'])
                ->on('learning_pathways')
                ->restrictOnDelete();

            $table->foreign(['learning_objective_id', 'curriculum_scope_id', 'subject_id'], 'pathway_item_objective_anchor_foreign')
                ->references(['id', 'curriculum_scope_id', 'subject_id'])
                ->on('learning_objectives')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_pathway_items');
    }
};
