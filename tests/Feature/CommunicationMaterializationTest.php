<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsCommunicationFixtures;
use Tests\TestCase;

/**
 * Publish resolves audience rules fresh and writes communication_recipients
 * once; every subsequent membership/relationship/category/role change is
 * invisible to that frozen history. Deduplication happens once, by canonical
 * recipient identity, across every rule on the Communication.
 */
class CommunicationMaterializationTest extends TestCase
{
    use BuildsCommunicationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCommunicationReferenceData();
    }

    public function test_live_preview_matches_what_publish_materializes(): void
    {
        $principal = $this->principalUser();
        $this->teacherStaff('Sarah');
        $this->teacherStaff('Budi');
        $communication = $this->draft($principal);
        $this->communications()->addAudienceRule($communication, $principal, ['rule_type' => 'all_staff']);

        $preview = $this->communications()->previewAudience($communication->fresh());
        $published = $this->communications()->publish($communication->fresh(), $principal);

        $this->assertSame($preview['resolved'], $published->recipients()->count());
    }

    public function test_publish_re_resolves_fresh_not_from_a_cached_preview(): void
    {
        $principal = $this->principalUser();
        $this->teacherStaff('Sarah');
        $communication = $this->draft($principal);
        $this->communications()->addAudienceRule($communication, $principal, ['rule_type' => 'all_staff']);

        // A second staff member appears AFTER the preview was drawn.
        $this->communications()->previewAudience($communication->fresh());
        $this->teacherStaff('Budi');

        $published = $this->communications()->publish($communication->fresh(), $principal);

        $this->assertSame(2, $published->recipients()->count());
    }

    public function test_recipient_rows_are_frozen_after_publish(): void
    {
        $principal = $this->principalUser();
        $this->teacherStaff('Sarah');
        $published = $this->publishAllStaff($principal);

        $this->teacherStaff('Budi'); // appears after publish

        $this->assertSame(1, $published->fresh()->recipients()->count());
    }

    public function test_class_membership_change_after_publish_does_not_alter_history(): void
    {
        $principal = $this->principalUser();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'Sarah');
        $student = $this->studentNamed('Evelyn', 'Wijaya', 'STU-1');
        $this->enroll($student, $assignment->schoolClass);

        $communication = $this->draft($principal);
        $this->communications()->addAudienceRule($communication, $principal, [
            'rule_type' => 'school_class_students', 'school_class_id' => $assignment->class_id,
        ]);
        $published = $this->communications()->publish($communication->fresh(), $principal);
        $this->assertSame(1, $published->recipients()->count());

        // Student withdraws from the class after publish.
        $assignment->schoolClass->students()->updateExistingPivot($student->id, ['status' => 'withdrawn']);

        $this->assertSame(1, $published->fresh()->recipients()->count());
    }

    public function test_guardian_relationship_change_after_publish_does_not_alter_history(): void
    {
        $principal = $this->principalUser();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'Sarah');
        $student = $this->studentNamed('Evelyn', 'Wijaya', 'STU-1');
        $this->enroll($student, $assignment->schoolClass);
        $guardian = $this->guardianNamed('Rudi', 'Wijaya', '0812-1');
        $this->linkGuardian($student, $guardian);

        $communication = $this->draft($principal);
        $this->communications()->addAudienceRule($communication, $principal, [
            'rule_type' => 'school_class_guardians', 'school_class_id' => $assignment->class_id,
        ]);
        $published = $this->communications()->publish($communication->fresh(), $principal);
        $this->assertSame(1, $published->recipients()->count());

        // The guardian relationship is detached after publish.
        $student->guardians()->detach($guardian->id);

        $this->assertSame(1, $published->fresh()->recipients()->count());
    }

    public function test_role_change_after_publish_does_not_alter_history(): void
    {
        $principal = $this->principalUser();
        $management = $this->managementUser();

        $communication = $this->draft($principal);
        $this->communications()->addAudienceRule($communication, $principal, ['rule_type' => 'role', 'role_name' => 'management']);
        $published = $this->communications()->publish($communication->fresh(), $principal);
        $this->assertSame(1, $published->recipients()->count());

        $management->removeRole('management');

        $this->assertSame(1, $published->fresh()->recipients()->count());
    }

    public function test_staff_category_change_after_publish_does_not_alter_history(): void
    {
        $principal = $this->principalUser();
        $staff = $this->teacherStaff('Sarah');
        $staff->update(['staff_category_id' => $this->staffCategory()->id]);

        $communication = $this->draft($principal);
        $this->communications()->addAudienceRule($communication, $principal, [
            'rule_type' => 'staff_category', 'staff_category_id' => $this->staffCategory()->id,
        ]);
        $published = $this->communications()->publish($communication->fresh(), $principal);
        $this->assertSame(1, $published->recipients()->count());

        $staff->update(['staff_category_id' => null]);

        $this->assertSame(1, $published->fresh()->recipients()->count());
    }

    public function test_overlapping_rules_deduplicate_the_same_recipient(): void
    {
        $principal = $this->principalUser();
        $staff = $this->teacherStaff('Sarah');
        $communication = $this->draft($principal);
        $this->communications()->addAudienceRule($communication, $principal, ['rule_type' => 'all_staff']);
        $this->communications()->addAudienceRule($communication, $principal, [
            'rule_type' => 'selected_staff', 'ids' => [$staff->id],
        ]);

        $published = $this->communications()->publish($communication->fresh(), $principal);

        $this->assertSame(1, $published->recipients()->count());
    }

    public function test_a_guardian_with_two_children_in_the_matched_audience_appears_once(): void
    {
        $principal = $this->principalUser();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'Sarah');
        $child1 = $this->studentNamed('Evelyn', 'Wijaya', 'STU-1');
        $child2 = $this->studentNamed('Erik', 'Wijaya', 'STU-2');
        $this->enroll($child1, $assignment->schoolClass);
        $this->enroll($child2, $assignment->schoolClass);
        $guardian = $this->guardianNamed('Rudi', 'Wijaya', '0812-1');
        $this->linkGuardian($child1, $guardian);
        $this->linkGuardian($child2, $guardian);

        $communication = $this->draft($principal);
        $this->communications()->addAudienceRule($communication, $principal, [
            'rule_type' => 'school_class_guardians', 'school_class_id' => $assignment->class_id,
        ]);
        $published = $this->communications()->publish($communication->fresh(), $principal);

        $this->assertSame(1, $published->recipients()->where('guardian_id', $guardian->id)->count());
    }

    public function test_direct_selected_guardian_plus_class_derived_same_guardian_deduplicates(): void
    {
        $principal = $this->principalUser();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'Sarah');
        $student = $this->studentNamed('Evelyn', 'Wijaya', 'STU-1');
        $this->enroll($student, $assignment->schoolClass);
        $guardian = $this->guardianNamed('Rudi', 'Wijaya', '0812-1');
        $this->linkGuardian($student, $guardian);

        $communication = $this->draft($principal);
        $this->communications()->addAudienceRule($communication, $principal, [
            'rule_type' => 'school_class_guardians', 'school_class_id' => $assignment->class_id,
        ]);
        $this->communications()->addAudienceRule($communication, $principal, [
            'rule_type' => 'selected_guardians', 'ids' => [$guardian->id],
        ]);
        $published = $this->communications()->publish($communication->fresh(), $principal);

        $this->assertSame(1, $published->recipients()->where('guardian_id', $guardian->id)->count());
    }

    private function draft($actor)
    {
        return $this->communications()->createDraft($actor, [
            'display_sender' => 'Test', 'title' => 'Test', 'body' => 'Body.',
        ]);
    }

    private function publishAllStaff($actor)
    {
        $communication = $this->draft($actor);
        $this->communications()->addAudienceRule($communication, $actor, ['rule_type' => 'all_staff']);

        return $this->communications()->publish($communication->fresh(), $actor);
    }
}
