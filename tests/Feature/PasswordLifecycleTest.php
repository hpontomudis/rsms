<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Position;
use App\Models\Staff;
use App\Models\User;
use App\Services\UserProvisioningService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function staffWithUser(string $role = 'teacher'): Staff
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return Staff::create([
            'staff_number' => 'STF-'.uniqid(), 'first_name' => 'Test', 'last_name' => 'Person',
            'position_id' => Position::firstOrCreate(['title' => 'Support Staff'])->id,
            'phone' => '0812-0000-0000', 'hire_date' => '2020-07-01', 'user_id' => $user->id,
        ]);
    }

    // --- Provisioning ---

    private function superAdminActor(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        return $admin;
    }

    public function test_provisioned_account_has_a_hashed_password_and_must_change_password_true(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $result = app(UserProvisioningService::class)->provision($this->superAdminActor(), 'New Teacher', 'new-teacher@rahai.sch.id', 'teacher');

        $this->assertNotEquals($result['temporaryPassword'], $result['user']->getRawOriginal('password'));
        $this->assertTrue(Hash::check($result['temporaryPassword'], $result['user']->password));
        $this->assertTrue($result['user']->fresh()->must_change_password);
    }

    public function test_temporary_password_is_never_persisted_in_plaintext_anywhere(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $result = app(UserProvisioningService::class)->provision($this->superAdminActor(), 'New Teacher', 'plaintext-check@rahai.sch.id', 'teacher');

        $this->assertDatabaseMissing('users', ['password' => $result['temporaryPassword']]);
        $this->assertDatabaseMissing('audit_logs', ['new_values' => json_encode(['password' => $result['temporaryPassword']])]);
    }

    // --- Forced first-login redirect ---

    public function test_a_user_with_must_change_password_is_redirected_away_from_the_dashboard(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['must_change_password' => true]);
        $user->assignRole('teacher');

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('password.change'));
    }

    public function test_a_user_with_must_change_password_can_still_reach_the_change_password_page(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['must_change_password' => true]);
        $user->assignRole('teacher');

        $this->actingAs($user)->get('/password/change')->assertOk();
    }

    public function test_a_user_with_must_change_password_can_still_logout(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['must_change_password' => true]);
        $user->assignRole('teacher');

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));
    }

    public function test_a_user_without_must_change_password_reaches_the_dashboard_normally(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['must_change_password' => false]);
        $user->assignRole('teacher');

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    // --- Password change page ---

    public function test_changing_password_clears_the_must_change_password_flag(): void
    {
        $user = User::factory()->create(['password' => 'old-temp-password-123', 'must_change_password' => true]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Auth\ChangePassword::class)
            ->set('current_password', 'old-temp-password-123')
            ->set('new_password', 'a-brand-new-password-1')
            ->set('new_password_confirmation', 'a-brand-new-password-1')
            ->call('save');

        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertTrue(Hash::check('a-brand-new-password-1', $user->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'the-real-password-1', 'must_change_password' => true]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Auth\ChangePassword::class)
            ->set('current_password', 'totally-wrong-password')
            ->set('new_password', 'a-brand-new-password-1')
            ->set('new_password_confirmation', 'a-brand-new-password-1')
            ->call('save')
            ->assertHasErrors(['current_password']);

        $this->assertTrue($user->fresh()->must_change_password);
    }

    // --- Admin reset ---

    public function test_super_admin_can_reset_a_staff_members_password(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $staff = $this->staffWithUser();

        Livewire::actingAs($admin)
            ->test(\App\Livewire\Staff\Show::class, ['staff' => $staff])
            ->call('resetPassword')
            ->assertSet('temporaryPassword', fn ($v) => is_string($v) && strlen($v) >= 16);

        $this->assertTrue($staff->user->fresh()->must_change_password);
    }

    public function test_admin_staff_can_reset_a_staff_members_password(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $adminStaff = User::factory()->create();
        $adminStaff->assignRole('admin_staff');
        $staff = $this->staffWithUser();

        Livewire::actingAs($adminStaff)
            ->test(\App\Livewire\Staff\Show::class, ['staff' => $staff])
            ->call('resetPassword');

        $this->assertTrue($staff->user->fresh()->must_change_password);
    }

    public function test_teacher_cannot_reset_a_staff_members_password(): void
    {
        // A teacher lacks staff.view entirely, so a full Livewire mount
        // fails before resetPassword() is ever reached -- assert the
        // narrower claim directly against the policy instead.
        $this->seed(RolesAndPermissionsSeeder::class);
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');
        $staff = $this->staffWithUser();

        $this->assertFalse($teacher->can('resetPassword', $staff));
    }

    public function test_management_cannot_reset_a_staff_members_password(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $management = User::factory()->create();
        $management->assignRole('management');
        $staff = $this->staffWithUser();

        Livewire::actingAs($management)
            ->test(\App\Livewire\Staff\Show::class, ['staff' => $staff])
            ->call('resetPassword')
            ->assertForbidden();
    }

    public function test_reset_invalidates_other_active_sessions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $staff = $this->staffWithUser();

        \Illuminate\Support\Facades\DB::table('sessions')->insert([
            'id' => 'test-session-id', 'user_id' => $staff->user->id,
            'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => 'x', 'last_activity' => time(),
        ]);

        app(UserProvisioningService::class)->resetPassword($admin, $staff->user);

        $this->assertDatabaseMissing('sessions', ['id' => 'test-session-id']);
    }

    public function test_admin_reset_produces_an_audit_row_with_no_password_content(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $staff = $this->staffWithUser();

        $this->actingAs($admin);
        $password = app(UserProvisioningService::class)->resetPassword($admin, $staff->user);

        $log = AuditLog::where('auditable_type', User::class)
            ->where('auditable_id', $staff->user->id)
            ->where('action', 'password_reset')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringNotContainsString($password, json_encode($log->old_values));
        $this->assertStringNotContainsString($password, json_encode($log->new_values));
    }
}
