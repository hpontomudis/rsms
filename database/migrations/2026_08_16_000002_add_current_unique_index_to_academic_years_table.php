<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Foundation F1 -- at most one Academic Year may hold is_current = true.
 *
 * Before this migration, `AcademicYear::current()` was `where('is_current',
 * true)->first()` with no database guarantee behind it -- the seeder's
 * wipe-then-set was the only thing keeping the row unique, and nothing
 * stopped a raw insert/update producing two. Preflight against the dev
 * database (Foundation Integrity Pass F1, 2026-08-16) found exactly one
 * AcademicYear row, is_current = true -- no manual conflict resolution was
 * required before adding this constraint.
 *
 * A partial unique index (not a Postgres-only exclusion constraint) covers
 * this: only rows where is_current = true are indexed, and a unique index
 * on a single always-true value permits at most one such row. Identical
 * syntax on PostgreSQL and SQLite, matching the technique already proven by
 * class_subject_active_unique and teaching_group_student_open_unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX academic_years_current_unique ON academic_years (is_current) WHERE is_current = true'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX academic_years_current_unique');
    }
};
