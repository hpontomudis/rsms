<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Ajar / Teaching Module: one teacher's instructional design -- HOW a set
 * of objectives will be taught.
 *
 * ANCHORED TO class_subject, DELIBERATELY UNLIKE Prota/Prosem. A module is
 * teacher-level craft, so authorship should follow the person: Sarah's modules
 * stay Sarah's when Eka takes over, readable by Eka but never editable by her.
 * The annual and semester plans are the opposite -- they belong to the roster
 * and survive the handover shared. Both behaviours are correct for what they
 * describe.
 *
 * The roster columns and subject are MIRRORED from the assignment so composite
 * keys can police them, and so a journal can later prove it is citing a module
 * for its own roster and subject. No staff_id: teacher identity is
 * class_subject.staff_id and nowhere else.
 *
 * curriculum_scope_id is an EXPLICIT fact, never guessed. A class-backed
 * assignment resolves candidate scopes through grade -> learning phase, a
 * group-backed one through English level; where more than one candidate exists
 * the user chooses, because silently taking the first would quietly bind a
 * module to the wrong curriculum version.
 *
 * Carries no period, week, date or JP column -- those are Prosem's, and a
 * second copy could disagree with the first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::getConnection()->getDriverName() === 'sqlite'
            ? $this->createSqlite()
            : $this->createPostgres();

        // Objective links prove scope + subject against this.
        DB::statement('CREATE UNIQUE INDEX teaching_modules_scope_anchor_unique ON teaching_modules (id, curriculum_scope_id, subject_id)');

        // Journals prove roster + subject against these (split for the XOR).
        DB::statement('CREATE UNIQUE INDEX teaching_modules_class_anchor_unique ON teaching_modules (id, class_id, subject_id)');
        DB::statement('CREATE UNIQUE INDEX teaching_modules_group_anchor_unique ON teaching_modules (id, teaching_group_id, subject_id)');

        DB::statement('CREATE INDEX teaching_modules_assignment_index ON teaching_modules (class_subject_id, status)');
    }

    private function createPostgres(): void
    {
        DB::statement(
            "CREATE TABLE teaching_modules (
                id bigserial PRIMARY KEY,
                class_subject_id bigint NOT NULL,
                class_id bigint NULL,
                teaching_group_id bigint NULL,
                subject_id bigint NOT NULL,
                curriculum_scope_id bigint NOT NULL,
                title varchar(255) NOT NULL,
                topic varchar(255) NULL,
                planned_activity text NOT NULL,
                teaching_strategy text NULL,
                resources text NULL,
                differentiation text NULL,
                planned_assessment text NULL,
                teacher_notes text NULL,
                status varchar(255) NOT NULL DEFAULT 'draft',
                created_at timestamp(0) NULL,
                updated_at timestamp(0) NULL,

                CONSTRAINT teaching_modules_one_roster_source
                    CHECK ((class_id IS NULL) <> (teaching_group_id IS NULL)),
                CONSTRAINT teaching_modules_status_check
                    CHECK (status IN ('draft', 'ready', 'archived')),

                CONSTRAINT teaching_modules_class_subject_id_foreign
                    FOREIGN KEY (class_subject_id) REFERENCES class_subject (id) ON DELETE RESTRICT,
                CONSTRAINT teaching_modules_subject_id_foreign
                    FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE RESTRICT,
                CONSTRAINT teaching_modules_curriculum_scope_id_foreign
                    FOREIGN KEY (curriculum_scope_id) REFERENCES curriculum_scopes (id) ON DELETE RESTRICT,

                CONSTRAINT teaching_modules_assignment_class_foreign
                    FOREIGN KEY (class_subject_id, class_id, subject_id)
                    REFERENCES class_subject (id, class_id, subject_id) ON DELETE RESTRICT,
                CONSTRAINT teaching_modules_assignment_group_foreign
                    FOREIGN KEY (class_subject_id, teaching_group_id, subject_id)
                    REFERENCES class_subject (id, teaching_group_id, subject_id) ON DELETE RESTRICT
            )"
        );
    }

    private function createSqlite(): void
    {
        DB::statement(
            "CREATE TABLE teaching_modules (
                id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
                class_subject_id integer NOT NULL,
                class_id integer NULL,
                teaching_group_id integer NULL,
                subject_id integer NOT NULL,
                curriculum_scope_id integer NOT NULL,
                title varchar NOT NULL,
                topic varchar NULL,
                planned_activity text NOT NULL,
                teaching_strategy text NULL,
                resources text NULL,
                differentiation text NULL,
                planned_assessment text NULL,
                teacher_notes text NULL,
                status varchar NOT NULL DEFAULT 'draft',
                created_at datetime NULL,
                updated_at datetime NULL,

                CHECK ((class_id IS NULL) <> (teaching_group_id IS NULL)),
                CHECK (status IN ('draft', 'ready', 'archived')),

                FOREIGN KEY (class_subject_id) REFERENCES class_subject (id) ON DELETE RESTRICT,
                FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE RESTRICT,
                FOREIGN KEY (curriculum_scope_id) REFERENCES curriculum_scopes (id) ON DELETE RESTRICT,
                FOREIGN KEY (class_subject_id, class_id, subject_id)
                    REFERENCES class_subject (id, class_id, subject_id) ON DELETE RESTRICT,
                FOREIGN KEY (class_subject_id, teaching_group_id, subject_id)
                    REFERENCES class_subject (id, teaching_group_id, subject_id) ON DELETE RESTRICT
            )"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_modules');
    }
};
