<?php

namespace Tests\Feature;

use App\Models\Communication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Feature\Concerns\BuildsCommunicationFixtures;
use Tests\TestCase;

/**
 * Draft -> published -> archived, and nothing else. Published is immutable,
 * with the ONE transition it may still make -- to archived -- carved out
 * explicitly in both the model guard and here.
 */
class CommunicationTest extends TestCase
{
    use BuildsCommunicationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCommunicationReferenceData();
    }

    public function test_a_communication_is_created_as_draft(): void
    {
        $principal = $this->principalUser();

        $communication = $this->communications()->createDraft($principal, [
            'display_sender' => 'Rahai School',
            'title' => 'Test',
            'body' => 'Body text.',
        ]);

        $this->assertTrue($communication->isDraft());
        $this->assertNull($communication->published_at);
        $this->assertSame('normal', $communication->priority);
    }

    public function test_draft_content_is_editable(): void
    {
        $principal = $this->principalUser();
        $communication = $this->communications()->createDraft($principal, [
            'display_sender' => 'Rahai School', 'title' => 'Old', 'body' => 'Old body.',
        ]);

        $updated = $this->communications()->updateDraft($communication, [
            'display_sender' => 'Rahai School', 'title' => 'New', 'body' => 'New body.', 'priority' => 'important',
        ]);

        $this->assertSame('New', $updated->title);
        $this->assertSame('important', $updated->priority);
    }

    public function test_a_draft_is_deletable(): void
    {
        $principal = $this->principalUser();
        $communication = $this->communications()->createDraft($principal, [
            'display_sender' => 'Rahai School', 'title' => 'Delete me', 'body' => 'Body.',
        ]);

        $this->communications()->deleteDraft($communication);

        $this->assertDatabaseMissing('communications', ['id' => $communication->id]);
    }

    public function test_a_published_communication_refuses_every_content_edit(): void
    {
        $principal = $this->principalUser();
        $communication = $this->publishedForEveryone($principal);

        $this->expectException(LogicException::class);
        $communication->update(['title' => 'hacked']);
    }

    public function test_a_published_communication_cannot_be_deleted(): void
    {
        $principal = $this->principalUser();
        $communication = $this->publishedForEveryone($principal);

        $this->expectException(LogicException::class);
        $communication->delete();
    }

    public function test_an_archived_communication_refuses_every_edit_including_reverting_status(): void
    {
        $principal = $this->principalUser();
        $communication = $this->publishedForEveryone($principal);
        $archived = $this->communications()->archive($communication, $principal);

        $this->expectException(LogicException::class);
        $archived->update(['status' => 'published']);
    }

    public function test_archive_preserves_recipients(): void
    {
        $principal = $this->principalUser();
        $this->teacherStaff('Sarah');
        $communication = $this->publishedForEveryone($principal);
        $countBefore = $communication->recipients()->count();

        $archived = $this->communications()->archive($communication, $principal);

        $this->assertSame($countBefore, $archived->recipients()->count());
    }

    public function test_published_at_is_required_once_published_by_database_check(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        \Illuminate\Support\Facades\DB::table('communications')->insert([
            'created_by_user_id' => $this->principalUser()->id,
            'display_sender' => 'X', 'title' => 'X', 'body' => 'X',
            'priority' => 'normal', 'status' => 'published', 'published_at' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_priority_is_constrained_to_the_three_recognised_values(): void
    {
        $this->expectException(ValidationException::class);

        $this->communications()->createDraft($this->principalUser(), [
            'display_sender' => 'Rahai School', 'title' => 'X', 'body' => 'X', 'priority' => 'critical',
        ]);
    }

    public function test_publish_refuses_an_audience_that_resolves_to_nobody(): void
    {
        $principal = $this->principalUser();
        $communication = $this->communications()->createDraft($principal, [
            'display_sender' => 'Rahai School', 'title' => 'Empty', 'body' => 'Body.',
        ]);
        $this->communications()->addAudienceRule($communication, $principal, ['rule_type' => 'all_staff']);

        $this->expectException(ValidationException::class);
        $this->communications()->publish($communication->fresh(), $principal);
    }

    public function test_expiry_does_not_delete_or_remove_from_history(): void
    {
        $principal = $this->principalUser();
        $this->teacherStaff('Recipient');
        $communication = $this->communications()->createDraft($principal, [
            'display_sender' => 'Rahai School', 'title' => 'Expiring', 'body' => 'Body.', 'expires_at' => '2020-01-01',
        ]);
        $this->communications()->addAudienceRule($communication, $principal, ['rule_type' => 'everyone']);
        $published = $this->communications()->publish($communication->fresh(), $principal);

        $this->assertNotNull($published->expires_at);
        $this->assertDatabaseHas('communications', ['id' => $published->id]);
        $this->assertSame($published->recipients()->count(), $published->fresh()->recipients()->count());
    }

    public function test_content_validation_refuses_an_empty_title(): void
    {
        $this->expectException(ValidationException::class);

        $this->communications()->createDraft($this->principalUser(), [
            'display_sender' => 'Rahai School', 'title' => '', 'body' => 'Body.',
        ]);
    }

    private function publishedForEveryone($actor): Communication
    {
        // "everyone" needs at least one live candidate to resolve to, or
        // publish() correctly refuses (see CommunicationTest::
        // test_publish_refuses_an_audience_that_resolves_to_nobody).
        $this->teacherStaff('Recipient');

        $communication = $this->communications()->createDraft($actor, [
            'display_sender' => 'Rahai School', 'title' => 'Announcement', 'body' => 'Body.',
        ]);
        $this->communications()->addAudienceRule($communication, $actor, ['rule_type' => 'everyone']);

        return $this->communications()->publish($communication->fresh(), $actor);
    }
}
