<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extends class_subject so a teaching assignment can be backed by EITHER an
 * administrative class OR a teaching group -- never both, never neither.
 *
 * The table and model keep their names deliberately: renaming them would
 * churn every reference (assessments.class_subject_id most of all) for no
 * behavioural gain. Domain-wise a row is now a Teaching Assignment.
 *
 * PORTABILITY: this migration is deliberately written twice, because neither
 * half of it is portable.
 *   - Dropping NOT NULL: PostgreSQL has ALTER COLUMN ... DROP NOT NULL;
 *     SQLite has no equivalent.
 *   - Adding a CHECK constraint: PostgreSQL has ADD CONSTRAINT; SQLite's
 *     ALTER TABLE cannot add one at all.
 * So PostgreSQL alters in place, and SQLite does the create-copy-drop-rename
 * dance that ALTER TABLE cannot express. Both end at the same schema, and
 * both are verified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::getConnection()->getDriverName() === 'sqlite'
            ? $this->upSqlite()
            : $this->upPostgres();

        // One ACTIVE assignment per group+subject, mirroring the class rule
        // from Step 0. Closed historical rows stay unlimited, so a handover
        // (Sarah closes, Eka opens) is representable. Partial unique index
        // syntax is identical on both databases.
        DB::statement(
            'CREATE UNIQUE INDEX class_subject_group_active_unique
             ON class_subject (teaching_group_id, subject_id)
             WHERE ended_on IS NULL'
        );
    }

    private function upPostgres(): void
    {
        DB::statement('ALTER TABLE class_subject ALTER COLUMN class_id DROP NOT NULL');

        DB::statement(
            'ALTER TABLE class_subject
             ADD COLUMN teaching_group_id BIGINT NULL
             REFERENCES teaching_groups (id) ON DELETE RESTRICT'
        );

        DB::statement('CREATE INDEX class_subject_teaching_group_id_index ON class_subject (teaching_group_id)');

        // Exactly one roster source. Written as XOR over the two NULL tests so
        // both "neither" and "both" are rejected by the database itself.
        DB::statement(
            'ALTER TABLE class_subject
             ADD CONSTRAINT class_subject_one_roster_source
             CHECK ((class_id IS NULL) <> (teaching_group_id IS NULL))'
        );
    }

    /**
     * SQLite: rebuild the table. Existing rows are copied across verbatim with
     * teaching_group_id NULL, so every class-backed assignment survives with
     * the same id -- which matters because assessments.class_subject_id points
     * at it.
     */
    private function upSqlite(): void
    {
        Schema::disableForeignKeyConstraints();

        DB::statement(
            'CREATE TABLE class_subject_rebuild (
                id integer primary key autoincrement not null,
                class_id integer null,
                teaching_group_id integer null,
                subject_id integer not null,
                staff_id integer not null,
                started_on date not null default CURRENT_DATE,
                ended_on date null,
                created_at datetime null,
                updated_at datetime null,
                foreign key (class_id) references classes (id) on delete cascade,
                foreign key (teaching_group_id) references teaching_groups (id) on delete restrict,
                foreign key (subject_id) references subjects (id) on delete restrict,
                foreign key (staff_id) references staff (id) on delete restrict,
                check ((class_id is null) <> (teaching_group_id is null))
            )'
        );

        DB::statement(
            'INSERT INTO class_subject_rebuild
                (id, class_id, teaching_group_id, subject_id, staff_id, started_on, ended_on, created_at, updated_at)
             SELECT id, class_id, NULL, subject_id, staff_id, started_on, ended_on, created_at, updated_at
             FROM class_subject'
        );

        DB::statement('DROP TABLE class_subject');
        DB::statement('ALTER TABLE class_subject_rebuild RENAME TO class_subject');

        // Dropped with the old table; recreate exactly as Step 0 defined it.
        DB::statement(
            'CREATE UNIQUE INDEX class_subject_active_unique
             ON class_subject (class_id, subject_id)
             WHERE ended_on IS NULL'
        );
        DB::statement('CREATE INDEX class_subject_teaching_group_id_index ON class_subject (teaching_group_id)');

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        DB::statement('DROP INDEX class_subject_group_active_unique');

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // Rolling back would mean deciding what to do with group-backed
            // assignments, which have no representation in the old schema.
            // Refusing is safer than inventing an answer.
            throw new RuntimeException('Rolling this migration back on SQLite is not supported; rebuild the test database instead.');
        }

        DB::statement('ALTER TABLE class_subject DROP CONSTRAINT class_subject_one_roster_source');
        DB::statement('DROP INDEX class_subject_teaching_group_id_index');
        DB::statement('ALTER TABLE class_subject DROP COLUMN teaching_group_id');
        DB::statement('ALTER TABLE class_subject ALTER COLUMN class_id SET NOT NULL');
    }
};
