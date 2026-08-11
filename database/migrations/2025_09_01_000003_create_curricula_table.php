<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A versioned curriculum registry.
 *
 * A curriculum VERSION is a first-class historical record, never a row that
 * gets overwritten when standards change: work recorded under the 2025
 * standards must keep pointing at the 2025 standards. Superseding a version
 * means archiving it and opening a new row -- the same close-and-create shape
 * used for teaching assignments and English placements.
 *
 * IDENTITY is `code` + `version`, not `name`. Names are presentation and may
 * be corrected; identity may not.
 *
 * `english_programme_id` binds a curriculum to one of Rahai's English
 * programmes, or is NULL for the national phase-based curriculum. It exists
 * now so that Curriculum Scope (the next step) can enforce the rule that a
 * Primary English curriculum may never scope to a Junior High level. No
 * curriculum-type enum: the only distinction the school actually has today is
 * "bound to an English programme or not", and inventing framework types ahead
 * of a real requirement is how speculative schema gets built.
 *
 * There is deliberately no academic_year_id. A curriculum version may span
 * several academic years, and its effective dates are its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curricula', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->string('version');
            $table->text('description')->nullable();
            // Free-text provenance: a regulation reference, an internal
            // approval note. Not a URL, and no document storage.
            $table->string('source_reference')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->foreignId('english_programme_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['code', 'version']);
        });

        // At most one ACTIVE version per curriculum family. NATIONAL/2025 and
        // NATIONAL/2026 may both exist, but only one may be in force; NATIONAL
        // and PRI-ENG are different families and may both be active.
        // Partial unique indexes behave identically on PostgreSQL and SQLite,
        // so this is a real constraint rather than a service-level hope.
        DB::statement(
            "CREATE UNIQUE INDEX curricula_one_active_version_per_code
             ON curricula (code)
             WHERE status = 'active'"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX curricula_one_active_version_per_code');
        Schema::dropIfExists('curricula');
    }
};
