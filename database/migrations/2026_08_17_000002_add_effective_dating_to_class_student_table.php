<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Foundation F3 -- makes ClassStudent effective-dated, the last of the
 * three-part Foundation Integrity Pass (F1: AcademicYear, F2: ClassTeacher).
 *
 * Preflight against the dev database (2026-08-17) found exactly 2 rows,
 * both `status = active`, no student holding more than one active row, no
 * orphaned Student/Class references, and ZERO `transferred_out`/`withdrawn`
 * legacy rows -- so no legacy-closure-provenance question exists to solve,
 * and no manual conflict resolution was required.
 *
 * BOUNDARY CONVENTION -- deliberately HALF-OPEN, unlike class_subject and
 * teaching_group_student's CLOSED-interval convention:
 *
 *   [enrolled_at, ended_on)  -- enrolled_at inclusive, ended_on EXCLUSIVE.
 *
 * A transfer closes the outgoing row and opens the incoming row on the SAME
 * calendar date (the transfer's effective date) -- matching how a school
 * admin actually describes a transfer ("Aug 15" is the one date everyone
 * writes down, not two different dates for the two sides). Under a closed
 * interval (`ended_on >= $on`, teaching_group_student's convention) that
 * would make the student appear on BOTH classes' rosters on Aug 15. Under
 * this half-open convention (`ended_on > $on` required to still count),
 * the outgoing row's Aug-15 is excluded and only the incoming row counts --
 * no double-membership, no off-by-one date arithmetic required of callers
 * or the UI. This is a deliberate, documented divergence from
 * teaching_group_student, not an oversight; see ClassStudentService.
 *
 * Status/ended_on stay a matched pair: `active` implies `ended_on IS NULL`;
 * `transferred_out`/`withdrawn` imply `ended_on IS NOT NULL`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_student', function (Blueprint $table) {
            $table->date('ended_on')->nullable()->after('status');
            $table->index(['student_id', 'ended_on']);
            $table->index(['class_id', 'ended_on']);
        });

        // No backfill loop: preflight confirmed every existing row is
        // `active` and ended_on's NULL default already matches that.

        Schema::table('class_student', function (Blueprint $table) {
            $table->dropUnique(['class_id', 'student_id']);
        });

        // The one invariant this migration exists to add: at most one OPEN
        // administrative class enrollment per Student, system-wide -- NOT
        // scoped by class_id, matching the architecture review's explicit
        // instruction. Same partial-unique-index technique as
        // class_subject_active_unique / teaching_group_student_open_unique /
        // class_teacher_homeroom_open_unique, all portable across
        // PostgreSQL and SQLite.
        DB::statement(
            'CREATE UNIQUE INDEX class_student_current_enrollment_unique ON class_student (student_id) WHERE ended_on IS NULL'
        );

        // Date-order and status/ended_on consistency guards. PostgreSQL
        // supports ADD CONSTRAINT ... CHECK on an existing table directly;
        // SQLite's ALTER TABLE has no equivalent, so on SQLite both
        // invariants are guarded by ClassStudentService and pinned by tests
        // instead -- a documented asymmetry, the same shape already used
        // for class_teacher's date-order CHECK (Foundation F2).
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE class_student ADD CONSTRAINT class_student_date_range_check CHECK (ended_on IS NULL OR ended_on > enrolled_at)'
            );
            DB::statement(
                "ALTER TABLE class_student ADD CONSTRAINT class_student_status_ended_on_check CHECK ".
                "((status = 'active' AND ended_on IS NULL) OR (status IN ('transferred_out', 'withdrawn') AND ended_on IS NOT NULL))"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE class_student DROP CONSTRAINT class_student_status_ended_on_check');
            DB::statement('ALTER TABLE class_student DROP CONSTRAINT class_student_date_range_check');
        }

        DB::statement('DROP INDEX class_student_current_enrollment_unique');

        // NOTE: this will fail if any student has more than one row for the
        // same class (i.e. a transfer back to a previous class has
        // happened) or more than one row overall predating a unique
        // (class_id, student_id) re-add across different classes with
        // history. That is intentional -- silently discarding enrollment
        // history to satisfy a rollback would be worse, matching the
        // class_subject/class_teacher migrations.
        Schema::table('class_student', function (Blueprint $table) {
            $table->unique(['class_id', 'student_id']);
        });

        Schema::table('class_student', function (Blueprint $table) {
            $table->dropIndex(['class_id', 'ended_on']);
            $table->dropIndex(['student_id', 'ended_on']);
            $table->dropColumn('ended_on');
        });
    }
};
