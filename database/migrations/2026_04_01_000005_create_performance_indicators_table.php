<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One thing being evaluated within a section -- "Maintains Annual Programme",
 * "Classroom environment" -- and how it is answered.
 *
 * `system_evidence_key` is validated against a controlled PHP registry
 * (App\Evidence\EvidenceRegistry), never arbitrary SQL or a JSON DSL. Null
 * means the indicator has no automated evidence source at all, which is
 * entirely normal for narrative and most rubric indicators.
 *
 * `target_value`/`unit_label` are INFORMATIONAL ONLY -- a number for a human to
 * compare against evidence by eye. No code path turns "evidence >= target"
 * into a rating; that would be exactly the auto-rating this domain forbids.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_section_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('indicator_type', ['rubric', 'numeric', 'boolean', 'narrative']);
            $table->unsignedSmallInteger('position');
            $table->string('system_evidence_key')->nullable();
            $table->decimal('target_value', 8, 2)->nullable();
            $table->string('unit_label')->nullable();
            $table->timestamps();

            $table->unique(['performance_section_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_indicators');
    }
};
