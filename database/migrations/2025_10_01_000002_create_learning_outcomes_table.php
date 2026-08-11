<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A learning outcome within a curriculum scope.
 *
 * ONE table for both frameworks. On the national curriculum a row is a Capaian
 * Pembelajaran; on a Rahai English curriculum it is an English Learning
 * Outcome. The wording differs in the UI, derived from the parent curriculum;
 * the structure does not, so there is no national_cp / english_outcomes split
 * to keep in sync.
 *
 * NO grade_id, deliberately and permanently. National CP is scoped through a
 * Learning Phase: Phase C covers Year 5 and Year 6 and has ONE outcome set.
 * A grade column would let the same standard be written twice and drift.
 *
 * NO status column either. The parent curriculum already carries
 * draft/active/archived, outcomes are immutable once it leaves draft, and a
 * second lifecycle would only create a way to mutate an active standard.
 * `outcome_text` is TEXT because official CP narratives are paragraphs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_scope_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            // Optional reference from an official document, e.g. a CP element code.
            $table->string('code')->nullable();
            $table->string('title')->nullable();
            $table->text('outcome_text');
            $table->unsignedSmallInteger('sequence');
            $table->timestamps();

            // Ordering, not identity: several outcomes may share a scope and a
            // subject when the school breaks an official CP into elements.
            // Deliberately NOT unique(scope, subject) -- that would force
            // one row per subject and make element-level CP impossible.
            $table->unique(['curriculum_scope_id', 'subject_id', 'sequence']);
        });

        // A code, where one is used, identifies an outcome within its scope.
        // Partial so the many outcomes without a code do not collide.
        DB::statement(
            'CREATE UNIQUE INDEX learning_outcomes_scope_code_unique
             ON learning_outcomes (curriculum_scope_id, code)
             WHERE code IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_outcomes');
    }
};
