<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a curriculum version says something about.
 *
 * A scope belongs to one curriculum version and has exactly ONE basis:
 *   - a Learning Phase, for the national phase-based curriculum, or
 *   - an English Level, for a Rahai English curriculum.
 *
 * CROSS-PROGRAMME INTEGRITY. The design commitment from the architecture work
 * was that a Primary English curriculum must never scope to Junior High Level
 * B. That is a comparison between two different parent tables
 * (curricula.english_programme_id vs english_levels.english_programme_id),
 * which plain SQL cannot express without either a trigger (PostgreSQL-only,
 * ruled out) or carrying the discriminator on the child row.
 *
 * So english_programme_id IS carried here -- the one piece of deliberate
 * duplication in this schema -- and two composite foreign keys make the
 * database enforce the agreement:
 *
 *   (curriculum_id, english_programme_id)   -> curricula (id, english_programme_id)
 *   (english_level_id, english_programme_id)-> english_levels (id, english_programme_id)
 *
 * Together with the CHECK constraints this makes the database refuse:
 *   - a level scope whose programme disagrees with the curriculum's
 *   - a level scope on a national curriculum (which has no programme)
 *   - a level from a different programme than the one recorded
 *   - both bases, or neither
 *
 * NOT closable this way, and therefore enforced in CurriculumScopeService:
 * an English-bound curriculum taking a LEARNING PHASE scope. A phase scope
 * legitimately carries a NULL discriminator, and SQL's MATCH SIMPLE semantics
 * skip a composite foreign key whenever any of its columns is NULL, so no
 * constraint can see the curriculum's programme in that direction. Closing it
 * would need a sentinel "no programme" row, which would mean inventing data.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Composite foreign keys need their parent columns to carry a unique
        // index. Both are trivially unique already (id is a primary key); the
        // indexes exist so the database will accept the references.
        DB::statement('CREATE UNIQUE INDEX curricula_id_programme_unique ON curricula (id, english_programme_id)');
        DB::statement('CREATE UNIQUE INDEX english_levels_id_programme_unique ON english_levels (id, english_programme_id)');

        Schema::getConnection()->getDriverName() === 'sqlite'
            ? $this->createSqlite()
            : $this->createPostgres();

        // One scope per basis per curriculum version. NULLs compare as
        // distinct on both engines, so a curriculum may hold many level scopes
        // (each with a NULL phase) without colliding -- and Phase C may still
        // appear in a different curriculum version, which is what keeps each
        // version's history its own.
        DB::statement('CREATE UNIQUE INDEX curriculum_scopes_phase_unique ON curriculum_scopes (curriculum_id, learning_phase_id)');
        DB::statement('CREATE UNIQUE INDEX curriculum_scopes_level_unique ON curriculum_scopes (curriculum_id, english_level_id)');
    }

    private function createPostgres(): void
    {
        DB::statement(
            'CREATE TABLE curriculum_scopes (
                id bigserial PRIMARY KEY,
                curriculum_id bigint NOT NULL,
                english_programme_id bigint NULL,
                learning_phase_id bigint NULL,
                english_level_id bigint NULL,
                created_at timestamp(0) NULL,
                updated_at timestamp(0) NULL,

                CONSTRAINT curriculum_scopes_one_basis
                    CHECK ((learning_phase_id IS NULL) <> (english_level_id IS NULL)),
                CONSTRAINT curriculum_scopes_programme_tracks_basis
                    CHECK ((english_level_id IS NULL) = (english_programme_id IS NULL)),

                CONSTRAINT curriculum_scopes_curriculum_id_foreign
                    FOREIGN KEY (curriculum_id) REFERENCES curricula (id) ON DELETE RESTRICT,
                CONSTRAINT curriculum_scopes_curriculum_programme_foreign
                    FOREIGN KEY (curriculum_id, english_programme_id)
                    REFERENCES curricula (id, english_programme_id) ON DELETE RESTRICT,
                CONSTRAINT curriculum_scopes_learning_phase_id_foreign
                    FOREIGN KEY (learning_phase_id) REFERENCES learning_phases (id) ON DELETE RESTRICT,
                CONSTRAINT curriculum_scopes_english_level_id_foreign
                    FOREIGN KEY (english_level_id) REFERENCES english_levels (id) ON DELETE RESTRICT,
                CONSTRAINT curriculum_scopes_level_programme_foreign
                    FOREIGN KEY (english_level_id, english_programme_id)
                    REFERENCES english_levels (id, english_programme_id) ON DELETE RESTRICT
            )'
        );
    }

    private function createSqlite(): void
    {
        DB::statement(
            'CREATE TABLE curriculum_scopes (
                id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
                curriculum_id integer NOT NULL,
                english_programme_id integer NULL,
                learning_phase_id integer NULL,
                english_level_id integer NULL,
                created_at datetime NULL,
                updated_at datetime NULL,

                CHECK ((learning_phase_id IS NULL) <> (english_level_id IS NULL)),
                CHECK ((english_level_id IS NULL) = (english_programme_id IS NULL)),

                FOREIGN KEY (curriculum_id) REFERENCES curricula (id) ON DELETE RESTRICT,
                FOREIGN KEY (curriculum_id, english_programme_id)
                    REFERENCES curricula (id, english_programme_id) ON DELETE RESTRICT,
                FOREIGN KEY (learning_phase_id) REFERENCES learning_phases (id) ON DELETE RESTRICT,
                FOREIGN KEY (english_level_id) REFERENCES english_levels (id) ON DELETE RESTRICT,
                FOREIGN KEY (english_level_id, english_programme_id)
                    REFERENCES english_levels (id, english_programme_id) ON DELETE RESTRICT
            )'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_scopes');
        DB::statement('DROP INDEX english_levels_id_programme_unique');
        DB::statement('DROP INDEX curricula_id_programme_unique');
    }
};
