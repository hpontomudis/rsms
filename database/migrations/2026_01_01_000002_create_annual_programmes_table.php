<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Program Tahunan: the annual operating decision for one roster and subject --
 * which parts of the chosen pathway are covered this year, and in which
 * reporting period.
 *
 * ANCHORED TO THE ROSTER, NOT TO class_subject. This is the load-bearing
 * decision of Phase 5E. Teaching assignments are effective-dated, so a mid-year
 * handover closes one row and opens another; anchoring the annual plan there
 * would split it in half every time a teacher changed and force the successor
 * to rebuild a year that is already half taught. Anchoring to
 * (class XOR teaching group) + subject means the plan survives the handover
 * untouched, while WHO may edit it follows the current assignment.
 *
 * No staff_id: operational responsibility is class_subject's job, and
 * authorship is the audit trail's.
 *
 * academic_year_id and curriculum_scope_id are mirrored rather than derived,
 * because mirroring is what lets composite foreign keys police them. A mirror
 * the database verifies cannot drift; a derived value cannot be constrained.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::getConnection()->getDriverName() === 'sqlite'
            ? $this->createSqlite()
            : $this->createPostgres();

        // Anchors for the item table's composite keys.
        DB::statement('CREATE UNIQUE INDEX annual_programmes_year_anchor_unique ON annual_programmes (id, academic_year_id)');
        DB::statement('CREATE UNIQUE INDEX annual_programmes_pathway_anchor_unique ON annual_programmes (id, learning_pathway_id)');

        // One programme in force per roster + subject. Because the roster
        // determines the year, this is automatically per year. Drafts coexist.
        DB::statement("CREATE UNIQUE INDEX annual_programmes_active_class_unique ON annual_programmes (class_id, subject_id) WHERE status = 'active'");
        DB::statement("CREATE UNIQUE INDEX annual_programmes_active_group_unique ON annual_programmes (teaching_group_id, subject_id) WHERE status = 'active'");
    }

    private function createPostgres(): void
    {
        DB::statement(
            "CREATE TABLE annual_programmes (
                id bigserial PRIMARY KEY,
                class_id bigint NULL,
                teaching_group_id bigint NULL,
                subject_id bigint NOT NULL,
                academic_year_id bigint NOT NULL,
                curriculum_scope_id bigint NOT NULL,
                learning_pathway_id bigint NOT NULL,
                title varchar(255) NULL,
                notes text NULL,
                status varchar(255) NOT NULL DEFAULT 'draft',
                created_at timestamp(0) NULL,
                updated_at timestamp(0) NULL,

                CONSTRAINT annual_programmes_one_roster_source
                    CHECK ((class_id IS NULL) <> (teaching_group_id IS NULL)),
                CONSTRAINT annual_programmes_status_check
                    CHECK (status IN ('draft', 'active', 'archived')),

                CONSTRAINT annual_programmes_subject_id_foreign
                    FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE RESTRICT,
                CONSTRAINT annual_programmes_academic_year_id_foreign
                    FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE RESTRICT,
                CONSTRAINT annual_programmes_curriculum_scope_id_foreign
                    FOREIGN KEY (curriculum_scope_id) REFERENCES curriculum_scopes (id) ON DELETE RESTRICT,
                CONSTRAINT annual_programmes_learning_pathway_id_foreign
                    FOREIGN KEY (learning_pathway_id) REFERENCES learning_pathways (id) ON DELETE RESTRICT,

                CONSTRAINT annual_programmes_class_year_foreign
                    FOREIGN KEY (class_id, academic_year_id) REFERENCES classes (id, academic_year_id) ON DELETE RESTRICT,
                CONSTRAINT annual_programmes_group_year_foreign
                    FOREIGN KEY (teaching_group_id, academic_year_id) REFERENCES teaching_groups (id, academic_year_id) ON DELETE RESTRICT,
                CONSTRAINT annual_programmes_pathway_anchor_foreign
                    FOREIGN KEY (learning_pathway_id, curriculum_scope_id, subject_id)
                    REFERENCES learning_pathways (id, curriculum_scope_id, subject_id) ON DELETE RESTRICT
            )"
        );
    }

    private function createSqlite(): void
    {
        DB::statement(
            "CREATE TABLE annual_programmes (
                id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
                class_id integer NULL,
                teaching_group_id integer NULL,
                subject_id integer NOT NULL,
                academic_year_id integer NOT NULL,
                curriculum_scope_id integer NOT NULL,
                learning_pathway_id integer NOT NULL,
                title varchar NULL,
                notes text NULL,
                status varchar NOT NULL DEFAULT 'draft',
                created_at datetime NULL,
                updated_at datetime NULL,

                CHECK ((class_id IS NULL) <> (teaching_group_id IS NULL)),
                CHECK (status IN ('draft', 'active', 'archived')),

                FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE RESTRICT,
                FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE RESTRICT,
                FOREIGN KEY (curriculum_scope_id) REFERENCES curriculum_scopes (id) ON DELETE RESTRICT,
                FOREIGN KEY (learning_pathway_id) REFERENCES learning_pathways (id) ON DELETE RESTRICT,
                FOREIGN KEY (class_id, academic_year_id) REFERENCES classes (id, academic_year_id) ON DELETE RESTRICT,
                FOREIGN KEY (teaching_group_id, academic_year_id) REFERENCES teaching_groups (id, academic_year_id) ON DELETE RESTRICT,
                FOREIGN KEY (learning_pathway_id, curriculum_scope_id, subject_id)
                    REFERENCES learning_pathways (id, curriculum_scope_id, subject_id) ON DELETE RESTRICT
            )"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('annual_programmes');
    }
};
