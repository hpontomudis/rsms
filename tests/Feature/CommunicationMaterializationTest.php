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

    /**
     * Regression pin for the bug browser verification caught:
     * AudienceResolver::everyone() concatenated raw Student models instead of
     * AudienceCandidate objects, throwing a TypeError the moment the "everyone"
     * rule actually had a Student to include. Every EARLIER "everyone" test in
     * this suite used all_staff-only or a Staff-only population, so an empty
     * Student collection meant the buggy concat() never received a mismatched
     * type and every one of those tests passed against the broken code. This
     * test exists specifically because it does NOT make that mistake: it
     * mixes Staff, Guardian and Student in one population, some reachable via
     * a linked User and some not, exactly the shape that made the bug visible
     * in the browser.
     */
    public function test_everyone_resolves_a_mixed_population_correctly_and_notifies_only_reachable_recipients(): void
    {
        $principal = $this->principalUser();

        // Two Staff, both User-linked via teacherStaff()'s own fixture.
        $sarah = $this->teacherStaff('Sarah');
        $this->teacherStaff('Budi');
        // A third Staff with no login at all.
        \App\Models\Staff::create([
            'staff_number' => 'S-NOLOGIN', 'first_name' => 'NoLogin', 'last_name' => 'Staff',
            'position_id' => \App\Models\Position::firstOrFail()->id, 'phone' => '08',
            'hire_date' => '2020-01-01', 'status' => 'active',
        ]);

        // Two active Students: one with a login, one without.
        $reachableStudentUser = \App\Models\User::factory()->create();
        $studentWithLogin = $this->studentNamed('Erik', 'Wijaya', 'STU-1');
        $studentWithLogin->update(['user_id' => $reachableStudentUser->id]);
        $studentWithoutLogin = $this->studentNamed('Evelyn', 'Wijaya', 'STU-2');

        // Two Guardians: one with a login, one without.
        $reachableGuardianUser = \App\Models\User::factory()->create();
        $guardianWithLogin = $this->guardianNamed('Rudi', 'Wijaya', '0812-0001', $reachableGuardianUser->id);
        $guardianWithoutLogin = $this->guardianNamed('Sri', 'Wijaya', '0812-0002');
        $this->linkGuardian($studentWithLogin, $guardianWithLogin);
        $this->linkGuardian($studentWithoutLogin, $guardianWithoutLogin);

        $communication = $this->draft($principal);
        $this->communications()->addAudienceRule($communication, $principal, ['rule_type' => 'everyone']);

        // The bug threw here, inside previewAudience(), before publish was
        // ever reached -- assert the preview itself succeeds and is correct.
        $preview = $this->communications()->previewAudience($communication->fresh());

        $this->assertSame(7, $preview['resolved']); // 3 staff + 2 students + 2 guardians
        $this->assertSame(['staff' => 3, 'student' => 2, 'guardian' => 2], $preview['byType']);
        $this->assertSame(4, $preview['reachable']); // sarah, budi, studentWithLogin, guardianWithLogin
        $this->assertSame(3, $preview['unreachable']); // no-login staff, no-login student, no-login guardian

        $published = $this->communications()->publish($communication->fresh(), $principal);

        $this->assertSame('published', $published->status);
        $this->assertSame(7, $published->recipients()->count());

        // Identities materialized correctly, not merged or mistyped.
        $this->assertSame(1, $published->recipients()->where('staff_id', $sarah->id)->count());
        $this->assertSame(1, $published->recipients()->where('student_id', $studentWithLogin->id)->count());
        $this->assertSame(1, $published->recipients()->where('student_id', $studentWithoutLogin->id)->count());
        $this->assertSame(1, $published->recipients()->where('guardian_id', $guardianWithLogin->id)->count());
        $this->assertSame(1, $published->recipients()->where('guardian_id', $guardianWithoutLogin->id)->count());

        $reachableCount = $published->recipients()->whereNotNull('resolved_user_id')->count();
        $this->assertSame(4, $reachableCount); // sarah, budi, studentWithLogin, guardianWithLogin

        // Notifications created ONLY for reachable recipients -- never one per
        // canonical recipient, and never for a recipient with no resolved
        // login. `data` is a plain text column (Laravel's own standard
        // notifications shape), so filter by decoded content in PHP rather
        // than a JSON-path WHERE -- Postgres' `->>` operator refuses a text
        // column outright, even though the same query is silently fine on
        // SQLite's dynamic typing. A prior version of this test used
        // `where('data->communication_id', ...)` and passed here on SQLite
        // while it would have thrown on Postgres.
        $notifiedUserIds = $published->recipients()->whereNotNull('resolved_user_id')->pluck('resolved_user_id');
        $notificationsForThisCommunication = \Illuminate\Notifications\DatabaseNotification::where('notifiable_type', \App\Models\User::class)
            ->whereIn('notifiable_id', $notifiedUserIds)
            ->get()
            ->filter(fn ($n) => ($n->data['communication_id'] ?? null) === $published->id);

        foreach ($notifiedUserIds as $userId) {
            $this->assertSame(1, $notificationsForThisCommunication->where('notifiable_id', $userId)->count());
        }
        $this->assertSame($reachableCount, $notificationsForThisCommunication->count());
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
