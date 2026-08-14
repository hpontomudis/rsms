<?php

namespace App\Livewire\Performance\Evaluations;

use App\Models\PerformanceEvaluation;
use App\Models\PerformanceEvidence;
use App\Models\PerformanceRatingOption;
use App\Services\PerformanceEvaluationItemService;
use App\Services\PerformanceEvaluationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Draft workspace and finalized read view, in one component -- which branch
 * renders is decided by $evaluation->status, exactly the way Curricula\Show
 * renders one page across a lifecycle rather than splitting per status.
 *
 * A performance.view holder (management, read-only) reaches this page on a
 * DRAFT evaluation too; $canEdit (not the route) is what hides every form
 * control from them. Self-view can only ever land here on a FINALIZED
 * evaluation -- PerformanceEvaluationPolicy::view() already refuses a draft
 * to anyone without performance.view/manage.
 */
#[Layout('layouts.app')]
class Show extends Component
{
    public PerformanceEvaluation $evaluation;

    /** @var array<int, array{value: string, evaluator_comment: string}> */
    public array $responses = [];

    public ?int $addEvidenceItemId = null;

    public string $evidence_label = '';

    public string $evidence_note = '';

    public string $overall_rating_option_id = '';

    public string $summary = '';

    public string $strengths = '';

    public string $development_priorities = '';

    public string $action_plan = '';

    public string $review_date = '';

    public function mount(PerformanceEvaluation $evaluation): void
    {
        $this->authorize('view', $evaluation);
        $this->evaluation = $evaluation;

        if (! $evaluation->isDraft()) {
            return;
        }

        foreach ($evaluation->items()->with('indicator')->get() as $item) {
            $value = match ($item->indicator->indicator_type) {
                'rubric' => $item->rating_option_id !== null ? (string) $item->rating_option_id : '',
                'numeric' => $item->numeric_value !== null ? (string) $item->numeric_value : '',
                'boolean' => $item->boolean_value === null ? '' : ($item->boolean_value ? '1' : '0'),
                'narrative' => $item->narrative_response ?? '',
            };

            $this->responses[$item->id] = [
                'value' => $value,
                'evaluator_comment' => $item->evaluator_comment ?? '',
            ];
        }

        $this->overall_rating_option_id = $evaluation->overall_rating_option_id !== null
            ? (string) $evaluation->overall_rating_option_id : '';
        $this->summary = $evaluation->summary ?? '';
        $this->strengths = $evaluation->strengths ?? '';
        $this->development_priorities = $evaluation->development_priorities ?? '';
        $this->action_plan = $evaluation->action_plan ?? '';
        $this->review_date = $evaluation->review_date?->toDateString() ?? '';
    }

    // -------------------------------------------------------------- items

    public function respond(int $itemId, PerformanceEvaluationItemService $items): void
    {
        $this->authorize('update', $this->evaluation);

        $item = $this->evaluation->items()->with('indicator')->findOrFail($itemId);
        $field = match ($item->indicator->indicator_type) {
            'rubric' => 'rating_option_id',
            'numeric' => 'numeric_value',
            'boolean' => 'boolean_value',
            'narrative' => 'narrative_response',
        };

        $value = $this->responses[$itemId]['value'] ?? '';

        $items->respond($item, [
            $field => $value !== '' ? $value : null,
            'evaluator_comment' => $this->responses[$itemId]['evaluator_comment'] ?? null,
        ]);

        $this->evaluation->refresh();
    }

    public function startAddEvidence(int $itemId): void
    {
        $this->authorize('update', $this->evaluation);

        $this->reset(['evidence_label', 'evidence_note']);
        $this->addEvidenceItemId = $itemId;
        $this->resetErrorBag();
    }

    public function cancelAddEvidence(): void
    {
        $this->reset(['addEvidenceItemId', 'evidence_label', 'evidence_note']);
    }

    public function saveEvidence(PerformanceEvaluationItemService $items): void
    {
        $this->authorize('update', $this->evaluation);

        $validated = $this->validate([
            'evidence_label' => ['required', 'string', 'max:150'],
            'evidence_note' => ['required', 'string', 'max:2000'],
        ]);

        $item = $this->evaluation->items()->findOrFail($this->addEvidenceItemId);

        $items->addManualEvidence($item, $validated['evidence_label'], $validated['evidence_note']);

        $this->cancelAddEvidence();
        $this->evaluation->refresh();
    }

    public function removeEvidence(int $evidenceId, PerformanceEvaluationItemService $items): void
    {
        $this->authorize('update', $this->evaluation);

        $evidence = PerformanceEvidence::whereHas(
            'item', fn ($q) => $q->where('performance_evaluation_id', $this->evaluation->id)
        )->findOrFail($evidenceId);

        $items->removeManualEvidence($evidence);

        $this->evaluation->refresh();
    }

    // ------------------------------------------------------------ overall

    public function setOverallRating(PerformanceEvaluationService $evaluations): void
    {
        $this->authorize('update', $this->evaluation);

        $validated = $this->validate([
            'overall_rating_option_id' => ['nullable', 'exists:performance_rating_options,id'],
        ]);

        $option = $validated['overall_rating_option_id'] !== ''
            ? PerformanceRatingOption::findOrFail($validated['overall_rating_option_id']) : null;

        $evaluations->setOverallRating($this->evaluation, $option);

        $this->evaluation->refresh();
    }

    public function saveDevelopmentPlan(PerformanceEvaluationService $evaluations): void
    {
        $this->authorize('update', $this->evaluation);

        $validated = $this->validate([
            'summary' => ['nullable', 'string', 'max:2000'],
            'strengths' => ['nullable', 'string', 'max:2000'],
            'development_priorities' => ['nullable', 'string', 'max:2000'],
            'action_plan' => ['nullable', 'string', 'max:2000'],
            'review_date' => ['nullable', 'date'],
        ]);

        $evaluations->updateDraft($this->evaluation, $validated);

        $this->evaluation->refresh();
    }

    // ---------------------------------------------------------- lifecycle

    public function finalize(PerformanceEvaluationService $evaluations): void
    {
        $this->authorize('finalize', $this->evaluation);

        $evaluations->finalize($this->evaluation, Auth::user());

        $this->evaluation->refresh();
        session()->flash('status', 'Evaluation finalized.');
    }

    public function deleteDraft(PerformanceEvaluationService $evaluations)
    {
        $this->authorize('delete', $this->evaluation);

        $evaluations->deleteDraft($this->evaluation);

        session()->flash('status', 'Draft evaluation deleted.');

        return $this->redirect(route('performance.evaluations.index'), navigate: true);
    }

    public function render(PerformanceEvaluationService $evaluations)
    {
        $preview = $this->evaluation->isDraft() ? $evaluations->preview($this->evaluation) : null;

        $finalizedItems = $this->evaluation->isFinalized()
            ? $this->evaluation->items()->with(['evidence' => fn ($q) => $q->orderBy('id')])->get()
                ->sortBy(fn ($item) => [$item->section_position_snapshot, $item->indicator_position_snapshot])
            : null;

        return view('livewire.performance.evaluations.show', [
            'preview' => $preview,
            'finalizedItems' => $finalizedItems,
            'ratingOptions' => $this->evaluation->framework->ratingOptions()->get(),
            'canEdit' => Auth::user()->can('update', $this->evaluation),
            'canFinalize' => Auth::user()->can('finalize', $this->evaluation),
            'canDelete' => Auth::user()->can('delete', $this->evaluation),
        ]);
    }
}
