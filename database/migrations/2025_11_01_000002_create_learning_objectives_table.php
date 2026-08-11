<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tujuan Pembelajaran on the national curriculum; Learning Objective on a
 * Rahai English one. One table, wording derived from the curriculum.
 *
 * Anchored to a curriculum scope and a subject -- NOT to a grade, a class, a
 * teaching group or an academic year. A Phase C objective serves Year 5 and
 * Year 6 alike, and which grade works on which part is a teaching decision
 * that ATP and teaching assignments will make later, without moving the
 * objective.
 *
 * Unlike learning_outcomes, this table DOES carry a status. An outcome had no
 * authoring lifecycle of its own -- it inherited the curriculum's. An
 * objective genuinely does: the school formulates and revises TP while a
 * curriculum is in force, so a draft successor must be able to sit alongside
 * the active TP it will replace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_scope_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->string('code')->nullable();
            $table->string('title')->nullable();
            $table->text('objective_text');
            // Library display order ONLY. ATP owns instructional sequence and
            // may select a subset in a different order; this must never be
            // mistaken for it, which is why it is not called `sequence`.
            $table->unsignedSmallInteger('reference_order');
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->timestamps();
        });

        // The anchor, referenceable by the pivot's composite foreign key.
        DB::statement(
            'CREATE UNIQUE INDEX learning_objectives_anchor_unique
             ON learning_objectives (id, curriculum_scope_id, subject_id)'
        );

        // Uniqueness applies to the ACTIVE library only. A draft successor may
        // deliberately share its predecessor's reference order and code while
        // it is being prepared; only one of them may be in force.
        DB::statement(
            "CREATE UNIQUE INDEX learning_objectives_active_order_unique
             ON learning_objectives (curriculum_scope_id, subject_id, reference_order)
             WHERE status = 'active'"
        );
        DB::statement(
            "CREATE UNIQUE INDEX learning_objectives_active_code_unique
             ON learning_objectives (curriculum_scope_id, subject_id, code)
             WHERE code IS NOT NULL AND status = 'active'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_objectives');
    }
};
