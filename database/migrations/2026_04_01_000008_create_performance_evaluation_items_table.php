<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One indicator's response, for one evaluation.
 *
 * Provisioned automatically -- one row per indicator on the evaluation's
 * framework, created empty when the evaluation itself is created (see
 * PerformanceEvaluationService::create()). No caller ever chooses an
 * indicator id independently, which is what makes "does this indicator
 * belong to this evaluation's framework" a non-issue rather than a check to
 * enforce: the row would not exist otherwise.
 *
 * `performance_framework_id` is MIRRORED from the parent evaluation, set only
 * by the service, and proven correct by a composite foreign key back to
 * performance_evaluations(id, performance_framework_id) -- the same
 * mirrored-discriminator pattern used throughout this project since Phase 5B.
 * That same mirror is what lets `rating_option_id` be proven, by a second
 * composite key, to belong to the identical framework: an option from a
 * different framework is a database-level refusal, not just a service check.
 *
 * Exactly one of the four response columns is populated, per indicator_type --
 * enforced at the service layer (PerformanceEvaluationItemService), since which
 * specific column depends on a sibling column's value in a way no portable
 * CHECK expresses cleanly across both drivers.
 *
 * Every `*_snapshot` column is nullable: null while draft, populated only at
 * finalization.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_evaluation_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('performance_evaluation_id');
            $table->unsignedBigInteger('performance_framework_id');
            $table->unsignedBigInteger('performance_indicator_id');

            $table->unsignedBigInteger('rating_option_id')->nullable();
            $table->decimal('numeric_value', 10, 2)->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->text('narrative_response')->nullable();
            $table->text('evaluator_comment')->nullable();

            $table->string('section_name_snapshot')->nullable();
            $table->unsignedSmallInteger('section_position_snapshot')->nullable();
            $table->string('indicator_name_snapshot')->nullable();
            $table->text('indicator_description_snapshot')->nullable();
            $table->unsignedSmallInteger('indicator_position_snapshot')->nullable();
            $table->string('indicator_type_snapshot')->nullable();
            $table->string('rating_label_snapshot')->nullable();
            $table->smallInteger('rating_value_snapshot')->nullable();

            $table->timestamps();

            $table->unique(['performance_evaluation_id', 'performance_indicator_id'], 'performance_evaluation_item_unique');

            $table->foreign('performance_indicator_id', 'pei_indicator_foreign')
                ->references('id')->on('performance_indicators')->restrictOnDelete();

            $table->foreign(['performance_evaluation_id', 'performance_framework_id'], 'pei_evaluation_anchor_foreign')
                ->references(['id', 'performance_framework_id'])->on('performance_evaluations')->restrictOnDelete();

            $table->foreign(['rating_option_id', 'performance_framework_id'], 'pei_rating_option_anchor_foreign')
                ->references(['id', 'performance_framework_id'])->on('performance_rating_options')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_evaluation_items');
    }
};
