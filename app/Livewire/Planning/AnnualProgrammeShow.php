<?php

namespace App\Livewire\Planning;

use App\Models\AcademicPeriod;
use App\Models\AnnualProgramme;
use App\Models\LearningPathwayItem;
use App\Models\SemesterProgramme;
use App\Services\AnnualProgrammeService;
use App\Services\SemesterProgrammeService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Program Tahunan / Annual Programme.
 *
 * Items are grouped by reporting period, because that is the question a Prota
 * exists to answer: what are we covering, and in which semester.
 */
#[Layout('layouts.app')]
class AnnualProgrammeShow extends Component
{
    public AnnualProgramme $annualProgramme;

    public bool $showAddItem = false;

    public string $learning_pathway_item_id = '';

    public string $academic_period_id = '';

    public string $planned_lesson_periods = '';

    public string $item_notes = '';

    public function mount(AnnualProgramme $annualProgramme): void
    {
        $this->authorize('view', $annualProgramme);
        $this->annualProgramme = $annualProgramme;
    }

    public function addItem(AnnualProgrammeService $programmes): void
    {
        $this->authorize('update', $this->annualProgramme);

        $validated = $this->validate([
            'learning_pathway_item_id' => ['required', 'exists:learning_pathway_items,id'],
            'academic_period_id' => ['required', 'exists:academic_periods,id'],
            'planned_lesson_periods' => ['nullable', 'integer', 'min:1'],
            'item_notes' => ['nullable', 'string'],
        ]);

        $programmes->addItem(
            $this->annualProgramme,
            LearningPathwayItem::findOrFail($validated['learning_pathway_item_id']),
            AcademicPeriod::findOrFail($validated['academic_period_id']),
            $validated['planned_lesson_periods'] !== '' && $validated['planned_lesson_periods'] !== null
                ? (int) $validated['planned_lesson_periods'] : null,
            $validated['item_notes'] !== '' ? $validated['item_notes'] : null,
        );

        $this->reset(['learning_pathway_item_id', 'academic_period_id', 'planned_lesson_periods', 'item_notes', 'showAddItem']);
        $this->annualProgramme->refresh();
    }

    public function removeItem(int $itemId, AnnualProgrammeService $programmes): void
    {
        $this->authorize('update', $this->annualProgramme);

        $programmes->removeItem($this->annualProgramme->items()->findOrFail($itemId));

        $this->annualProgramme->refresh();
    }

    public function activate(AnnualProgrammeService $programmes): void
    {
        $this->authorize('transition', $this->annualProgramme);

        $programmes->activate($this->annualProgramme);

        $this->annualProgramme->refresh();
    }

    public function archive(AnnualProgrammeService $programmes): void
    {
        $this->authorize('transition', $this->annualProgramme);

        $programmes->archive($this->annualProgramme);

        $this->annualProgramme->refresh();
    }

    /** Open the semester plan for a period, creating a draft if none exists. */
    public function planPeriod(int $periodId, SemesterProgrammeService $semesters)
    {
        $this->authorize('update', $this->annualProgramme);

        $existing = $this->annualProgramme->semesterProgrammes()
            ->where('academic_period_id', $periodId)->first();

        $programme = $existing ?? $semesters->create($this->annualProgramme, AcademicPeriod::findOrFail($periodId));

        return $this->redirect(route('planning.semester.show', $programme), navigate: true);
    }

    public function render(AnnualProgrammeService $programmes)
    {
        $vocabulary = $this->annualProgramme->curriculumScope->curriculum->vocabulary();

        return view('livewire.planning.annual-programme-show', [
            'vocabulary' => $vocabulary,
            'periods' => $this->annualProgramme->academicYear->periods,
            'itemsByPeriod' => $this->annualProgramme->items()
                ->with(['learningPathwayItem.learningObjective', 'academicPeriod', 'semesterItems'])
                ->get()
                ->sortBy(fn ($item) => [$item->academicPeriod->sequence, $item->learningPathwayItem->position])
                ->groupBy('academic_period_id'),
            'semesterProgrammes' => $this->annualProgramme->semesterProgrammes()
                ->get()->keyBy('academic_period_id'),
            // Only pathway items not already allocated.
            'addableItems' => $this->showAddItem
                ? $this->annualProgramme->learningPathway->items()->with('learningObjective')->get()
                    ->whereNotIn('id', $this->annualProgramme->items()->pluck('learning_pathway_item_id'))
                    ->values()
                : collect(),
            'canEdit' => auth()->user()->can('update', $this->annualProgramme),
            'canTransition' => auth()->user()->can('transition', $this->annualProgramme),
        ]);
    }
}
