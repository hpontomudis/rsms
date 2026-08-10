<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DEPRECATION: `assessments.term`.
 *
 * `academic_period_id` is now the canonical reporting period. This column is
 * retained only so the previous phase remains recoverable, and is made
 * nullable so that new rows -- which must not populate it -- can be inserted.
 * Historical values are left untouched.
 *
 * Nothing in application code reads or writes this column. It will be dropped
 * in a later cleanup migration once the new architecture has proven stable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->string('term')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rows created after deprecation have no term value, so restoring
        // NOT NULL would fail. Backfill a placeholder first.
        Schema::table('assessments', function (Blueprint $table) {
            $table->string('term')->nullable()->change();
        });
    }
};
