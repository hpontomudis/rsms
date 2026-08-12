<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which objectives a module sets out to teach -- many-to-many, because a real
 * module ("Fractions and Equivalent Fractions") routinely addresses several.
 *
 * A real table with a real model rather than a Laravel pivot, because
 * attach()/detach()/sync() bypass Eloquent events and would leave link changes
 * unaudited. This project has proved that empirically often enough.
 *
 * curriculum_scope_id and subject_id are mirrored here so two composite keys
 * can force the module and the objective to share them. All three columns are
 * NOT NULL, so MATCH SIMPLE never skips the check: a Phase C Mathematics module
 * cannot link a Phase D objective, a Phase C English objective, or a Green
 * English objective -- including when the mirrored anchor is falsified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_module_learning_objective', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('teaching_module_id');
            $table->unsignedBigInteger('learning_objective_id');
            // Mirrored anchor -- set by the service, never by a caller.
            $table->unsignedBigInteger('curriculum_scope_id');
            $table->unsignedBigInteger('subject_id');
            $table->timestamps();

            $table->unique(['teaching_module_id', 'learning_objective_id'], 'module_objective_unique');

            $table->foreign(['teaching_module_id', 'curriculum_scope_id', 'subject_id'], 'module_objective_module_anchor_foreign')
                ->references(['id', 'curriculum_scope_id', 'subject_id'])
                ->on('teaching_modules')->restrictOnDelete();

            $table->foreign(['learning_objective_id', 'curriculum_scope_id', 'subject_id'], 'module_objective_objective_anchor_foreign')
                ->references(['id', 'curriculum_scope_id', 'subject_id'])
                ->on('learning_objectives')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_module_learning_objective');
    }
};
