<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One staff member, one framework, one period -- the appraisal itself.
 *
 * `staff_category_id` is the category AT CREATION, copied once and never
 * re-derived. If the staff member's category changes later, this evaluation
 * does not move: it means "conducted under category X", a historical fact,
 * not "this person's current category".
 *
 * Every `*_snapshot` column is NULLABLE. A draft carries none of them --
 * there is nothing to snapshot until finalization, and a draft's live preview
 * is built from PerformanceEvaluationService::preview(), never from these
 * columns. Populating them is entirely PerformanceEvaluationService::finalize()'s
 * job, in one transaction with everything else that freezes.
 *
 * `overall_rating_option_id` is a real FK into the framework's own rating
 * options, via a composite key that includes performance_framework_id -- so an
 * option borrowed from a different framework is refused by the database, not
 * merely the service. There is no loose integer rating anywhere in this schema.
 *
 * The status/finalized-timestamp relationship is enforced by CHECK on both
 * drivers, exactly like the model's own guard: draft carries neither
 * finalized_at nor finalized_by_user_id; finalized carries both.
 *
 * UNIQUE(staff, framework, period_start, period_end): V7A allows exactly one
 * evaluation per staff, framework and exact date range. There is no
 * supersession here -- a finalized evaluation is immutable and permanent, and
 * a genuine correction is a new evaluation against a new (or corrected) period,
 * not a replacement of this one. That is a documented limitation, not an
 * oversight: see PerformanceEvaluationService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::getConnection()->getDriverName() === 'sqlite'
            ? $this->createSqlite()
            : $this->createPostgres();

        DB::statement('CREATE UNIQUE INDEX performance_evaluations_anchor_unique ON performance_evaluations (id, performance_framework_id)');
        DB::statement('CREATE UNIQUE INDEX performance_evaluations_scope_unique ON performance_evaluations (staff_id, performance_framework_id, period_start, period_end)');
        DB::statement('CREATE INDEX performance_evaluations_staff_index ON performance_evaluations (staff_id, status)');
    }

    private function createPostgres(): void
    {
        DB::statement(
            "CREATE TABLE performance_evaluations (
                id bigserial PRIMARY KEY,
                staff_id bigint NOT NULL,
                staff_category_id bigint NOT NULL,
                performance_framework_id bigint NOT NULL,
                evaluator_user_id bigint NOT NULL,
                academic_year_id bigint NULL,
                period_start date NOT NULL,
                period_end date NOT NULL,
                status varchar(255) NOT NULL DEFAULT 'draft',
                overall_rating_option_id bigint NULL,
                summary text NULL,
                strengths text NULL,
                development_priorities text NULL,
                action_plan text NULL,
                review_date date NULL,
                finalized_at timestamp(0) NULL,
                finalized_by_user_id bigint NULL,

                staff_name_snapshot varchar(255) NULL,
                staff_number_snapshot varchar(255) NULL,
                position_title_snapshot varchar(255) NULL,
                staff_category_name_snapshot varchar(255) NULL,
                framework_name_snapshot varchar(255) NULL,
                framework_code_snapshot varchar(255) NULL,
                framework_version_snapshot varchar(255) NULL,
                evaluator_name_snapshot varchar(255) NULL,

                created_at timestamp(0) NULL,
                updated_at timestamp(0) NULL,

                CONSTRAINT performance_evaluations_status_check
                    CHECK (status IN ('draft', 'finalized')),
                CONSTRAINT performance_evaluations_period_order
                    CHECK (period_end >= period_start),
                CONSTRAINT performance_evaluations_finalized_consistency
                    CHECK (
                        (status = 'draft' AND finalized_at IS NULL AND finalized_by_user_id IS NULL)
                        OR
                        (status = 'finalized' AND finalized_at IS NOT NULL AND finalized_by_user_id IS NOT NULL)
                    ),

                CONSTRAINT performance_evaluations_staff_id_foreign
                    FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE RESTRICT,
                CONSTRAINT performance_evaluations_staff_category_id_foreign
                    FOREIGN KEY (staff_category_id) REFERENCES staff_categories (id) ON DELETE RESTRICT,
                CONSTRAINT performance_evaluations_performance_framework_id_foreign
                    FOREIGN KEY (performance_framework_id) REFERENCES performance_frameworks (id) ON DELETE RESTRICT,
                CONSTRAINT performance_evaluations_evaluator_user_id_foreign
                    FOREIGN KEY (evaluator_user_id) REFERENCES users (id) ON DELETE RESTRICT,
                CONSTRAINT performance_evaluations_academic_year_id_foreign
                    FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE RESTRICT,
                CONSTRAINT performance_evaluations_finalized_by_user_id_foreign
                    FOREIGN KEY (finalized_by_user_id) REFERENCES users (id) ON DELETE RESTRICT,

                CONSTRAINT performance_evaluations_rating_option_foreign
                    FOREIGN KEY (overall_rating_option_id, performance_framework_id)
                    REFERENCES performance_rating_options (id, performance_framework_id) ON DELETE RESTRICT
            )"
        );
    }

    private function createSqlite(): void
    {
        DB::statement(
            "CREATE TABLE performance_evaluations (
                id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
                staff_id integer NOT NULL,
                staff_category_id integer NOT NULL,
                performance_framework_id integer NOT NULL,
                evaluator_user_id integer NOT NULL,
                academic_year_id integer NULL,
                period_start date NOT NULL,
                period_end date NOT NULL,
                status varchar NOT NULL DEFAULT 'draft',
                overall_rating_option_id integer NULL,
                summary text NULL,
                strengths text NULL,
                development_priorities text NULL,
                action_plan text NULL,
                review_date date NULL,
                finalized_at datetime NULL,
                finalized_by_user_id integer NULL,

                staff_name_snapshot varchar NULL,
                staff_number_snapshot varchar NULL,
                position_title_snapshot varchar NULL,
                staff_category_name_snapshot varchar NULL,
                framework_name_snapshot varchar NULL,
                framework_code_snapshot varchar NULL,
                framework_version_snapshot varchar NULL,
                evaluator_name_snapshot varchar NULL,

                created_at datetime NULL,
                updated_at datetime NULL,

                CHECK (status IN ('draft', 'finalized')),
                CHECK (period_end >= period_start),
                CHECK (
                    (status = 'draft' AND finalized_at IS NULL AND finalized_by_user_id IS NULL)
                    OR
                    (status = 'finalized' AND finalized_at IS NOT NULL AND finalized_by_user_id IS NOT NULL)
                ),

                FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE RESTRICT,
                FOREIGN KEY (staff_category_id) REFERENCES staff_categories (id) ON DELETE RESTRICT,
                FOREIGN KEY (performance_framework_id) REFERENCES performance_frameworks (id) ON DELETE RESTRICT,
                FOREIGN KEY (evaluator_user_id) REFERENCES users (id) ON DELETE RESTRICT,
                FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE RESTRICT,
                FOREIGN KEY (finalized_by_user_id) REFERENCES users (id) ON DELETE RESTRICT,
                FOREIGN KEY (overall_rating_option_id, performance_framework_id)
                    REFERENCES performance_rating_options (id, performance_framework_id) ON DELETE RESTRICT
            )"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_evaluations');
    }
};
