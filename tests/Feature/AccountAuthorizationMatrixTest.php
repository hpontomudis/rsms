<?php

namespace Tests\Feature;

use App\Models\Position;
use App\Models\Staff;
use App\Models\User;
use App\Services\AccountAuthorizationMatrix;
use App\Services\UserProvisioningService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * P2.1 -- the actor+target-role matrix that closes the privilege-escalation
 * path P2's own `users.reset-password`/`staff.import` permissions left
 * open: actor permission alone previously meant "may act on ANY target,"
 * including one more privileged than the actor.
 */
class AccountAuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function staffWithUserRole(string $role): Staff
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return Staff::create([
            'staff_number' => 'MTX-'.uniqid(), 'first_name' => 'Target', 'last_name' => ucfirst($role),
            'position_id' => Position::firstOrCreate(['title' => 'Support Staff'])->id,
            'phone' => '0812-0000-0000', 'hire_date' => '2020-07-01', 'user_id' => $user->id,
        ]);
    }

    // --- §10: bulk provisioning matrix ---

    public function test_admin_staff_can_provision_teacher(): void
    {
        $actor = $this->userWithRole('admin_staff');
        $this->assertTrue(AccountAuthorizationMatrix::canProvision($actor, 'teacher'));
    }

    #[DataProvider('forbiddenAdminStaffProvisionTargets')]
    public function test_admin_staff_cannot_provision(string $role): void
    {
        $actor = $this->userWithRole('admin_staff');
        $this->assertFalse(AccountAuthorizationMatrix::canProvision($actor, $role));
    }

    public static function forbiddenAdminStaffProvisionTargets(): array
    {
        return [
            ['admin_staff'], ['finance_staff'], ['management'], ['principal'], ['super_admin'],
        ];
    }

    #[DataProvider('allowedSuperAdminProvisionTargets')]
    public function test_super_admin_can_bulk_provision(string $role): void
    {
        $actor = $this->userWithRole('super_admin');
        $this->assertTrue(AccountAuthorizationMatrix::canProvision($actor, $role));
    }

    public static function allowedSuperAdminProvisionTargets(): array
    {
        return [['teacher'], ['admin_staff'], ['finance_staff'], ['management']];
    }

    #[DataProvider('forbiddenSuperAdminProvisionTargets')]
    public function test_super_admin_cannot_bulk_provision(string $role): void
    {
        $actor = $this->userWithRole('super_admin');
        $this->assertFalse(AccountAuthorizationMatrix::canProvision($actor, $role));
    }

    public static function forbiddenSuperAdminProvisionTargets(): array
    {
        return [['principal'], ['super_admin']];
    }

    // --- §11: password reset matrix ---

    public function test_admin_staff_can_reset_teacher(): void
    {
        $actor = $this->userWithRole('admin_staff');
        $target = User::factory()->create();
        $target->assignRole('teacher');

        $this->assertTrue(AccountAuthorizationMatrix::canResetPasswordFor($actor, $target));
    }

    #[DataProvider('forbiddenAdminStaffResetTargets')]
    public function test_admin_staff_cannot_reset(string $role): void
    {
        $actor = $this->userWithRole('admin_staff');
        $target = User::factory()->create();
        $target->assignRole($role);

        $this->assertFalse(AccountAuthorizationMatrix::canResetPasswordFor($actor, $target));
    }

    public static function forbiddenAdminStaffResetTargets(): array
    {
        return [
            ['admin_staff'], ['finance_staff'], ['management'], ['principal'], ['super_admin'],
        ];
    }

    #[DataProvider('allowedSuperAdminResetTargets')]
    public function test_super_admin_can_reset_operational_roles(string $role): void
    {
        $actor = $this->userWithRole('super_admin');
        $target = User::factory()->create();
        $target->assignRole($role);

        $this->assertTrue(AccountAuthorizationMatrix::canResetPasswordFor($actor, $target));
    }

    public static function allowedSuperAdminResetTargets(): array
    {
        return [['teacher'], ['admin_staff'], ['finance_staff'], ['management']];
    }

    public function test_super_admin_cannot_reset_another_super_admin_through_the_matrix(): void
    {
        // Pinned deliberately (§6/§8): even super_admin -> super_admin is
        // denied through the ordinary reset workflow. Bootstrap remains
        // the sanctioned path for a super_admin credential.
        $actor = $this->userWithRole('super_admin');
        $target = User::factory()->create();
        $target->assignRole('super_admin');

        $this->assertFalse(AccountAuthorizationMatrix::canResetPasswordFor($actor, $target));
    }

    public function test_super_admin_cannot_reset_principal(): void
    {
        $actor = $this->userWithRole('super_admin');
        $target = User::factory()->create();
        $target->assignRole('principal');

        $this->assertFalse(AccountAuthorizationMatrix::canResetPasswordFor($actor, $target));
    }

    // --- §12: no target-role guessing ---

    public function test_a_roleless_target_fails_closed(): void
    {
        $actor = $this->userWithRole('super_admin');
        $target = User::factory()->create(); // no role assigned at all

        $this->assertFalse(AccountAuthorizationMatrix::canResetPasswordFor($actor, $target));
    }

    public function test_a_target_with_more_than_one_role_fails_closed(): void
    {
        $actor = $this->userWithRole('super_admin');
        $target = User::factory()->create();
        $target->assignRole('teacher');
        $target->assignRole('management');

        $this->assertFalse(AccountAuthorizationMatrix::canResetPasswordFor($actor, $target));
    }

    // --- End-to-end: the actual finding, proven closed via the real service/policy/Livewire path ---

    public function test_end_to_end_admin_staff_can_no_longer_reset_a_management_accounts_password(): void
    {
        $adminStaff = $this->userWithRole('admin_staff');
        $managementStaff = $this->staffWithUserRole('management');

        Livewire::actingAs($adminStaff)
            ->test(\App\Livewire\Staff\Show::class, ['staff' => $managementStaff])
            ->call('resetPassword')
            ->assertForbidden();

        $this->assertFalse($managementStaff->user->fresh()->must_change_password);
    }

    public function test_end_to_end_admin_staff_denial_gives_a_real_403_not_merely_a_hidden_button(): void
    {
        $adminStaff = $this->userWithRole('admin_staff');
        $financeStaff = $this->staffWithUserRole('finance_staff');

        // Calling the action directly (as if the button were visible)
        // still refuses -- proving this is enforced server-side.
        $this->assertFalse($adminStaff->can('resetPassword', $financeStaff));

        Livewire::actingAs($adminStaff)
            ->test(\App\Livewire\Staff\Show::class, ['staff' => $financeStaff])
            ->call('resetPassword')
            ->assertForbidden();
    }

    public function test_end_to_end_super_admin_reset_of_another_super_admin_via_staff_ui_is_denied(): void
    {
        $superAdminActor = $this->userWithRole('super_admin');
        $superAdminTarget = $this->staffWithUserRole('super_admin');

        // Gate::before makes $this->authorize('resetPassword', ...) pass
        // unconditionally for a super_admin actor -- the real refusal
        // comes from UserProvisioningService itself (an AuthorizationException
        // thrown from inside the component action, still rendered as a 403
        // by Laravel's exception handler regardless of where it originated).
        Livewire::actingAs($superAdminActor)
            ->test(\App\Livewire\Staff\Show::class, ['staff' => $superAdminTarget])
            ->call('resetPassword')
            ->assertForbidden();

        $this->assertTrue($superAdminTarget->user->fresh()->must_change_password === false);
    }

    public function test_service_layer_refuses_reset_even_when_called_directly_bypassing_policy(): void
    {
        // Defense-in-depth (§7): the service itself refuses, proving the
        // restriction does not depend on every caller remembering to check
        // StaffPolicy first.
        $adminStaff = $this->userWithRole('admin_staff');
        $managementUser = User::factory()->create();
        $managementUser->assignRole('management');

        $this->expectException(AuthorizationException::class);
        app(UserProvisioningService::class)->resetPassword($adminStaff, $managementUser);
    }

    public function test_service_layer_refuses_provision_even_when_called_directly_bypassing_validator(): void
    {
        $adminStaff = $this->userWithRole('admin_staff');

        $this->expectException(AuthorizationException::class);
        app(UserProvisioningService::class)->provision($adminStaff, 'Sneaky Manager', 'sneaky@rahai.sch.id', 'management');
    }
}
