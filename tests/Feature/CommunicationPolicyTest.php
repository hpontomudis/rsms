<?php

namespace Tests\Feature;

use App\Models\Communication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Concerns\BuildsCommunicationFixtures;
use Tests\TestCase;

/**
 * Role grants: principal manages (school-wide), management views only,
 * teacher manages but strictly scoped, admin_staff has neither permission by
 * default. The raw communications.manage permission a teacher holds must
 * never, by itself, unlock a school-wide audience -- CommunicationPolicy and
 * CommunicationService both refuse it independently (see
 * CommunicationAudienceTest for the service-level proof; this file proves
 * the policy/permission layer).
 */
class CommunicationPolicyTest extends TestCase
{
    use BuildsCommunicationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCommunicationReferenceData();
    }

    public function test_principal_can_create_and_publish(): void
    {
        $principal = $this->principalUser();

        $this->assertTrue($principal->can('create', Communication::class));
    }

    public function test_management_cannot_create_or_publish(): void
    {
        $management = $this->managementUser();

        $this->assertFalse($management->can('create', Communication::class));
    }

    public function test_management_can_view_any_communication(): void
    {
        $principal = $this->principalUser();
        $management = $this->managementUser();
        $draft = $this->communications()->createDraft($principal, [
            'display_sender' => 'Test', 'title' => 'Test', 'body' => 'Body.',
        ]);

        $this->assertTrue($management->can('view', $draft));
    }

    public function test_admin_staff_holds_neither_communications_permission_by_default(): void
    {
        $adminStaff = $this->adminStaffUser();

        $this->assertFalse($adminStaff->can('communications.view'));
        $this->assertFalse($adminStaff->can('communications.manage'));
        $this->assertFalse($adminStaff->can('create', Communication::class));
    }

    public function test_admin_staff_cannot_view_a_draft_they_did_not_author_and_are_not_a_recipient_of(): void
    {
        $principal = $this->principalUser();
        $adminStaff = $this->adminStaffUser();
        $draft = $this->communications()->createDraft($principal, [
            'display_sender' => 'Test', 'title' => 'Test', 'body' => 'Body.',
        ]);

        $this->assertFalse($adminStaff->can('view', $draft));
    }

    public function test_teacher_holds_communications_manage_but_cannot_view_another_teachers_communication(): void
    {
        $sarah = $this->teacherUserFor('Sarah');
        $budi = $this->teacherUserFor('Budi');
        $this->assertTrue($budi->can('communications.manage'));

        $draft = $this->communications()->createDraft($sarah, [
            'display_sender' => 'Test', 'title' => 'Test', 'body' => 'Body.',
        ]);

        $this->assertFalse($budi->can('view', $draft));
    }

    public function test_teacher_can_view_their_own_draft(): void
    {
        $sarah = $this->teacherUserFor('Sarah');
        $draft = $this->communications()->createDraft($sarah, [
            'display_sender' => 'Test', 'title' => 'Test', 'body' => 'Body.',
        ]);

        $this->assertTrue($sarah->can('view', $draft));
        $this->assertTrue($sarah->can('update', $draft));
        $this->assertTrue($sarah->can('publish', $draft));
        $this->assertTrue($sarah->can('delete', $draft));
    }

    public function test_teacher_cannot_update_another_teachers_draft(): void
    {
        $sarah = $this->teacherUserFor('Sarah');
        $budi = $this->teacherUserFor('Budi');
        $draft = $this->communications()->createDraft($sarah, [
            'display_sender' => 'Test', 'title' => 'Test', 'body' => 'Body.',
        ]);

        $this->assertFalse($budi->can('update', $draft));
        $this->assertFalse($budi->can('publish', $draft));
        $this->assertFalse($budi->can('delete', $draft));
    }

    public function test_recipient_access_depends_on_a_materialized_recipient_row_not_permission(): void
    {
        $principal = $this->principalUser();
        $staff = $this->teacherStaff('Sarah');
        $sarahUser = $staff->user->fresh();

        $communication = $this->communications()->createDraft($principal, [
            'display_sender' => 'Test', 'title' => 'Test', 'body' => 'Body.',
        ]);
        $this->communications()->addAudienceRule($communication, $principal, [
            'rule_type' => 'selected_staff', 'ids' => [$staff->id],
        ]);
        $published = $this->communications()->publish($communication->fresh(), $principal);

        // Sarah holds no communications.* permission at all (teacher does,
        // via the seeded role -- strip it to prove access truly comes from
        // the recipient row, not the permission).
        $sarahUser->removeRole('teacher');
        $sarahUser->refresh();

        $this->assertFalse($sarahUser->can('communications.view'));
        $this->assertFalse($sarahUser->can('communications.manage'));
        $this->assertTrue($sarahUser->can('view', $published));
    }

    public function test_super_admin_bypasses_every_communications_gate(): void
    {
        $superAdmin = $this->userWithRole('super_admin');

        $this->assertTrue($superAdmin->can('create', Communication::class));
        $this->assertTrue($superAdmin->can('communications.manage'));
    }

    public function test_teacher_cannot_bypass_scope_by_calling_the_service_directly_with_a_forbidden_rule(): void
    {
        $sarah = $this->teacherUserFor('Sarah');
        $this->teacherStaff('Sarah');
        $draft = $this->communications()->createDraft($sarah, [
            'display_sender' => 'Test', 'title' => 'Test', 'body' => 'Body.',
        ]);

        $this->expectException(ValidationException::class);
        $this->communications()->addAudienceRule($draft, $sarah, ['rule_type' => 'everyone']);
    }
}
