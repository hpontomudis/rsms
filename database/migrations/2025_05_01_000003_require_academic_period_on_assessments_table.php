<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Validation gate, then makes the academic period mandatory.
 *
 * Runs as its own migration so the conservative backfill in the previous step
 * can be reviewed -- and any ambiguous rows resolved by an explicit, deliberate
 * decision -- before the column becomes NOT NULL. Failing loudly here is the
 * intended behaviour: it is far better than silently guessing which period a
 * historical assessment belonged to.
 */
return new class extends Migration
{
    public function up(): void
    {
        $unmapped = DB::table('assessments')->whereNull('academic_period_id')->count();

        if ($unmapped > 0) {
            $sample = DB::table('assessments')
                ->whereNull('academic_period_id')
                ->limit(5)
                ->pluck('term')
                ->unique()
                ->implode(', ');

            throw new RuntimeException(
                "Cannot make assessments.academic_period_id NOT NULL: {$unmapped} assessment(s) have no academic period. "
                ."Unmapped term value(s): [{$sample}]. "
                .'Resolve each one explicitly (map it to the correct academic_periods row) and re-run this migration. '
                .'Do NOT add an automatic Term->Semester mapping -- three terms do not divide cleanly into two semesters.'
            );
        }

        Schema::table('assessments', function (Blueprint $table) {
            $table->foreignId('academic_period_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->foreignId('academic_period_id')->nullable()->change();
        });
    }
};
