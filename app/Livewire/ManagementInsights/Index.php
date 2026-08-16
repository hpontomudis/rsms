<?php

namespace App\Livewire\ManagementInsights;

use App\ManagementInsights\ManagementInsightScope;
use App\ManagementInsights\ManagementInsightService;
use App\ManagementInsights\ManagementNarrativeAssistant;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The deterministic Management Insights dashboard, always fully rendered
 * without any AI dependency. The optional narrative summary is generated
 * only by an explicit user click, never on mount/render/filter change --
 * matching the discipline used everywhere else in V9A.
 *
 * The AcademicYear/AcademicPeriod filter resolves the scope ONCE at this
 * UI boundary and passes it explicitly to the service. No provider
 * silently re-resolves "current" (see ManagementInsightScope docblock).
 */
#[Layout('layouts.app')]
class Index extends Component
{
    public string $academic_year_id = '';

    public string $academic_period_id = '';

    public ?string $aiSummary = null;

    /** @var array<int, string> */
    public array $aiAttentionPoints = [];

    public ?int $aiGenerationId = null;

    public ?string $aiError = null;

    public function mount(): void
    {
        abort_unless(Auth::user()?->can('management-insights.view'), 403);

        // Default the filter to whichever year happens to hold is_current=true
        // (a UI-only convenience; providers never see this fallback -- the scope
        // DTO's academicYear is always the explicitly-resolved model).
        $current = AcademicYear::where('is_current', true)->orderBy('id')->first()
            ?? AcademicYear::orderByDesc('start_date')->first();
        $this->academic_year_id = (string) ($current?->id ?? '');
    }

    public function updatedAcademicYearId(): void
    {
        $this->academic_period_id = '';
        $this->resetAiState();
    }

    public function updatedAcademicPeriodId(): void
    {
        $this->resetAiState();
    }

    public function generateAiSummary(ManagementNarrativeAssistant $assistant): void
    {
        abort_unless(Auth::user()?->can('management-insights.view'), 403);
        $this->resetAiState();

        $scope = $this->currentScope();
        if ($scope === null) {
            $this->aiError = 'Pick an Academic Year first.';

            return;
        }

        $insights = app(ManagementInsightService::class)->all($scope);

        // An empty (or all-zero) insight list produces no useful summary.
        // "Nothing to report" (per architecture review §66) means every
        // insight has count === 0. Unavailable insights (count === null) are
        // dashboard-state hints, not actionable facts worth an AI narrative.
        $anyToSummarize = collect($insights)->contains(fn ($i) => $i->count !== null && $i->count > 0);
        if (! $anyToSummarize) {
            $this->aiError = 'No current insights to summarize.';

            return;
        }

        try {
            $result = $assistant->suggest(Auth::user(), $insights);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->aiError = $e->getMessage();

            return;
        }

        if ($result->isUsable()) {
            $this->aiSummary = $result->suggestion->summary;
            $this->aiAttentionPoints = $result->suggestion->attentionPoints;
            $this->aiGenerationId = $result->generationId;

            return;
        }

        $this->aiError = match ($result->status) {
            'rate_limited' => 'You have reached the AI assistance limit for now. The verified management insights are still available below.',
            'unusable' => "AI assistance couldn't produce a usable summary this time. The verified management insights are still available below.",
            default => 'AI summary is unavailable. The verified management insights are still available below.',
        };
    }

    public function dismissAiSummary(): void
    {
        $this->resetAiState();
    }

    private function resetAiState(): void
    {
        $this->reset(['aiSummary', 'aiAttentionPoints', 'aiGenerationId', 'aiError']);
    }

    private function currentScope(): ?ManagementInsightScope
    {
        $year = $this->academic_year_id !== '' ? AcademicYear::find($this->academic_year_id) : null;
        if ($year === null) {
            return null;
        }
        $period = $this->academic_period_id !== '' ? AcademicPeriod::find($this->academic_period_id) : null;

        return new ManagementInsightScope($year, $period, Auth::user());
    }

    public function render()
    {
        $user = Auth::user();
        $canUseAi = config('ai.enabled') && $user?->can('ai.use') && $user?->can('management-insights.view');

        $scope = $this->currentScope();
        $insights = $scope === null ? [] : app(ManagementInsightService::class)->all($scope);
        // "Nothing to report" (per architecture review §66) means every
        // insight has count === 0. Unavailable insights (count === null) are
        // dashboard-state hints, not actionable facts worth an AI narrative.
        $anyToSummarize = collect($insights)->contains(fn ($i) => $i->count !== null && $i->count > 0);

        return view('livewire.management-insights.index', [
            'insights' => $insights,
            'years' => AcademicYear::orderByDesc('start_date')->get(),
            'periods' => $scope
                ? AcademicPeriod::where('academic_year_id', $scope->academicYear->id)->orderBy('sequence')->get()
                : collect(),
            'canUseAi' => (bool) $canUseAi,
            'canGenerateAi' => (bool) $canUseAi && $anyToSummarize,
        ]);
    }
}
