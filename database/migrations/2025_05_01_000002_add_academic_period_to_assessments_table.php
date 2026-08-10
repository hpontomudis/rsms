<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Points assessments at a real academic period.
 *
 * Backfill is deliberately conservative: it maps ONLY where the deprecated
 * free-text `term` matches an existing period name exactly, within the
 * assessment's own academic year. It does not attempt a Term->Semester
 * translation, because no such mapping is universally correct (three terms do
 * not divide into two semesters). Anything it cannot resolve is left NULL for
 * the validation gate in the next migration to surface.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->foreignId('academic_period_id')
                ->nullable()
                ->after('class_subject_id')
                ->constrained('academic_periods')
                ->restrictOnDelete();
        });

        // Exact-name match only, scoped to the assessment's own academic year
        // (reached via class_subject -> classes -> academic_year_id).
        $rows = DB::table('assessments')
            ->join('class_subject', 'assessments.class_subject_id', '=', 'class_subject.id')
            ->join('classes', 'class_subject.class_id', '=', 'classes.id')
            ->select('assessments.id', 'assessments.term', 'classes.academic_year_id')
            ->get();

        foreach ($rows as $row) {
            $periodId = DB::table('academic_periods')
                ->where('academic_year_id', $row->academic_year_id)
                ->where('name', $row->term)
                ->value('id');

            if ($periodId) {
                DB::table('assessments')->where('id', $row->id)->update(['academic_period_id' => $periodId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_period_id');
        });
    }
};
