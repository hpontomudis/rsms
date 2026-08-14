<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical, school-authored content. Nothing about AUDIENCE, RECIPIENT,
 * NOTIFICATION or EXTERNAL DELIVERY lives here -- those are separate tables on
 * purpose, per the V8A architecture review. No channel column, no email/phone
 * fields, no template IDs: this table is what was said, not who it reached or
 * how.
 *
 * `created_by_user_id` is the real audit actor and is never shown to
 * recipients as-is; `display_sender` is the recipient-facing label ("Rahai
 * School Administration"), editable while draft and frozen at publish.
 *
 * The status/published_at relationship is enforced by CHECK on both drivers,
 * the same discipline V7A used for performance_evaluations: draft carries no
 * published_at; published and archived both do (archiving never clears it --
 * archive is a presentation state, not an unpublish).
 *
 * PUBLISHED IS IMMUTABLE, with no correction/replacement path in V8A -- same
 * rule, same reasoning, as Performance Evaluation. A mistake in a published
 * Communication is fixed by publishing a new one, never by editing this row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::getConnection()->getDriverName() === 'sqlite'
            ? $this->createSqlite()
            : $this->createPostgres();
    }

    private function createPostgres(): void
    {
        DB::statement(
            "CREATE TABLE communications (
                id bigserial PRIMARY KEY,
                created_by_user_id bigint NOT NULL,
                display_sender varchar(255) NOT NULL,
                title varchar(255) NOT NULL,
                body text NOT NULL,
                priority varchar(255) NOT NULL DEFAULT 'normal',
                status varchar(255) NOT NULL DEFAULT 'draft',
                published_at timestamp(0) NULL,
                expires_at timestamp(0) NULL,
                audience_summary_snapshot varchar(255) NULL,

                created_at timestamp(0) NULL,
                updated_at timestamp(0) NULL,

                CONSTRAINT communications_priority_check
                    CHECK (priority IN ('normal', 'important', 'urgent')),
                CONSTRAINT communications_status_check
                    CHECK (status IN ('draft', 'published', 'archived')),
                CONSTRAINT communications_published_at_consistency
                    CHECK (
                        (status = 'draft' AND published_at IS NULL)
                        OR
                        (status IN ('published', 'archived') AND published_at IS NOT NULL)
                    ),

                CONSTRAINT communications_created_by_user_id_foreign
                    FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE RESTRICT
            )"
        );
    }

    private function createSqlite(): void
    {
        DB::statement(
            "CREATE TABLE communications (
                id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
                created_by_user_id integer NOT NULL,
                display_sender varchar NOT NULL,
                title varchar NOT NULL,
                body text NOT NULL,
                priority varchar NOT NULL DEFAULT 'normal',
                status varchar NOT NULL DEFAULT 'draft',
                published_at datetime NULL,
                expires_at datetime NULL,
                audience_summary_snapshot varchar NULL,

                created_at datetime NULL,
                updated_at datetime NULL,

                CHECK (priority IN ('normal', 'important', 'urgent')),
                CHECK (status IN ('draft', 'published', 'archived')),
                CHECK (
                    (status = 'draft' AND published_at IS NULL)
                    OR
                    (status IN ('published', 'archived') AND published_at IS NOT NULL)
                ),

                FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE RESTRICT
            )"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('communications');
    }
};
