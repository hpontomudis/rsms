<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BootstrapAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_no_default_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseMissing('users', ['email' => 'admin@rahai.sch.id']);
    }

    public function test_bootstrap_command_refuses_when_unconfigured(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Config::set('services.bootstrap_admin.email', null);
        Config::set('services.bootstrap_admin.password', null);

        $this->artisan('rsms:bootstrap-admin')->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_bootstrap_command_refuses_when_only_email_configured(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Config::set('services.bootstrap_admin.email', 'admin@rahai.sch.id');
        Config::set('services.bootstrap_admin.password', null);

        $this->artisan('rsms:bootstrap-admin')->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_explicit_bootstrap_configuration_creates_the_expected_administrator(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Config::set('services.bootstrap_admin.email', 'verify-admin@rahai.sch.id');
        Config::set('services.bootstrap_admin.password', 'a-real-verification-password-1');

        $this->artisan('rsms:bootstrap-admin')->assertExitCode(0);

        $admin = User::where('email', 'verify-admin@rahai.sch.id')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('super_admin'));
        $this->assertTrue($admin->isActive());
    }

    public function test_the_bootstrapped_password_is_hashed_not_stored_literally(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Config::set('services.bootstrap_admin.email', 'verify-admin@rahai.sch.id');
        Config::set('services.bootstrap_admin.password', 'a-real-verification-password-1');

        $this->artisan('rsms:bootstrap-admin');

        $admin = User::where('email', 'verify-admin@rahai.sch.id')->first();
        $this->assertNotEquals('a-real-verification-password-1', $admin->getRawOriginal('password'));
        $this->assertTrue(Hash::check('a-real-verification-password-1', $admin->password));
    }

    public function test_repeated_bootstrap_is_idempotent(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Config::set('services.bootstrap_admin.email', 'verify-admin@rahai.sch.id');
        Config::set('services.bootstrap_admin.password', 'a-real-verification-password-1');

        $this->artisan('rsms:bootstrap-admin')->assertExitCode(0);
        $this->artisan('rsms:bootstrap-admin')->assertExitCode(0);

        $this->assertDatabaseCount('users', 1);
        $this->assertEquals(
            1,
            User::where('email', 'verify-admin@rahai.sch.id')->count()
        );
    }

    public function test_bootstrapped_admin_can_authenticate(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Config::set('services.bootstrap_admin.email', 'verify-admin@rahai.sch.id');
        Config::set('services.bootstrap_admin.password', 'a-real-verification-password-1');
        $this->artisan('rsms:bootstrap-admin');

        $this->assertTrue(auth()->attempt([
            'email' => 'verify-admin@rahai.sch.id',
            'password' => 'a-real-verification-password-1',
            'status' => 'active',
        ]));
    }

    public function test_configuration_seeders_remain_unaffected_by_the_bootstrap_change(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(\Spatie\Permission\Models\Role::where('name', 'super_admin')->exists());
        $this->assertTrue(\Spatie\Permission\Models\Role::where('name', 'teacher')->exists());
        $this->assertTrue(\App\Models\Grade::where('name', 'Year 1')->exists());
        $this->assertTrue(\App\Models\AcademicYear::where('is_current', true)->exists());
    }
}
