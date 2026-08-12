<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One subject line on an issued report card.
 *
 * `subject_name_snapshot` is what the document prints; `subject_id` is kept
 * only so the system can still answer which subject it was. Renaming "Maths"
 * to "Matematika" in 2028 must not rewrite a rapor issued in 2026.
 *
 * `score` is the PERIOD average as issued -- the existing formula, frozen.
 *
 * Deliberately NOT snapshotted here: assessments, assessment results, teaching
 * groups, English levels, teaching assignments. A rapor shows a subject result;
 * the evidence behind it stays canonical and reachable through the live views.
 *
 * These rows are written only inside the publication transaction and are never
 * edited afterwards, which is why they do not carry the Auditable trait: the
 * parent's audit row plus the frozen snapshot is the whole history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_record_subjects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('academic_record_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_name_snapshot');
            $table->unsignedSmallInteger('score')->nullable();
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(['academic_record_id', 'subject_id'], 'academic_record_subject_unique');

            $table->foreign('academic_record_id')->references('id')->on('academic_records')->restrictOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_record_subjects');
    }
};
