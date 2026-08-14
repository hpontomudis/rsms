<?php

namespace Tests\Feature;

use App\Models\CommunicationRecipient;
use App\Notifications\NewCommunicationPublished;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\BuildsCommunicationFixtures;
use Tests\TestCase;

/**
 * CommunicationRecipient: canonical identity (exactly one of staff/guardian/
 * student/direct_user, enforced by a DB CHECK) vs resolved_user_id (the
 * login, if any, that can open it) are two different questions -- plus the
 * in-app inbox, read semantics, and the Notification badge layer that never
 * becomes the canonical record.
 */
class CommunicationRecipientTest extends TestCase
{
    use BuildsCommunicationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCommunicationReferenceData();
    }

    // ----------------------------------------------------- identity CHECK

    public function test_the_database_refuses_a_recipient_with_no_canonical_identity(): void
    {
        $this->expectException(QueryException::class);

        DB::table('communication_recipients')->insert([
            'communication_id' => $this->publishedStaffCommunication()->id,
            'recipient_name_snapshot' => 'Nobody', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_database_refuses_a_recipient_with_two_canonical_identities(): void
    {
        $communication = $this->publishedStaffCommunication();
        $staff = $this->teacherStaff('Extra');

        $this->expectException(QueryException::class);

        DB::table('communication_recipients')->insert([
            'communication_id' => $communication->id,
            'staff_id' => $staff->id, 'guardian_id' => 1,
            'recipient_name_snapshot' => 'Both', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_staff_recipient_is_materialized_correctly(): void
    {
        $communication = $this->publishedStaffCommunication();

        $recipient = $communication->recipients()->first();

        $this->assertNotNull($recipient->staff_id);
        $this->assertNull($recipient->guardian_id);
        $this->assertNull($recipient->student_id);
        $this->assertNull($recipient->direct_user_id);
    }

    public function test_a_guardian_recipient_is_materialized_correctly(): void
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
        $recipient = $published->recipients()->first();

        $this->assertSame($guardian->id, $recipient->guardian_id);
        $this->assertNull($recipient->staff_id);
    }

    public function test_a_student_recipient_is_materialized_correctly(): void
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
        $recipient = $published->recipients()->first();

        $this->assertSame($student->id, $recipient->student_id);
    }

    public function test_a_direct_user_recipient_is_materialized_correctly(): void
    {
        $principal = $this->principalUser();
        $management = $this->managementUser();

        $communication = $this->draft($principal);
        $this->communications()->addAudienceRule($communication, $principal, [
            'rule_type' => 'selected_users', 'ids' => [$management->id],
        ]);
        $published = $this->communications()->publish($communication->fresh(), $principal);
        $recipient = $published->recipients()->first();

        $this->assertSame($management->id, $recipient->direct_user_id);
        $this->assertSame($management->id, $recipient->resolved_user_id);
    }

    public function test_resolved_user_id_is_optional(): void
    {
        $principal = $this->principalUser();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'Sarah');
        $student = $this->studentNamed('Evelyn', 'Wijaya', 'STU-1'); // no user_id
        $this->enroll($student, $assignment->schoolClass);

        $communication = $this->draft($principal);
        $this->communications()->addAudienceRule($communication, $principal, [
            'rule_type' => 'school_class_students', 'school_class_id' => $assignment->class_id,
        ]);
        $published = $this->communications()->publish($communication->fresh(), $principal);
        $recipient = $published->recipients()->first();

        $this->assertNotNull($recipient->student_id);
        $this->assertNull($recipient->resolved_user_id);
        $this->assertFalse($recipient->isReachable());
    }

    public function test_an_ambiguous_login_yields_a_null_resolved_user_but_the_recipient_still_exists(): void
    {
        $principal = $this->principalUser();
        $sharedUser = \App\Models\User::factory()->create();
        $staffA = $this->teacherStaff('A');
        $staffA->update(['user_id' => $sharedUser->id]);
        $staffB = $this->teacherStaff('B');
        $staffB->update(['user_id' => $sharedUser->id]);

        $communication = $this->draft($principal);
        $this->communications()->addAudienceRule($communication, $principal, [
            'rule_type' => 'selected_staff', 'ids' => [$staffA->id],
        ]);
        $published = $this->communications()->publish($communication->fresh(), $principal);
        $recipient = $published->recipients()->first();

        $this->assertSame($staffA->id, $recipient->staff_id);
        $this->assertNull($recipient->resolved_user_id);
    }

    // ---------------------------------------------------------- reachability

    public function test_reachability_counts_are_correct_for_a_mixed_audience(): void
    {
        $principal = $this->principalUser();
        $this->teacherStaff('Sarah'); // has a user
        $unlinked = \App\Models\Staff::create([
            'staff_number' => 'S-UNLINKED', 'first_name' => 'No', 'last_name' => 'Login',
            'position_id' => \App\Models\Position::firstOrFail()->id, 'phone' => '08',
            'hire_date' => '2020-01-01', 'status' => 'active',
        ]);

        $communication = $this->draft($principal);
        $this->communications()->addAudienceRule($communication, $principal, ['rule_type' => 'all_staff']);
        $preview = $this->communications()->previewAudience($communication->fresh());

        $this->assertSame(2, $preview['resolved']);
        $this->assertSame(1, $preview['reachable']);
        $this->assertSame(1, $preview['unreachable']);
    }

    private function draft($actor)
    {
        return $this->communications()->createDraft($actor, [
            'display_sender' => 'Test', 'title' => 'Test', 'body' => 'Body.',
        ]);
    }

    private function publishedStaffCommunication()
    {
        $principal = $this->principalUser();
        $this->teacherStaff('Sarah');
        $communication = $this->draft($principal);
        $this->communications()->addAudienceRule($communication, $principal, ['rule_type' => 'all_staff']);

        return $this->communications()->publish($communication->fresh(), $principal);
    }
}
