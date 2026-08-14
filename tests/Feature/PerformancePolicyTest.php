<?php

namespace Tests\Feature;

use App\Models\PerformanceEvaluation;
use App\Models\PerformanceFramework;
use App\Models\Staff;
use App\Models\User;
use App\Services\PerformanceEvaluationService;
use Database\Seeders\PositionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsPerformanceFixtures;
use Tests\TestCase;

/**
 * Authorization for Frameworks and Evaluations.
 *
 * The self-view carve-out is the part worth reading closely: it is NOT a
 * permission, it is a narrow policy exception that only ever fires for a
 * staff member's own FINALIZED record, and only when their login is
 * exclusively theirs -- proven here by the shared-login case, which must
 * come back false rather than guess.
 */
class PerformancePolicyTest extends TestCase
{
    use BuildsPerformanceFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PositionSeeder::class);
    }

    private function finalizedEvaluationFor(Staff $staff): PerformanceEvaluation
    {
        [$framework, , , $high] = $this->activeFramework();
        $staff->update(['staff_category_id' => $framework->staff_category_id]);
        $evaluator = $this->userWithRole('principal');
        $evaluations = app(PerformanceEvaluationService::class);
        $evaluation = $evaluations->create($staff, $framework, $evaluator, '2026-01-01', '2026-06-30');
        $this->respondToEveryItem($evaluation, $high->id);
        $evaluations->setOverallRating($evaluation, $high);

        return $evaluations->finalize($evaluation, $evaluator);
    }

    // ------------------------------------------------------- Framework policy

    public function test_principal_can_manage_frameworks_management_can_only_view(): void
    {
        $this->seedPerformanceReferenceData();
        $framework = app(\App\Services\PerformanceFrameworkService::class)
            ->create($this->teacherCategory(), ['name' => 'X', 'code' => 'X', 'version' => '1']);

        $principal = $this->userWithRole('principal');
        $management = $this->userWithRole('management');

        $this->assertTrue($principal->can('create', PerformanceFramework::class));
        $this->assertTrue($principal->can('update', $framework));
        $this->assertTrue($principal->can('view', $framework));

        $this->assertFalse($management->can('create', PerformanceFramework::class));
        $this->assertFalse($management->can('update', $framework));
        $this->assertTrue($management->can('view', $framework));
    }

    public function test_teacher_and_admin_staff_hold_neither_performance_permission(): void
    {
        $this->seedPerformanceReferenceData();
        $framework = app(\App\Services\PerformanceFrameworkService::class)
            ->create($this->teacherCategory(), ['name' => 'X', 'code' => 'X', 'version' => '1']);

        foreach (['teacher', 'admin_staff'] as $role) {
            $user = $this->userWithRole($role);
            $this->assertFalse($user->can('view', $framework), "{$role} should not see framework structure");
            $this->assertFalse($user->can('viewAny', PerformanceFramework::class));
        }
    }

    public function test_framework_structure_is_only_editable_while_draft_at_the_policy_level(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework] = $this->activeFramework();
        $principal = $this->userWithRole('principal');

        $this->assertFalse($principal->can('update', $framework), 'active framework structure is frozen');
        $this->assertTrue($principal->can('activate', app(\App\Services\PerformanceFrameworkService::class)
            ->create($this->teacherCategory(), ['name' => 'Y', 'code' => 'Y', 'version' => '1'])));
        $this->assertFalse($principal->can('activate', $framework), 'already active');
        $this->assertTrue($principal->can('archive', $framework));
    }

    // ----------------------------------------------------- Evaluation policy

    public function test_self_view_works_only_for_an_unambiguous_own_login(): void
    {
        $this->seedPerformanceReferenceData();
        $user = User::factory()->create();
        $staff = $this->staffInCategory($this->teacherCategory(), $user->id);
        $finalized = $this->finalizedEvaluationFor($staff);

        $this->assertTrue($user->fresh()->can('view', $finalized), 'own finalized evaluation, unique login');
    }

    public function test_self_view_never_shows_a_draft(): void
    {
        $this->seedPerformanceReferenceData();
        $user = User::factory()->create();
        $staff = $this->staffInCategory($this->teacherCategory(), $user->id);
        [$framework, , , $high] = $this->activeFramework();
        $staff->update(['staff_category_id' => $framework->staff_category_id]);
        $draft = app(PerformanceEvaluationService::class)
            ->create($staff, $framework, $this->userWithRole('principal'), '2026-01-01', '2026-06-30');

        $this->assertFalse($user->fresh()->can('view', $draft), 'a draft is never shown, even to the person it is about');
    }

    public function test_self_view_refuses_a_shared_login_rather_than_guessing(): void
    {
        $this->seedPerformanceReferenceData();
        $user = User::factory()->create();
        $staffA = $this->staffInCategory($this->teacherCategory(), $user->id);
        $this->staffInCategory($this->teacherCategory(), $user->id); // shares the same login

        $finalized = $this->finalizedEvaluationFor($staffA);

        $this->assertFalse($user->fresh()->can('view', $finalized), 'shared login is not attributable to either staff row');
    }

    public function test_self_view_does_not_apply_to_an_unrelated_staff_members_login(): void
    {
        $this->seedPerformanceReferenceData();
        $ownerUser = User::factory()->create();
        $owner = $this->staffInCategory($this->teacherCategory(), $ownerUser->id);
        $finalized = $this->finalizedEvaluationFor($owner);

        $strangerUser = User::factory()->create();
        $this->staffInCategory($this->teacherCategory(), $strangerUser->id);

        $this->assertFalse($strangerUser->fresh()->can('view', $finalized));
    }

    public function test_management_and_principal_can_view_any_evaluation_regardless_of_self_view(): void
    {
        $this->seedPerformanceReferenceData();
        $owner = $this->staffInCategory($this->teacherCategory());
        $finalized = $this->finalizedEvaluationFor($owner);

        $this->assertTrue($this->userWithRole('management')->can('view', $finalized));
        $this->assertTrue($this->userWithRole('principal')->can('view', $finalized));
    }

    public function test_only_principal_may_create_update_or_finalize_an_evaluation(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework] = $this->activeFramework();
        $staff = $this->staffInCategory($framework->staffCategory);
        $draft = app(PerformanceEvaluationService::class)
            ->create($staff, $framework, $this->userWithRole('principal'), '2026-01-01', '2026-06-30');

        $management = $this->userWithRole('management');
        $principal = $this->userWithRole('principal');

        $this->assertFalse($management->can('create', PerformanceEvaluation::class));
        $this->assertFalse($management->can('update', $draft));
        $this->assertFalse($management->can('finalize', $draft));
        $this->assertFalse($management->can('delete', $draft));

        $this->assertTrue($principal->can('update', $draft));
        $this->assertTrue($principal->can('finalize', $draft));
        $this->assertTrue($principal->can('delete', $draft));
    }

    public function test_a_finalized_evaluation_cannot_be_updated_finalized_again_or_deleted_by_anyone(): void
    {
        $this->seedPerformanceReferenceData();
        $owner = $this->staffInCategory($this->teacherCategory());
        $finalized = $this->finalizedEvaluationFor($owner);
        $principal = $this->userWithRole('principal');

        $this->assertFalse($principal->can('update', $finalized));
        $this->assertFalse($principal->can('finalize', $finalized));
        $this->assertFalse($principal->can('delete', $finalized));
    }
}
