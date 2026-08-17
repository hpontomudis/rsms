<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Configuration/reference data only -- every seeder called here is
 * idempotent and safe to run in ANY environment, including staging and
 * production (Pre-UAT Hardening P1). None of it creates a user account.
 *
 * This used to also create a super_admin (`admin@rahai.sch.id` with a
 * hardcoded literal password) unconditionally on every run, in every
 * environment. That was removed: creating a login account is not
 * configuration data, and a predictable seeded credential is exactly the
 * kind of thing that must never exist outside deliberate, explicit action.
 * The one and only way to create the initial super_admin now is
 * `php artisan rsms:bootstrap-admin`, which requires BOOTSTRAP_ADMIN_EMAIL
 * and BOOTSTRAP_ADMIN_PASSWORD to be explicitly configured first -- see
 * App\Console\Commands\BootstrapAdminCommand and
 * config/services.php's `bootstrap_admin` block. Run it manually,
 * immediately after this seeder, in every environment that needs a login.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            GradeSeeder::class,
            PositionSeeder::class,
            StaffCategorySeeder::class,
            AcademicYearSeeder::class,
            // Must follow AcademicYearSeeder -- periods hang off a year.
            AcademicPeriodSeeder::class,
            // Must follow GradeSeeder -- programme applicability references grades.
            EnglishProgrammeSeeder::class,
            LearningPhaseSeeder::class,
        ]);
    }
}
