<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An officially issued report card: student + academic period, as at the moment
 * it was published.
 *
 * WHY A SNAPSHOT AT ALL. Ten independent things can change underneath a live
 * report card -- a score, a max_score, an assessment's period, a subject's
 * name, a period's name, a student's name, class membership, group membership.
 * A card rendered from current data is a view, not a record. Once a family
 * holds a printed copy, the numbers on it are a fact about that day, and the
 * system has to be able to say what they were.
 *
 * WHAT IS SNAPSHOTTED. Anything whose DISPLAY value must remain as issued.
 * Foreign keys are kept beside the snapshots for traceability -- "which student
 * is this?" -- but nothing rendered comes from them.
 *
 * The publication unit is the PERIOD, not the year: a school issues a rapor at
 * the end of a semester. The year-level report card remains a live overview and
 * is never published.
 *
 * No document number: nothing at Rahai currently requires one, and a numbering
 * subsystem invented in advance would be a format guess.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::getConnection()->getDriverName() === 'sqlite'
            ? $this->createSqlite()
            : $this->createPostgres();

        // ONE issued record per student and period. Drafts and superseded
        // records coexist freely -- preparing a correction while the current
        // issue is still in force is the normal workflow.
        DB::statement("CREATE UNIQUE INDEX academic_records_published_unique ON academic_records (student_id, academic_period_id) WHERE status = 'published'");

        DB::statement('CREATE INDEX academic_records_student_index ON academic_records (student_id, academic_period_id, status)');
    }

    private function createPostgres(): void
    {
        DB::statement(
            "CREATE TABLE academic_records (
                id bigserial PRIMARY KEY,
                student_id bigint NOT NULL,
                academic_year_id bigint NOT NULL,
                academic_period_id bigint NOT NULL,
                class_id bigint NULL,

                student_name_snapshot varchar(255) NOT NULL,
                student_number_snapshot varchar(255) NULL,
                class_name_snapshot varchar(255) NULL,
                grade_name_snapshot varchar(255) NULL,
                period_name_snapshot varchar(255) NOT NULL,
                academic_year_name_snapshot varchar(255) NOT NULL,
                school_name_snapshot varchar(255) NULL,
                school_line2_snapshot varchar(255) NULL,
                school_address_snapshot varchar(255) NULL,
                principal_name_snapshot varchar(255) NULL,
                principal_title_snapshot varchar(255) NULL,
                homeroom_teacher_name_snapshot varchar(255) NULL,

                overall_average smallint NULL,
                homeroom_comment text NULL,
                notes text NULL,
                status varchar(255) NOT NULL DEFAULT 'draft',
                published_at timestamp(0) NULL,
                published_by_user_id bigint NULL,
                supersedes_id bigint NULL,
                created_at timestamp(0) NULL,
                updated_at timestamp(0) NULL,

                CONSTRAINT academic_records_status_check
                    CHECK (status IN ('draft', 'published', 'superseded')),
                CONSTRAINT academic_records_published_has_timestamp
                    CHECK (status = 'draft' OR published_at IS NOT NULL),

                CONSTRAINT academic_records_student_id_foreign
                    FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE RESTRICT,
                CONSTRAINT academic_records_academic_year_id_foreign
                    FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE RESTRICT,
                CONSTRAINT academic_records_class_id_foreign
                    FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE RESTRICT,
                CONSTRAINT academic_records_published_by_user_id_foreign
                    FOREIGN KEY (published_by_user_id) REFERENCES users (id) ON DELETE RESTRICT,
                CONSTRAINT academic_records_supersedes_id_foreign
                    FOREIGN KEY (supersedes_id) REFERENCES academic_records (id) ON DELETE RESTRICT,

                CONSTRAINT academic_records_period_year_foreign
                    FOREIGN KEY (academic_period_id, academic_year_id)
                    REFERENCES academic_periods (id, academic_year_id) ON DELETE RESTRICT
            )"
        );
    }

    private function createSqlite(): void
    {
        DB::statement(
            "CREATE TABLE academic_records (
                id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
                student_id integer NOT NULL,
                academic_year_id integer NOT NULL,
                academic_period_id integer NOT NULL,
                class_id integer NULL,

                student_name_snapshot varchar NOT NULL,
                student_number_snapshot varchar NULL,
                class_name_snapshot varchar NULL,
                grade_name_snapshot varchar NULL,
                period_name_snapshot varchar NOT NULL,
                academic_year_name_snapshot varchar NOT NULL,
                school_name_snapshot varchar NULL,
                school_line2_snapshot varchar NULL,
                school_address_snapshot varchar NULL,
                principal_name_snapshot varchar NULL,
                principal_title_snapshot varchar NULL,
                homeroom_teacher_name_snapshot varchar NULL,

                overall_average integer NULL,
                homeroom_comment text NULL,
                notes text NULL,
                status varchar NOT NULL DEFAULT 'draft',
                published_at datetime NULL,
                published_by_user_id integer NULL,
                supersedes_id integer NULL,
                created_at datetime NULL,
                updated_at datetime NULL,

                CHECK (status IN ('draft', 'published', 'superseded')),
                CHECK (status = 'draft' OR published_at IS NOT NULL),

                FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE RESTRICT,
                FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE RESTRICT,
                FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE RESTRICT,
                FOREIGN KEY (published_by_user_id) REFERENCES users (id) ON DELETE RESTRICT,
                FOREIGN KEY (supersedes_id) REFERENCES academic_records (id) ON DELETE RESTRICT,
                FOREIGN KEY (academic_period_id, academic_year_id)
                    REFERENCES academic_periods (id, academic_year_id) ON DELETE RESTRICT
            )"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_records');
    }
};
