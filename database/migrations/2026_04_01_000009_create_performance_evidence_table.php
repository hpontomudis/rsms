<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A typed evidence entry -- system-computed or human-authored -- attached to
 * one evaluation item.
 *
 * `numeric_value` is DECIMAL, not integer: evidence may be a truthful count
 * today and a percentage or other fractional measure tomorrow, and this
 * column should not have to be widened later for that.
 *
 * `availability` is a first-class column, never inferred from a null value --
 * "unavailable" (an ambiguous User<->Staff mapping, say) must never render or
 * be stored indistinguishably from a genuine zero.
 *
 * Auditable, unlike academic_record_subjects / teaching_module_semester_
 * programme_item and every other write-once child in this project: manual
 * evidence genuinely has an independent create/edit/delete workflow while the
 * parent evaluation is draft, which is exactly the distinction those rows do
 * NOT have. Real model, explicit writes only -- no attach()/detach()/sync().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_evaluation_item_id')->constrained()->restrictOnDelete();
            $table->enum('source_type', ['system', 'manual']);
            $table->string('source_key')->nullable();
            $table->string('source_label');
            $table->enum('availability', ['available', 'unavailable']);
            $table->decimal('numeric_value', 12, 2)->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->text('text_value')->nullable();
            $table->date('date_range_start')->nullable();
            $table->date('date_range_end')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_evidence');
    }
};
