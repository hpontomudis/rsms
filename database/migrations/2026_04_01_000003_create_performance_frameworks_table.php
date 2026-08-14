<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The school's definition of what good performance means, for ONE category of
 * staff. Teachers, drivers and security each need different criteria, so a
 * framework is scoped to exactly one `staff_category_id` -- never inferred
 * from `positions.title`, which cannot safely carry this distinction.
 *
 * Lifecycle mirrors Curriculum exactly: draft is editable and cannot be
 * evaluated against; active freezes the structure (sections, indicators,
 * rating options) and permits new evaluations; archived stops new evaluations
 * but changes nothing about evaluations already in flight or already
 * finalized against this exact version. Superseding a framework is
 * archive-and-create, never an in-place rewrite -- the same reasoning Curriculum
 * and LearningPathway already established: once something points at a version,
 * rewriting it would retroactively change what that thing was evaluated against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_frameworks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('version');
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['code', 'version']);
        });

        // Anchor for the rating-option composite FK and for evaluation items
        // to prove an option belongs to the same framework as the indicator.
        DB::statement('CREATE UNIQUE INDEX performance_frameworks_anchor_unique ON performance_frameworks (id, staff_category_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_frameworks');
    }
};
