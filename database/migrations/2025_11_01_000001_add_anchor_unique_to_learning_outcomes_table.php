<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A composite foreign key can only point at columns that carry a unique index.
 * Learning objectives will link to outcomes through a pivot that must prove
 * both sides share a curriculum scope and a subject, so the outcome's anchor
 * needs to be referenceable as a unit.
 *
 * Additive and redundant on its own -- `id` is already unique -- but it is
 * what lets the database, rather than the application, refuse a Phase C
 * Mathematics objective linked to a Phase D Mathematics outcome.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX learning_outcomes_anchor_unique
             ON learning_outcomes (id, curriculum_scope_id, subject_id)'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX learning_outcomes_anchor_unique');
    }
};
