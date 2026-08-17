<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Explicit, environment-aware super_admin bootstrap (Pre-UAT Hardening P1).
 *
 * Replaces DatabaseSeeder's old unconditional `admin@rahai.sch.id` /
 * literal-'password' creation, which ran the same way in every environment
 * including staging and production. This command is the ONLY path in the
 * codebase that can create a super_admin, and it does so only when both
 * `BOOTSTRAP_ADMIN_EMAIL` and `BOOTSTRAP_ADMIN_PASSWORD` are explicitly
 * configured (config/services.php `bootstrap_admin`) -- absence of either
 * means no admin is created, never a predictable fallback. Safe to re-run:
 * an existing account by that email is left as-is (password is never
 * silently reset by re-running this), the role is granted only if missing.
 * The password is never echoed, logged, or written anywhere by this
 * command -- it is read once from config and handed straight to the
 * `hashed` cast on User::password.
 */
class BootstrapAdminCommand extends Command
{
    protected $signature = 'rsms:bootstrap-admin';

    protected $description = 'Create the initial super_admin from BOOTSTRAP_ADMIN_EMAIL/BOOTSTRAP_ADMIN_PASSWORD, if configured';

    public function handle(): int
    {
        $email = config('services.bootstrap_admin.email');
        $password = config('services.bootstrap_admin.password');

        if (! $email || ! $password) {
            $this->error('BOOTSTRAP_ADMIN_EMAIL and BOOTSTRAP_ADMIN_PASSWORD must both be set. No admin was created.');

            return self::FAILURE;
        }

        $admin = User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Super Admin', 'password' => $password, 'status' => 'active']
        );

        if (! $admin->hasRole('super_admin')) {
            $admin->assignRole('super_admin');
        }

        $this->info("super_admin ready for {$email}.");

        return self::SUCCESS;
    }
}
