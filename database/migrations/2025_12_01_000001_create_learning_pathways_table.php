<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A systematic, LINEAR sequence of learning objectives through one curriculum
 * scope and subject. Alur Tujuan Pembelajaran on the national curriculum;
 * Learning Path on a Rahai English one.
 *
 * Physically neutral naming on purpose. "ATP" is the national acronym and this
 * same engine serves the English programme, so baking it into the schema would
 * be the mistake that naming the CP table `capaian_pembelajaran` would have
 * been. Vocabulary is derived from the curriculum, as with outcomes and
 * objectives.
 *
 * Anchored to scope + subject, never to a grade, class, teaching group,
 * teaching assignment or academic year. A Phase C pathway covers the whole
 * phase -- Year 5 AND Year 6 -- and splitting it per grade would duplicate the
 * sequence and make "where does Year 6 resume?" unanswerable. When and by whom
 * parts of it are taught is the next planning layer's question.
 *
 * Several pathways may be ACTIVE at once for one scope + subject: they are
 * approved alternative routes, not competing versions. So unlike learning
 * objectives there is deliberately no one-active-per-anchor rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_pathways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_scope_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->string('code')->nullable();
            // Required: an unnamed pathway is unusable in a picker.
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->timestamps();
        });

        // The anchor, referenceable by the item's composite foreign key.
        DB::statement(
            'CREATE UNIQUE INDEX learning_pathways_anchor_unique
             ON learning_pathways (id, curriculum_scope_id, subject_id)'
        );

        // Codes are unique among ACTIVE pathways only, so a draft replacement
        // may carry its predecessor's code while it is prepared. Two pathways
        // in force cannot both answer to the same code.
        DB::statement(
            "CREATE UNIQUE INDEX learning_pathways_active_code_unique
             ON learning_pathways (curriculum_scope_id, subject_id, code)
             WHERE code IS NOT NULL AND status = 'active'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_pathways');
    }
};
