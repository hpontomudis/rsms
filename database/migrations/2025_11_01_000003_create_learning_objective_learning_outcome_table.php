<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which outcomes an objective derives from -- many-to-many, because Phase 5B
 * deliberately allows several CP elements per scope and subject, and a real TP
 * often synthesises more than one of them.
 *
 * `curriculum_scope_id` and `subject_id` are carried here so two composite
 * foreign keys can force both sides to share them. Unlike the Phase 5B
 * discriminator, all three columns are NOT NULL, so MATCH SIMPLE never skips
 * the check and there is no residual application-level gap: the database
 * itself refuses a Phase C Mathematics objective linked to a Phase D outcome,
 * to a Phase C English outcome, or to a Primary English Green outcome.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_objective_learning_outcome', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learning_objective_id');
            $table->unsignedBigInteger('learning_outcome_id');
            // Mirrored anchor -- set by the service, never by a caller.
            $table->unsignedBigInteger('curriculum_scope_id');
            $table->unsignedBigInteger('subject_id');
            $table->timestamps();

            $table->unique(['learning_objective_id', 'learning_outcome_id'], 'lo_lo_link_unique');

            $table->foreign(['learning_objective_id', 'curriculum_scope_id', 'subject_id'], 'lo_link_objective_anchor_foreign')
                ->references(['id', 'curriculum_scope_id', 'subject_id'])
                ->on('learning_objectives')
                ->restrictOnDelete();

            $table->foreign(['learning_outcome_id', 'curriculum_scope_id', 'subject_id'], 'lo_link_outcome_anchor_foreign')
                ->references(['id', 'curriculum_scope_id', 'subject_id'])
                ->on('learning_outcomes')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_objective_learning_outcome');
    }
};
