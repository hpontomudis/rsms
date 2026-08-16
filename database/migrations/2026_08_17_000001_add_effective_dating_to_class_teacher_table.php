<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Foundation F2 -- makes ClassTeacher effective-dated, the same shape as
 * class_subject (Step 0) and teaching_group_student.
 *
 * Before this migration, `class_teacher` had no dates at all: a handover was
 * `assignTeacher()` creating a new row with no obligation to also call
 * `removeTeacher()` on the outgoing one, so two Staff could both read as
 * "current" homeroom teacher for one Class indefinitely (regression-pinned
 * by CommunicationAudienceTest, since inverted). Preflight against the dev
 * database (2026-08-17) found exactly one row, homeroom, no duplicates, no
 * orphaned references -- no manual conflict resolution was required before
 * this migration.
 *
 * Backfill: started_on = the owning Class's AcademicYear.start_date. This is
 * not invented history -- academic_year_id is NOT NULL on `classes` and
 * `start_date` is NOT NULL on `academic_years`, so the value is a real,
 * deterministically-available fact about every existing row, matching the
 * same reasoning the F1/Step-0 backfills already used. Every existing row is
 * currently represented as an active assignment, so ended_on stays NULL.
 *
 * Two partial unique indexes, both proven portable (identical syntax on
 * PostgreSQL and SQLite) by class_subject_active_unique and
 * teaching_group_student_open_unique:
 *  - class_teacher_open_unique: at most one OPEN row per (class, staff,
 *    role) -- the same general hygiene rule class_subject already has,
 *    replacing the old flat unique(class_id, staff_id, role), which would
 *    have blocked legitimate rejoin history (Staff A homeroom, then B, then
 *    A again).
 *  - class_teacher_homeroom_open_unique: at most one OPEN homeroom row per
 *    class, REGARDLESS of staff -- the actual singleton-current-authority
 *    invariant this migration exists to add. Deliberately not applied to
 *    'assistant' (plural by design) or 'subject_teacher' (deprecated, see
 *    ClassTeacherService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_teacher', function (Blueprint $table) {
            // CURRENT_DATE default (portable across pgsql/sqlite) lets the
            // column be NOT NULL immediately without a ->change() call --
            // same technique as the class_subject effective-dating migration.
            $table->date('started_on')->default(DB::raw('CURRENT_DATE'))->after('role');
            $table->date('ended_on')->nullable()->after('started_on');
        });

        foreach (
            DB::table('class_teacher')
                ->join('classes', 'classes.id', '=', 'class_teacher.class_id')
                ->join('academic_years', 'academic_years.id', '=', 'classes.academic_year_id')
                ->select('class_teacher.id', 'academic_years.start_date')
                ->get() as $row
        ) {
            DB::table('class_teacher')->where('id', $row->id)->update(['started_on' => $row->start_date]);
        }

        Schema::table('class_teacher', function (Blueprint $table) {
            $table->dropUnique(['class_id', 'staff_id', 'role']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX class_teacher_open_unique ON class_teacher (class_id, staff_id, role) WHERE ended_on IS NULL'
        );
        DB::statement(
            "CREATE UNIQUE INDEX class_teacher_homeroom_open_unique ON class_teacher (class_id) WHERE role = 'homeroom' AND ended_on IS NULL"
        );

        // Date-order guard (ended_on IS NULL OR ended_on >= started_on).
        // PostgreSQL supports ADD CONSTRAINT ... CHECK on an existing table
        // directly. SQLite's ALTER TABLE has no equivalent (it supports only
        // rename/add-column/drop-column), so on SQLite this invariant is
        // guarded by ClassTeacherService and pinned by tests instead --
        // documented asymmetry, not a silent gap.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE class_teacher ADD CONSTRAINT class_teacher_date_range_check CHECK (ended_on IS NULL OR ended_on >= started_on)'
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE class_teacher DROP CONSTRAINT class_teacher_date_range_check');
        }

        DB::statement('DROP INDEX class_teacher_homeroom_open_unique');
        DB::statement('DROP INDEX class_teacher_open_unique');

        // NOTE: this will fail if any (class, staff, role) has more than one
        // row (i.e. a handover or reassignment has happened). That is
        // intentional -- silently discarding assignment history to satisfy a
        // rollback would be worse, matching the class_subject migration.
        Schema::table('class_teacher', function (Blueprint $table) {
            $table->unique(['class_id', 'staff_id', 'role']);
            $table->dropColumn(['started_on', 'ended_on']);
        });
    }
};
