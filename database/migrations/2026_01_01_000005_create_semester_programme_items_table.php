<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One scheduling SLOT inside a semester programme.
 *
 * Deliberately NOT unique on (semester_programme, annual_programme_item): one
 * annual item routinely needs several slots. An 8 JP objective might be taught
 * in week 3 (2 JP), week 4 (2 JP) and week 6 (4 JP) -- three rows, one budget.
 * A uniqueness rule here would have forced the school to either lie about the
 * schedule or split the objective.
 *
 * No learning_pathway_item_id: it is already determined by
 * annual_programme_item_id, and a second path to the same fact could disagree
 * with the first.
 *
 * week_label is a free string ("Week 3", "Minggu Efektif 7", "After Mid-Semester
 * Assessment") rather than a number, honouring the standing commitment that
 * weeks are labels and not a rigid calendar. Dates, when known, are separate.
 *
 * planned_lesson_periods here is the JP for THIS slot, not the annual total.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semester_programme_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('semester_programme_id');
            $table->unsignedBigInteger('annual_programme_item_id');
            // Mirrored so the composite keys below can prove that the item
            // being scheduled was allocated by THIS programme to THIS period.
            $table->unsignedBigInteger('annual_programme_id');
            $table->unsignedBigInteger('academic_period_id');
            $table->unsignedSmallInteger('position');
            $table->string('week_label')->nullable();
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->unsignedSmallInteger('planned_lesson_periods')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['semester_programme_id', 'position'], 'semester_item_order_index');

            $table->foreign(['semester_programme_id', 'annual_programme_id', 'academic_period_id'], 'semester_item_programme_anchor_foreign')
                ->references(['id', 'annual_programme_id', 'academic_period_id'])
                ->on('semester_programmes')->restrictOnDelete();

            // The annual item must belong to the same programme AND the same
            // period: a Semester 2 allocation cannot be scheduled in Semester 1.
            $table->foreign(['annual_programme_item_id', 'annual_programme_id', 'academic_period_id'], 'semester_item_annual_anchor_foreign')
                ->references(['id', 'annual_programme_id', 'academic_period_id'])
                ->on('annual_programme_items')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semester_programme_items');
    }
};
