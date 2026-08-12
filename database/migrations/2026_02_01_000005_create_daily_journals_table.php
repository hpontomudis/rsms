<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jurnal Harian Guru / Daily Teaching Journal: what actually happened in one
 * teaching session on one date.
 *
 * Anchored to class_subject like the module, and immutably so -- moving a
 * journal between assignments through an edit would rewrite whose teaching
 * history it was.
 *
 * TWO staff facts, deliberately distinct:
 *   class_subject.staff_id   -- who was RESPONSIBLE for the assignment
 *   conducted_by_staff_id    -- who actually stood in the room that day
 * A substitute lesson names the substitute here and changes nothing about the
 * assignment. conducted_by is NOT required to be currently active staff: a 2025
 * journal must still be able to name someone who left in 2026, because current
 * status cannot prove historical status.
 *
 * academic_period_id is STORED rather than derived from journal_date. Academic
 * periods carry no guarantee of being non-overlapping or complete, and this
 * project refuses to guess: a derived period would need zero-match and
 * multi-match handling on every read. The mirrored academic_year_id lets a
 * composite key prove the period belongs to the assignment's year.
 *
 * NO attendance_id. Daily Journal is not attendance; administrative attendance
 * stays exactly what it is, and no session-attendance engine is implied.
 * No planned JP, no week_label, no scores -- planned facts live upstream and
 * marks live in assessment_results.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::getConnection()->getDriverName() === 'sqlite'
            ? $this->createSqlite()
            : $this->createPostgres();

        DB::statement('CREATE UNIQUE INDEX daily_journals_scope_anchor_unique ON daily_journals (id, curriculum_scope_id, subject_id)');
        DB::statement('CREATE UNIQUE INDEX daily_journals_assignment_anchor_unique ON daily_journals (id, class_subject_id)');

        DB::statement('CREATE INDEX daily_journals_assignment_date_index ON daily_journals (class_subject_id, journal_date)');
    }

    private function createPostgres(): void
    {
        DB::statement(
            "CREATE TABLE daily_journals (
                id bigserial PRIMARY KEY,
                class_subject_id bigint NOT NULL,
                class_id bigint NULL,
                teaching_group_id bigint NULL,
                subject_id bigint NOT NULL,
                curriculum_scope_id bigint NOT NULL,
                academic_year_id bigint NOT NULL,
                academic_period_id bigint NOT NULL,
                journal_date date NOT NULL,
                conducted_by_staff_id bigint NOT NULL,
                teaching_module_id bigint NULL,
                semester_programme_item_id bigint NULL,
                meeting_number smallint NULL,
                topic varchar(255) NOT NULL,
                actual_activity text NOT NULL,
                reflection text NULL,
                follow_up text NULL,
                actual_lesson_periods smallint NULL,
                status varchar(255) NOT NULL DEFAULT 'draft',
                created_at timestamp(0) NULL,
                updated_at timestamp(0) NULL,

                CONSTRAINT daily_journals_one_roster_source
                    CHECK ((class_id IS NULL) <> (teaching_group_id IS NULL)),
                CONSTRAINT daily_journals_status_check
                    CHECK (status IN ('draft', 'finalized')),
                CONSTRAINT daily_journals_positive_periods
                    CHECK (actual_lesson_periods IS NULL OR actual_lesson_periods > 0),
                CONSTRAINT daily_journals_positive_meeting
                    CHECK (meeting_number IS NULL OR meeting_number > 0),

                CONSTRAINT daily_journals_class_subject_id_foreign
                    FOREIGN KEY (class_subject_id) REFERENCES class_subject (id) ON DELETE RESTRICT,
                CONSTRAINT daily_journals_subject_id_foreign
                    FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE RESTRICT,
                CONSTRAINT daily_journals_curriculum_scope_id_foreign
                    FOREIGN KEY (curriculum_scope_id) REFERENCES curriculum_scopes (id) ON DELETE RESTRICT,
                CONSTRAINT daily_journals_conducted_by_staff_id_foreign
                    FOREIGN KEY (conducted_by_staff_id) REFERENCES staff (id) ON DELETE RESTRICT,
                CONSTRAINT daily_journals_semester_programme_item_id_foreign
                    FOREIGN KEY (semester_programme_item_id) REFERENCES semester_programme_items (id) ON DELETE RESTRICT,

                CONSTRAINT daily_journals_assignment_class_foreign
                    FOREIGN KEY (class_subject_id, class_id, subject_id)
                    REFERENCES class_subject (id, class_id, subject_id) ON DELETE RESTRICT,
                CONSTRAINT daily_journals_assignment_group_foreign
                    FOREIGN KEY (class_subject_id, teaching_group_id, subject_id)
                    REFERENCES class_subject (id, teaching_group_id, subject_id) ON DELETE RESTRICT,

                CONSTRAINT daily_journals_period_year_foreign
                    FOREIGN KEY (academic_period_id, academic_year_id)
                    REFERENCES academic_periods (id, academic_year_id) ON DELETE RESTRICT,

                CONSTRAINT daily_journals_module_scope_foreign
                    FOREIGN KEY (teaching_module_id, curriculum_scope_id, subject_id)
                    REFERENCES teaching_modules (id, curriculum_scope_id, subject_id) ON DELETE RESTRICT,
                CONSTRAINT daily_journals_module_class_foreign
                    FOREIGN KEY (teaching_module_id, class_id, subject_id)
                    REFERENCES teaching_modules (id, class_id, subject_id) ON DELETE RESTRICT,
                CONSTRAINT daily_journals_module_group_foreign
                    FOREIGN KEY (teaching_module_id, teaching_group_id, subject_id)
                    REFERENCES teaching_modules (id, teaching_group_id, subject_id) ON DELETE RESTRICT
            )"
        );
    }

    private function createSqlite(): void
    {
        DB::statement(
            "CREATE TABLE daily_journals (
                id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
                class_subject_id integer NOT NULL,
                class_id integer NULL,
                teaching_group_id integer NULL,
                subject_id integer NOT NULL,
                curriculum_scope_id integer NOT NULL,
                academic_year_id integer NOT NULL,
                academic_period_id integer NOT NULL,
                journal_date date NOT NULL,
                conducted_by_staff_id integer NOT NULL,
                teaching_module_id integer NULL,
                semester_programme_item_id integer NULL,
                meeting_number integer NULL,
                topic varchar NOT NULL,
                actual_activity text NOT NULL,
                reflection text NULL,
                follow_up text NULL,
                actual_lesson_periods integer NULL,
                status varchar NOT NULL DEFAULT 'draft',
                created_at datetime NULL,
                updated_at datetime NULL,

                CHECK ((class_id IS NULL) <> (teaching_group_id IS NULL)),
                CHECK (status IN ('draft', 'finalized')),
                CHECK (actual_lesson_periods IS NULL OR actual_lesson_periods > 0),
                CHECK (meeting_number IS NULL OR meeting_number > 0),

                FOREIGN KEY (class_subject_id) REFERENCES class_subject (id) ON DELETE RESTRICT,
                FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE RESTRICT,
                FOREIGN KEY (curriculum_scope_id) REFERENCES curriculum_scopes (id) ON DELETE RESTRICT,
                FOREIGN KEY (conducted_by_staff_id) REFERENCES staff (id) ON DELETE RESTRICT,
                FOREIGN KEY (semester_programme_item_id) REFERENCES semester_programme_items (id) ON DELETE RESTRICT,
                FOREIGN KEY (class_subject_id, class_id, subject_id)
                    REFERENCES class_subject (id, class_id, subject_id) ON DELETE RESTRICT,
                FOREIGN KEY (class_subject_id, teaching_group_id, subject_id)
                    REFERENCES class_subject (id, teaching_group_id, subject_id) ON DELETE RESTRICT,
                FOREIGN KEY (academic_period_id, academic_year_id)
                    REFERENCES academic_periods (id, academic_year_id) ON DELETE RESTRICT,
                FOREIGN KEY (teaching_module_id, curriculum_scope_id, subject_id)
                    REFERENCES teaching_modules (id, curriculum_scope_id, subject_id) ON DELETE RESTRICT,
                FOREIGN KEY (teaching_module_id, class_id, subject_id)
                    REFERENCES teaching_modules (id, class_id, subject_id) ON DELETE RESTRICT,
                FOREIGN KEY (teaching_module_id, teaching_group_id, subject_id)
                    REFERENCES teaching_modules (id, teaching_group_id, subject_id) ON DELETE RESTRICT
            )"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_journals');
    }
};
