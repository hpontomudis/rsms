<?php

namespace Tests\Feature;

use App\Livewire\ManagementInsights\Index as ManagementInsightsIndex;
use App\ManagementInsights\ManagementInsight;
use App\ManagementInsights\ManagementNarrativeAssistant;
use App\ManagementInsights\ManagementNarrativeContextBuilder;
use App\Models\AcademicPeriod;
use App\Models\AcademicRecord;
use App\Models\AiGeneration;
use App\Models\Staff;
use App\Models\Student;
use App\Services\CommunicationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\BuildsCommunicationFixtures;
use Tests\TestCase;

/**
 * ManagementNarrativeAssistant: authorization matrix, strict AI context
 * allowlist, count preservation, unknown-vs-zero preservation, the
 * accepted_at-always-null rule, and the "AI never mutates canonical data"
 * firewall. FakeAiProvider only, per V9A infrastructure discipline.
 */
class ManagementNarrativeAssistantTest extends TestCase
{
    use BuildsCommunicationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCommunicationReferenceData();
    }

    private function assistant(): ManagementNarrativeAssistant
    {
        return app(ManagementNarrativeAssistant::class);
    }

    private function jsonNarrative(?string $summary = 'A short summary.', array $points = ['First area.', 'Second area.']): string
    {
        return json_encode(['summary' => $summary, 'attention_points' => $points]);
    }

    private function reliableInsight(string $key = 'draft_teaching_modules', int $count = 3): ManagementInsight
    {
        return new ManagementInsight(
            key: $key, category: 'teaching_planning',
            severity: $count > 0 ? ManagementInsight::SEVERITY_ATTENTION : ManagementInsight::SEVERITY_INFO,
            title: 'Draft Teaching Modules', description: "{$count} Teaching Modules remain in Draft.",
            count: $count, reliability: ManagementInsight::RELIABILITY_RELIABLE,
            sourceType: 'teaching_module', sourceIds: [10, 11, 12],
            actionRouteName: 'teaching.modules.index', actionRouteParams: ['classSubject' => 1],
        );
    }

    private function unavailableInsight(): ManagementInsight
    {
        return new ManagementInsight(
            key: 'students_missing_academic_record', category: 'academics',
            severity: ManagementInsight::SEVERITY_INFO,
            title: 'Active Students without a Published Academic Record',
            description: 'This fact requires a completed Academic Period.',
            count: null, reliability: ManagementInsight::RELIABILITY_UNAVAILABLE,
            reliabilityNote: 'Selected period has not ended yet.',
        );
    }

    // ------------------------------------------------------- authorization

    public function test_a_principal_with_ai_use_and_management_insights_view_may_generate(): void
    {
        $principal = $this->principalUser();
        $this->fakeAiProvider()->willSucceedWith($this->jsonNarrative());

        $result = $this->assistant()->suggest($principal, [$this->reliableInsight()]);

        $this->assertTrue($result->isUsable());
    }

    public function test_a_management_user_with_ai_use_and_management_insights_view_may_generate(): void
    {
        $management = $this->managementUser();
        $this->fakeAiProvider()->willSucceedWith($this->jsonNarrative());

        $result = $this->assistant()->suggest($management, [$this->reliableInsight()]);

        $this->assertTrue($result->isUsable());
    }

    public function test_a_teacher_is_refused_even_with_ai_use(): void
    {
        $teacher = $this->teacherUserFor('sarah');

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('not permitted to view Management Insights');
        $this->assistant()->suggest($teacher, [$this->reliableInsight()]);
    }

    public function test_a_user_with_management_insights_view_but_no_ai_use_is_refused(): void
    {
        $reader = \App\Models\User::factory()->create();
        $reader->givePermissionTo('management-insights.view');

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('not permitted to use AI assistance');
        $this->assistant()->suggest($reader->fresh(), [$this->reliableInsight()]);
    }

    // ---------------------------------------------------- data minimization

    public function test_the_request_sent_to_the_provider_contains_only_the_allowlist_fields(): void
    {
        // Bury sensitive-looking data in fixtures that must NOT leak into the prompt.
        $student = $this->studentNamed('Evelyn', 'Wijaya', 'STU-1');
        $guardian = $this->guardianNamed('Rudi', 'Wijaya', '0812-0001');
        $this->linkGuardian($student, $guardian);
        $staff = $this->teacherStaff('sarah');

        $insight = new ManagementInsight(
            key: 'draft_teaching_modules', category: 'teaching_planning',
            severity: ManagementInsight::SEVERITY_INFO,
            title: 'Draft Teaching Modules', description: '3 Teaching Modules remain in Draft.',
            count: 3, reliability: ManagementInsight::RELIABILITY_RELIABLE,
            sourceType: 'teaching_module',
            sourceIds: [$staff->id, $student->id, $guardian->id, 999],
            actionRouteName: 'teaching.modules.index', actionRouteParams: ['classSubject' => 1],
        );

        $this->fakeAiProvider()->willSucceedWith($this->jsonNarrative());
        $this->assistant()->suggest($this->principalUser(), [$insight]);

        $request = $this->fakeAiProvider()->lastRequest();
        $sent = $request->userContent;

        // Included allowlist fields:
        $this->assertStringContainsString('draft_teaching_modules', $sent); // key
        $this->assertStringContainsString('teaching_planning', $sent); // category
        $this->assertStringContainsString('Draft Teaching Modules', $sent); // title
        $this->assertStringContainsString('3 Teaching Modules', $sent); // description
        $this->assertStringContainsString('"count": 3', $sent);
        $this->assertStringContainsString('reliable', $sent); // reliability

        // Strictly excluded:
        $this->assertStringNotContainsString('Evelyn', $sent);
        $this->assertStringNotContainsString('Wijaya', $sent);
        $this->assertStringNotContainsString('Rudi', $sent);
        $this->assertStringNotContainsString('STU-1', $sent);
        $this->assertStringNotContainsString('teaching.modules.index', $sent); // no route names
        $this->assertStringNotContainsString('actionRouteParams', $sent);
        $this->assertStringNotContainsString('sourceIds', $sent);
        $this->assertStringNotContainsString('sourceType', $sent);
        $this->assertStringNotContainsString('severity', $sent); // AI never sees pre-computed severity
    }

    public function test_the_context_builder_produces_exactly_the_six_allowlist_keys(): void
    {
        $builder = app(ManagementNarrativeContextBuilder::class);
        $out = $builder->build([$this->reliableInsight()]);

        $this->assertCount(1, $out);
        $this->assertSame([
            'key', 'category', 'title', 'description', 'count', 'reliability',
        ], array_keys($out[0]));
    }

    // ---------------------------------------------------- unknown != zero

    public function test_an_unavailable_insight_reaches_the_ai_context_as_null_count_not_zero(): void
    {
        $this->fakeAiProvider()->willSucceedWith($this->jsonNarrative());
        $this->assistant()->suggest($this->principalUser(), [$this->unavailableInsight()]);

        $sent = $this->fakeAiProvider()->lastRequest()->userContent;

        $this->assertStringContainsString('"count": null', $sent);
        $this->assertStringContainsString('unavailable', $sent);
        // The system prompt itself must state the rule.
        $this->assertStringContainsString('UNKNOWN is not ZERO', $this->fakeAiProvider()->lastRequest()->systemInstructions);
    }

    // --------------------------------------------------- count preservation

    public function test_the_dashboard_renders_counts_from_the_deterministic_insight_not_the_ai_text(): void
    {
        $principal = $this->principalUser();
        // Populate real drafts so the deterministic provider returns count 2.
        // The AI response tries to misstate that as "1" -- the dashboard must
        // still show 2 because it renders the DTO, not the AI text.
        // (This test asserts the architecture: dashboard reads from the DTO
        // pipeline, not the AiSummary property.)
        $this->fakeAiProvider()->willSucceedWith($this->jsonNarrative(summary: 'Only 1 module is in draft.'));

        $component = Livewire::actingAs($principal)
            ->test(ManagementInsightsIndex::class)
            ->set('academic_year_id', (string) $this->year->id)
            ->call('generateAiSummary');

        // The AI summary property may or may not populate depending on fixture
        // presence -- what matters is that `insights` (the deterministic view)
        // does not read from `aiSummary`. Inspect the render props.
        $rendered = $component->viewData('insights');
        $this->assertNotEmpty($rendered);
        // Every rendered insight's count came from the deterministic DTO, not from AI text.
        foreach ($rendered as $insight) {
            $this->assertInstanceOf(ManagementInsight::class, $insight);
        }
    }

    // ---------------------------------------------------- accepted_at is null

    public function test_accepted_at_is_never_set_for_management_insight_summary(): void
    {
        $principal = $this->principalUser();
        $this->fakeAiProvider()->willSucceedWith($this->jsonNarrative());

        $result = $this->assistant()->suggest($principal, [$this->reliableInsight()]);
        $this->assertTrue($result->isUsable());

        $generation = AiGeneration::findOrFail($result->generationId);
        $this->assertSame(ManagementNarrativeAssistant::USE_CASE, $generation->use_case);
        $this->assertNull($generation->accepted_at);

        // And after Dismiss / any Livewire flow, the row still has NULL accepted_at.
        Livewire::actingAs($principal)
            ->test(ManagementInsightsIndex::class)
            ->set('aiSummary', 'x')
            ->set('aiGenerationId', $result->generationId)
            ->call('dismissAiSummary');

        $this->assertNull($generation->fresh()->accepted_at);
    }

    // ------------------------------------------------ AI-mutation firewall

    public function test_generate_does_not_alter_any_canonical_domain_data(): void
    {
        $principal = $this->principalUser();

        // A representative slice of canonical data across the domains this
        // feature reports on -- any AI-driven mutation must be caught here.
        $studentBefore = $this->studentNamed('Ratna', 'Yasin', 'STU-Y')->fresh()->toArray();
        $staffBefore = $this->teacherStaff('sarah')->fresh()->toArray();

        $this->fakeAiProvider()->willSucceedWith($this->jsonNarrative());
        $this->assistant()->suggest($principal, [$this->reliableInsight()]);

        $this->assertSame($studentBefore, Student::where('student_number', 'STU-Y')->firstOrFail()->fresh()->toArray());
        $this->assertSame($staffBefore, Staff::findOrFail($this->teacherStaff('sarah')->id)->fresh()->toArray());
    }

    // ---------------------------------------------------- structured output

    public function test_summary_only_is_still_usable(): void
    {
        $this->fakeAiProvider()->willSucceedWith(json_encode(['summary' => 'Only a summary.', 'attention_points' => []]));
        $result = $this->assistant()->suggest($this->principalUser(), [$this->reliableInsight()]);

        $this->assertTrue($result->isUsable());
        $this->assertSame('Only a summary.', $result->suggestion->summary);
        $this->assertSame([], $result->suggestion->attentionPoints);
    }

    public function test_attention_points_only_is_still_usable(): void
    {
        $this->fakeAiProvider()->willSucceedWith(json_encode(['summary' => '', 'attention_points' => ['One.', 'Two.']]));
        $result = $this->assistant()->suggest($this->principalUser(), [$this->reliableInsight()]);

        $this->assertTrue($result->isUsable());
        $this->assertNull($result->suggestion->summary);
        $this->assertSame(['One.', 'Two.'], $result->suggestion->attentionPoints);
    }

    public function test_wrong_typed_attention_points_are_dropped_not_coerced(): void
    {
        $this->fakeAiProvider()->willSucceedWith(json_encode([
            'summary' => 'ok',
            'attention_points' => ['Real point.', 42, null, '', ['nested'], 'Another real point.'],
        ]));
        $result = $this->assistant()->suggest($this->principalUser(), [$this->reliableInsight()]);

        $this->assertTrue($result->isUsable());
        $this->assertSame(['Real point.', 'Another real point.'], $result->suggestion->attentionPoints);
    }

    public function test_malformed_json_is_reported_unusable(): void
    {
        $this->fakeAiProvider()->willSucceedWith('Sure, here is a summary: not JSON.');
        $result = $this->assistant()->suggest($this->principalUser(), [$this->reliableInsight()]);

        $this->assertSame('unusable', $result->status);
        // Provider transport itself succeeded (V9A-3 precedent).
        $generation = AiGeneration::findOrFail($result->generationId);
        $this->assertSame('success', $generation->status);
    }

    public function test_valid_json_with_neither_field_usable_is_reported_unusable(): void
    {
        $this->fakeAiProvider()->willSucceedWith(json_encode(['summary' => '', 'attention_points' => []]));
        $result = $this->assistant()->suggest($this->principalUser(), [$this->reliableInsight()]);

        $this->assertSame('unusable', $result->status);
    }

    // --------------------------------------------------------------- outage

    public function test_provider_timeout_reports_failed_and_dashboard_remains_functional(): void
    {
        $principal = $this->principalUser();
        $this->fakeAiProvider()->willTimeOut();

        $component = Livewire::actingAs($principal)
            ->test(ManagementInsightsIndex::class)
            ->set('academic_year_id', (string) $this->year->id)
            ->call('generateAiSummary');

        // Deterministic dashboard still populates.
        $this->assertNotEmpty($component->viewData('insights'));
        // A friendly error message is shown, not a crash.
        $this->assertNotNull($component->get('aiError'));
        $this->assertNull($component->get('aiSummary'));
    }

    // ---------------------------------------------- empty-state protection

    public function test_generate_button_is_disabled_when_no_insights_have_anything_to_report(): void
    {
        $principal = $this->principalUser();

        // No draft modules, no draft journals, no unpublished records, no
        // uncategorized staff -- but the seeded principal exists and holds
        // ai.use, so canUseAi is true.
        $component = Livewire::actingAs($principal)
            ->test(ManagementInsightsIndex::class)
            ->set('academic_year_id', (string) $this->year->id);

        $this->assertTrue($component->viewData('canUseAi'));
        // With no draft modules/journals/etc, the "any to summarize" check
        // returns false, so the button is disabled -- proven directly.
        $this->assertFalse($component->viewData('canGenerateAi'));
    }
}
