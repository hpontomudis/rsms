<?php

namespace App\Livewire\Planning;

use App\Models\AnnualProgrammeItem;
use App\Models\SemesterProgramme;
use App\Services\SemesterProgrammeService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Program Semester / Semester Programme.
 *
 * Shows the period's schedule as an ordered list of slots. One allocation may
 * legitimately appear several times -- the JP summary makes it obvious whether
 * the slots add up to the annual budget before anyone tries to activate.
 */
#[Layout('layouts.app')]
class SemesterProgrammeShow extends Component
{
    public SemesterProgramme $semesterProgramme;

    public bool $showAddSlot = false;

    public string $annual_programme_item_id = '';

    public string $week_label = '';

    public string $planned_start_date = '';

    public string $planned_end_date = '';

    public string $planned_lesson_periods = '';

    public string $slot_notes = '';

    public ?int $editingSlotId = null;

    public function mount(SemesterProgramme $semesterProgramme): void
    {
        $this->authorize('view', $semesterProgramme);
        $this->semesterProgramme = $semesterProgramme;
    }

    private function slotAttributes(array $validated): array
    {
        return [
            'week_label' => $validated['week_label'],
            'planned_start_date' => $validated['planned_start_date'],
            'planned_end_date' => $validated['planned_end_date'],
            'planned_lesson_periods' => $validated['planned_lesson_periods'] !== '' && $validated['planned_lesson_periods'] !== null
                ? (int) $validated['planned_lesson_periods'] : null,
            'notes' => $validated['slot_notes'],
        ];
    }

    private function slotRules(): array
    {
        return [
            'week_label' => ['nullable', 'string', 'max:100'],
            'planned_start_date' => ['nullable', 'date'],
            'planned_end_date' => ['nullable', 'date'],
            'planned_lesson_periods' => ['nullable', 'integer', 'min:1'],
            'slot_notes' => ['nullable', 'string'],
        ];
    }

    public function addSlot(SemesterProgrammeService $semesters): void
    {
        $this->authorize('update', $this->semesterProgramme);

        $validated = $this->validate($this->slotRules() + [
            'annual_programme_item_id' => ['required', 'exists:annual_programme_items,id'],
        ]);

        $semesters->addSlot(
            $this->semesterProgramme,
            AnnualProgrammeItem::findOrFail($validated['annual_programme_item_id']),
            $this->slotAttributes($validated),
        );

        $this->cancel();
        $this->semesterProgramme->refresh();
    }

    public function startEditing(int $slotId): void
    {
        $this->authorize('update', $this->semesterProgramme);

        $slot = $this->slot($slotId);
        $this->editingSlotId = $slot->id;
        $this->showAddSlot = false;
        $this->week_label = $slot->week_label ?? '';
        $this->planned_start_date = $slot->planned_start_date?->toDateString() ?? '';
        $this->planned_end_date = $slot->planned_end_date?->toDateString() ?? '';
        $this->planned_lesson_periods = (string) ($slot->planned_lesson_periods ?? '');
        $this->slot_notes = $slot->notes ?? '';
        $this->resetErrorBag();
    }

    public function saveSlot(SemesterProgrammeService $semesters): void
    {
        $this->authorize('update', $this->semesterProgramme);

        $validated = $this->validate($this->slotRules());

        $semesters->updateSlot($this->slot($this->editingSlotId), $this->slotAttributes($validated));

        $this->cancel();
        $this->semesterProgramme->refresh();
    }

    public function removeSlot(int $slotId, SemesterProgrammeService $semesters): void
    {
        $this->authorize('update', $this->semesterProgramme);

        $semesters->removeSlot($this->slot($slotId));

        $this->semesterProgramme->refresh();
    }

    public function moveSlot(int $slotId, string $direction, SemesterProgrammeService $semesters): void
    {
        $this->authorize('update', $this->semesterProgramme);

        $semesters->moveSlot($this->slot($slotId), $direction);

        $this->semesterProgramme->refresh();
    }

    public function activate(SemesterProgrammeService $semesters): void
    {
        $this->authorize('transition', $this->semesterProgramme);

        $semesters->activate($this->semesterProgramme);

        $this->cancel();
        $this->semesterProgramme->refresh();
    }

    public function archive(SemesterProgrammeService $semesters): void
    {
        $this->authorize('transition', $this->semesterProgramme);

        $semesters->archive($this->semesterProgramme);

        $this->cancel();
        $this->semesterProgramme->refresh();
    }

    public function cancel(): void
    {
        $this->reset([
            'showAddSlot', 'editingSlotId', 'annual_programme_item_id', 'week_label',
            'planned_start_date', 'planned_end_date', 'planned_lesson_periods', 'slot_notes',
        ]);
        $this->resetErrorBag();
    }

    private function slot(int $id)
    {
        return $this->semesterProgramme->items()->findOrFail($id);
    }

    public function render()
    {
        $annual = $this->semesterProgramme->annualProgramme;

        $allocated = $annual->items()
            ->where('academic_period_id', $this->semesterProgramme->academic_period_id)
            ->with('learningPathwayItem.learningObjective')
            ->get()
            ->sortBy(fn ($item) => $item->learningPathwayItem->position)
            ->values();

        $slots = $this->semesterProgramme->items()->with('annualProgrammeItem.learningPathwayItem.learningObjective')->get();
        $scheduled = $slots->groupBy('annual_programme_item_id');

        return view('livewire.planning.semester-programme-show', [
            'annual' => $annual,
            'vocabulary' => $annual->curriculumScope->curriculum->vocabulary(),
            'allocated' => $allocated,
            // NOT `slots`: Blade defines its own $slots (the named-slot bag) in
            // a component view, and it silently wins over view data.
            'scheduleSlots' => $slots,
            // Per-allocation JP summary, so the reconciliation rule is visible
            // before activation rather than only on rejection.
            'summary' => $allocated->mapWithKeys(function ($item) use ($scheduled) {
                $itemSlots = $scheduled->get($item->id, collect());

                return [$item->id => (object) [
                    'slots' => $itemSlots->count(),
                    'budget' => $item->planned_lesson_periods,
                    'scheduled' => $itemSlots->contains(fn ($s) => $s->planned_lesson_periods === null)
                        ? null : (int) $itemSlots->sum('planned_lesson_periods'),
                ]];
            }),
            'canEdit' => auth()->user()->can('update', $this->semesterProgramme),
            'canTransition' => auth()->user()->can('transition', $this->semesterProgramme),
        ]);
    }
}
