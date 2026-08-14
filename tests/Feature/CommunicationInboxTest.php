<?php

namespace Tests\Feature;

use App\Notifications\NewCommunicationPublished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\BuildsCommunicationFixtures;
use Tests\TestCase;

/**
 * Inbox access depends only on a materialized CommunicationRecipient row
 * resolving to the viewer's own login -- current Class/Group membership
 * grants nothing. read_at means "opened inside RSMS," nothing more. The
 * Laravel database Notification is a badge only: deleting it never touches
 * CommunicationRecipient, and it is created ONLY for recipients that
 * actually resolved a login.
 */
class CommunicationInboxTest extends TestCase
{
    use BuildsCommunicationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCommunicationReferenceData();
    }

    public function test_a_recipient_with_a_resolved_login_can_view_the_communication(): void
    {
        [$published, $sarahUser] = $this->publishToSarah();

        $this->assertTrue($sarahUser->can('view', $published));
    }

    public function test_an_unrelated_user_cannot_view_the_communication(): void
    {
        [$published] = $this->publishToSarah();
        $budi = $this->teacherUserFor('Budi');
        $this->teacherStaff('Budi');

        $this->assertFalse($budi->can('view', $published));
    }

    public function test_current_class_membership_alone_grants_no_access(): void
    {
        // Budi teaches the SAME class Sarah's Student notice was published
        // to -- his current teaching relationship to that class is real, but
        // he was never himself a recipient of THIS communication, and
        // co-teaching the class is not a door in.
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'Sarah');
        $sarah = $this->teacherUserFor('Sarah');
        $this->assignmentFor('Year 5A', 'Eng', 'Budi');
        $budiUser = $this->teacherUserFor('Budi');
        $student = $this->studentNamed('Evelyn', 'Wijaya', 'STU-1');
        $this->enroll($student, $assignment->schoolClass);

        $communication = $this->draft($sarah);
        $this->communications()->addAudienceRule($communication, $sarah, [
            'rule_type' => 'school_class_students', 'school_class_id' => $assignment->class_id,
        ]);
        $published = $this->communications()->publish($communication->fresh(), $sarah);

        $this->assertFalse($budiUser->can('view', $published));
    }

    public function test_read_at_starts_null(): void
    {
        [$published] = $this->publishToSarah();

        $recipient = $published->recipients()->first();

        $this->assertNull($recipient->read_at);
        $this->assertFalse($recipient->isRead());
    }

    public function test_opening_sets_read_at(): void
    {
        [$published, , $recipient] = $this->publishToSarah();

        $recipient->update(['read_at' => now()]);

        $this->assertTrue($recipient->fresh()->isRead());
    }

    public function test_archived_communications_remain_readable_by_their_recipients(): void
    {
        [$published, $sarahUser] = $this->publishToSarah();
        $principal = $this->principalUser();
        $archived = $this->communications()->archive($published, $principal);

        $this->assertTrue($sarahUser->can('view', $archived));
    }

    public function test_expired_communications_remain_in_history(): void
    {
        $principal = $this->principalUser();
        $staff = $this->teacherStaff('Sarah');
        $sarahUser = $staff->user->fresh();

        $communication = $this->communications()->createDraft($principal, [
            'display_sender' => 'Test', 'title' => 'Test', 'body' => 'Body.', 'expires_at' => '2020-01-01',
        ]);
        $this->communications()->addAudienceRule($communication, $principal, ['rule_type' => 'all_staff']);
        $published = $this->communications()->publish($communication->fresh(), $principal);

        $this->assertTrue($sarahUser->can('view', $published));
        $this->assertSame(1, $published->recipients()->count());
    }

    public function test_publish_creates_a_database_notification_only_for_reachable_recipients(): void
    {
        Notification::fake();

        $principal = $this->principalUser();
        $this->teacherStaff('Sarah'); // has a login
        \App\Models\Staff::create([
            'staff_number' => 'S-NOLOGIN', 'first_name' => 'No', 'last_name' => 'Login',
            'position_id' => \App\Models\Position::firstOrFail()->id, 'phone' => '08',
            'hire_date' => '2020-01-01', 'status' => 'active',
        ]);

        $communication = $this->draft($principal);
        $this->communications()->addAudienceRule($communication, $principal, ['rule_type' => 'all_staff']);
        $published = $this->communications()->publish($communication->fresh(), $principal);

        Notification::assertSentTimes(NewCommunicationPublished::class, 1);
    }

    public function test_notification_payload_references_the_communication(): void
    {
        [$published, $sarahUser] = $this->publishToSarah();

        $notification = $sarahUser->notifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame($published->id, $notification->data['communication_id']);
    }

    public function test_deleting_the_notification_does_not_delete_the_recipient_or_communication(): void
    {
        [$published, $sarahUser, $recipient] = $this->publishToSarah();

        $sarahUser->notifications()->delete();

        $this->assertDatabaseHas('communication_recipients', ['id' => $recipient->id]);
        $this->assertDatabaseHas('communications', ['id' => $published->id]);
    }

    public function test_a_recipient_with_no_resolvable_login_gets_no_notification_but_still_exists(): void
    {
        Notification::fake();

        $principal = $this->principalUser();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'Sarah');
        $student = $this->studentNamed('Evelyn', 'Wijaya', 'STU-1'); // no user_id
        $this->enroll($student, $assignment->schoolClass);

        $communication = $this->draft($principal);
        $this->communications()->addAudienceRule($communication, $principal, [
            'rule_type' => 'school_class_students', 'school_class_id' => $assignment->class_id,
        ]);
        $published = $this->communications()->publish($communication->fresh(), $principal);

        Notification::assertNothingSent();
        $this->assertSame(1, $published->recipients()->count());
    }

    private function draft($actor)
    {
        return $this->communications()->createDraft($actor, [
            'display_sender' => 'Test', 'title' => 'Test', 'body' => 'Body.',
        ]);
    }

    /** @return array{0: \App\Models\Communication, 1: \App\Models\User, 2: \App\Models\CommunicationRecipient} */
    private function publishToSarah(): array
    {
        $principal = $this->principalUser();
        $staff = $this->teacherStaff('Sarah');
        $sarahUser = $staff->user->fresh();

        $communication = $this->draft($principal);
        $this->communications()->addAudienceRule($communication, $principal, [
            'rule_type' => 'selected_staff', 'ids' => [$staff->id],
        ]);
        $published = $this->communications()->publish($communication->fresh(), $principal);

        return [$published, $sarahUser, $published->recipients()->first()];
    }
}
