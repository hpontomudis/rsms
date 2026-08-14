<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The historical truth of who a published Communication actually reached --
 * written once, atomically, inside CommunicationService::publish(), and never
 * rewritten by a later membership, relationship, category or role change.
 *
 * CANONICAL RECIPIENT IDENTITY vs RESOLVED LOGIN USER are two different
 * questions, kept as two different columns on purpose (V8A review item 12):
 *
 *  - Exactly ONE of staff_id / guardian_id / student_id / direct_user_id is
 *    the entity this Communication is historically addressed to. Enforced by
 *    CHECK on both drivers -- a raw SQL attack setting two, or none, is
 *    refused by the database itself, not merely the service.
 *  - resolved_user_id is the login (if any) through which that entity can
 *    open this Communication in RSMS today. It is resolved via the same
 *    unambiguous-login exact-match rule V7A built (ResolvesUnambiguousUser):
 *    a shared or absent login yields NULL here, and the recipient row still
 *    exists -- unreachable in-app is not the same thing as "not a recipient",
 *    and V8A has no external delivery to fall back on.
 *
 * `recipient_name_snapshot`/`recipient_context_snapshot` preserve what should
 * display for this recipient even after the live name/relationship changes;
 * the FKs remain for traceability but are never the source of display text
 * for a published record.
 *
 * `read_at` means exactly one thing: opened inside RSMS. Never "delivered",
 * never "read on WhatsApp" -- V8A has no channel that could prove either.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::getConnection()->getDriverName() === 'sqlite'
            ? $this->createSqlite()
            : $this->createPostgres();

        DB::statement('CREATE INDEX communication_recipients_resolved_user_index ON communication_recipients (resolved_user_id, read_at)');
    }

    private function createPostgres(): void
    {
        DB::statement(
            "CREATE TABLE communication_recipients (
                id bigserial PRIMARY KEY,
                communication_id bigint NOT NULL,
                staff_id bigint NULL,
                guardian_id bigint NULL,
                student_id bigint NULL,
                direct_user_id bigint NULL,
                resolved_user_id bigint NULL,
                recipient_name_snapshot varchar(255) NOT NULL,
                recipient_context_snapshot varchar(255) NULL,
                read_at timestamp(0) NULL,

                created_at timestamp(0) NULL,
                updated_at timestamp(0) NULL,

                CONSTRAINT communication_recipients_exactly_one_identity
                    CHECK (
                        (CASE WHEN staff_id IS NOT NULL THEN 1 ELSE 0 END) +
                        (CASE WHEN guardian_id IS NOT NULL THEN 1 ELSE 0 END) +
                        (CASE WHEN student_id IS NOT NULL THEN 1 ELSE 0 END) +
                        (CASE WHEN direct_user_id IS NOT NULL THEN 1 ELSE 0 END) = 1
                    ),

                CONSTRAINT communication_recipients_communication_id_foreign
                    FOREIGN KEY (communication_id) REFERENCES communications (id) ON DELETE RESTRICT,
                CONSTRAINT communication_recipients_staff_id_foreign
                    FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE RESTRICT,
                CONSTRAINT communication_recipients_guardian_id_foreign
                    FOREIGN KEY (guardian_id) REFERENCES guardians (id) ON DELETE RESTRICT,
                CONSTRAINT communication_recipients_student_id_foreign
                    FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE RESTRICT,
                CONSTRAINT communication_recipients_direct_user_id_foreign
                    FOREIGN KEY (direct_user_id) REFERENCES users (id) ON DELETE RESTRICT,
                CONSTRAINT communication_recipients_resolved_user_id_foreign
                    FOREIGN KEY (resolved_user_id) REFERENCES users (id) ON DELETE RESTRICT,

                CONSTRAINT communication_recipients_unique_per_staff
                    UNIQUE (communication_id, staff_id),
                CONSTRAINT communication_recipients_unique_per_guardian
                    UNIQUE (communication_id, guardian_id),
                CONSTRAINT communication_recipients_unique_per_student
                    UNIQUE (communication_id, student_id),
                CONSTRAINT communication_recipients_unique_per_direct_user
                    UNIQUE (communication_id, direct_user_id)
            )"
        );
    }

    private function createSqlite(): void
    {
        DB::statement(
            "CREATE TABLE communication_recipients (
                id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
                communication_id integer NOT NULL,
                staff_id integer NULL,
                guardian_id integer NULL,
                student_id integer NULL,
                direct_user_id integer NULL,
                resolved_user_id integer NULL,
                recipient_name_snapshot varchar NOT NULL,
                recipient_context_snapshot varchar NULL,
                read_at datetime NULL,

                created_at datetime NULL,
                updated_at datetime NULL,

                CHECK (
                    (CASE WHEN staff_id IS NOT NULL THEN 1 ELSE 0 END) +
                    (CASE WHEN guardian_id IS NOT NULL THEN 1 ELSE 0 END) +
                    (CASE WHEN student_id IS NOT NULL THEN 1 ELSE 0 END) +
                    (CASE WHEN direct_user_id IS NOT NULL THEN 1 ELSE 0 END) = 1
                ),

                FOREIGN KEY (communication_id) REFERENCES communications (id) ON DELETE RESTRICT,
                FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE RESTRICT,
                FOREIGN KEY (guardian_id) REFERENCES guardians (id) ON DELETE RESTRICT,
                FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE RESTRICT,
                FOREIGN KEY (direct_user_id) REFERENCES users (id) ON DELETE RESTRICT,
                FOREIGN KEY (resolved_user_id) REFERENCES users (id) ON DELETE RESTRICT,

                UNIQUE (communication_id, staff_id),
                UNIQUE (communication_id, guardian_id),
                UNIQUE (communication_id, student_id),
                UNIQUE (communication_id, direct_user_id)
            )"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_recipients');
    }
};
