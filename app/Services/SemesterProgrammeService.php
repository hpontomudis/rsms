<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\AnnualProgramme;
use App\Models\AnnualProgrammeItem;
use App\Models\SemesterProgramme;
use App\Models\SemesterProgrammeItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Program Semester: when, inside one reporting period, the items the annual
 * programme allocated to that period will actually be taught.
 *
 * One annual item may need SEVERAL slots -- an 8 JP objective taught in week 3
 * (2 JP), week 4 (2 JP) and week 6 (4 JP) is three rows sharing one budget.
 * That is why there is no uniqueness rule on (programme, annual item).
 *
 * POSITION CONTIGUITY IS APPLICATION-LEVEL, as it is for pathway items: a
 * partial unique index would have to know the parent's status, which SQL
 * cannot read from an index predicate. Every write path below re-normalises
 * to 1..n in the same transaction, and activation re-validates.
 *
 * THE ACTIVE INVARIANT. Because an active plan stays editable, completeness
 * and JP reconciliation cannot be checked only at activation -- a later edit
 * could quietly falsify them. Every mutation therefore runs inside a
 * transaction and re-asserts `assertPlanIsComplete()` against the *resulting*
 * database state; a violation throws and the transaction rolls back. The state
 * is re-read rather than simulated, so there is no second model of the rules to
 * drift out of step with the first. A DRAFT plan is exempt: it is allowed to be
 * incomplete while it is being prepared, and activation is the gate.
 */
class SemesterProgrammeService
{
    public function create(AnnualProgramme $programme, AcademicPeriod $period, ?string $notes = null): SemesterProgramme
    {
        if ($programme->isArchived()) {
            $this->fail('annual_programme_id', 'An archived annual programme cannot take new semester planning.');
        }

        if ($period->academic_year_id !== $programme->academic_year_id) {
            $this->fail('academic_period_id', "{$period->name} belongs to a different academic year.");
        }

        if ($programme->semesterProgrammes()->where('academic_period_id', $period->id)->exists()) {
            $this->fail('academic_period_id', "{$programme->rosterName()} already has a {$period->name} programme.");
        }

        if (! $programme->items()->where('academic_period_id', $period->id)->exists()) {
            $this->fail('academic_period_id', "The annual programme has nothing allocated to {$period->name} yet.");
        }

        return SemesterProgramme::create([
            'annual_programme_id' => $programme->id,
            'academic_period_id' => $period->id,
            'academic_year_id' => $programme->academic_year_id,
            'notes' => $notes,
            'status' => 'draft',
        ]);
    }

    /**
     * Add a scheduling slot. The same annual item may be scheduled repeatedly.
     */
    public function addSlot(SemesterProgramme $programme, AnnualProgrammeItem $item, array $attributes = []): SemesterProgrammeItem
    {
        $this->assertEditable($programme);

        if ($item->annual_programme_id !== $programme->annual_programme_id
            || $item->academic_period_id !== $programme->academic_period_id) {
            $this->fail('annual_programme_item_id', 'That item was not allocated to this period by the annual programme.');
        }

        [$start, $end] = $this->validateDates($programme, $attributes);
        $this->assertPositiveBudget($attributes['planned_lesson_periods'] ?? null);

        return DB::transaction(function () use ($programme, $item, $attributes, $start, $end) {
            $slot = SemesterProgrammeItem::create([
                'semester_programme_id' => $programme->id,
                'annual_programme_item_id' => $item->id,
                // Mirrored so the composite keys can verify the context.
                'annual_programme_id' => $programme->annual_programme_id,
                'academic_period_id' => $programme->academic_period_id,
                'position' => $this->nextPosition($programme),
                'week_label' => ($attributes['week_label'] ?? '') !== '' ? $attributes['week_label'] : null,
                'planned_start_date' => $start,
                'planned_end_date' => $end,
                'planned_lesson_periods' => $attributes['planned_lesson_periods'] ?? null,
                'notes' => ($attributes['notes'] ?? '') !== '' ? $attributes['notes'] : null,
            ]);

            $this->normalise($programme);
            $this->guardActive($programme);

            return $slot->refresh();
        });
    }

    public function updateSlot(SemesterProgrammeItem $slot, array $attributes): SemesterProgrammeItem
    {
        $programme = $slot->semesterProgramme;
        $this->assertEditable($programme);

        [$start, $end] = $this->validateDates($programme, $attributes);
        $this->assertPositiveBudget($attributes['planned_lesson_periods'] ?? null);

        return DB::transaction(function () use ($programme, $slot, $attributes, $start, $end) {
            $slot->update([
                'week_label' => ($attributes['week_label'] ?? '') !== '' ? $attributes['week_label'] : null,
                'planned_start_date' => $start,
                'planned_end_date' => $end,
                'planned_lesson_periods' => $attributes['planned_lesson_periods'] ?? null,
                'notes' => ($attributes['notes'] ?? '') !== '' ? $attributes['notes'] : null,
            ]);

            $this->guardActive($programme);

            return $slot->refresh();
        });
    }

    public function removeSlot(SemesterProgrammeItem $slot): void
    {
        $programme = $slot->semesterProgramme;
        $this->assertEditable($programme);

        DB::transaction(function () use ($programme, $slot) {
            $slot->delete();
            $this->normalise($programme);
            $this->guardActive($programme);
        });
    }

    /**
     * Restate one objective's whole allocation at once: its annual budget and
     * the distribution of that budget across ALL of its slots.
     *
     * This exists because sequential editing cannot get there. Moving an active
     * 2+2+4 to 3+1+4 is refused at the first write, since the intermediate
     * 3+2+4 does not reconcile; and raising a budget from 8 to 10 is impossible
     * from either side alone -- change the slots and they disagree with 8,
     * change the budget and it disagrees with the slots. Both facts have to
     * move together or neither can.
     *
     * So one transaction writes the budget and every slot, and the invariant is
     * re-asserted over the result. No revision subsystem, no version history --
     * just the smallest operation that never leaves an invalid state visible.
     *
     * The caller must state EVERY slot of the item. A partial map is rejected
     * rather than treated as "leave the rest alone", because the point of the
     * operation is that the total is stated deliberately.
     *
     * @param  array<int, int|null>  $lessonPeriodsBySlotId
     */
    public function rebalance(AnnualProgrammeItem $item, array $lessonPeriodsBySlotId, ?int $budget = null, bool $touchBudget = false): void
    {
        $slots = $item->semesterItems()->get();

        if ($slots->isEmpty()) {
            $this->fail('items', 'That objective has no schedule slots to rebalance.');
        }

        $programme = $slots->first()->semesterProgramme;
        $this->assertEditable($programme);

        if ($touchBudget) {
            if ($item->annualProgramme->isArchived()) {
                $this->fail('status', 'An archived annual programme is read-only.');
            }

            if ($budget !== null && $budget < 1) {
                $this->fail('planned_lesson_periods', 'A lesson-period budget must be at least 1, or left blank.');
            }
        }

        $known = $slots->pluck('id')->all();
        $given = array_map('intval', array_keys($lessonPeriodsBySlotId));

        if (array_diff($given, $known) !== []) {
            $this->fail('items', 'A slot in that allocation does not belong to this objective.');
        }

        if (array_diff($known, $given) !== []) {
            $this->fail('items', 'Give a figure for every slot of this objective, so the total is stated deliberately.');
        }

        foreach ($lessonPeriodsBySlotId as $value) {
            $this->assertPositiveBudget($value === null || $value === '' ? null : (int) $value);
        }

        DB::transaction(function () use ($item, $slots, $lessonPeriodsBySlotId, $programme, $budget, $touchBudget) {
            if ($touchBudget && $item->planned_lesson_periods !== $budget) {
                $item->update(['planned_lesson_periods' => $budget]);
            }

            foreach ($slots as $slot) {
                $value = $lessonPeriodsBySlotId[$slot->id] ?? null;
                $value = ($value === null || $value === '') ? null : (int) $value;

                if ($slot->planned_lesson_periods !== $value) {
                    $slot->update(['planned_lesson_periods' => $value]);
                }
            }

            $this->guardActive($programme);
        });
    }

    public function moveSlot(SemesterProgrammeItem $slot, string $direction): void
    {
        $programme = $slot->semesterProgramme;
        $this->assertEditable($programme);

        $slots = $programme->items()->get();

        $neighbour = $direction === 'up'
            ? $slots->where('position', '<', $slot->position)->last()
            : $slots->where('position', '>', $slot->position)->first();

        if (! $neighbour) {
            return;
        }

        $mine = $slot->position;
        $theirs = $neighbour->position;

        DB::transaction(function () use ($programme, $slot, $neighbour, $mine, $theirs) {
            $neighbour->update(['position' => $mine]);
            $slot->update(['position' => $theirs]);

            $this->normalise($programme);
            $this->guardActive($programme);
        });
    }

    /** Renumber to a contiguous 1..n, writing only rows that actually change. */
    public function normalise(SemesterProgramme $programme): void
    {
        $ordered = SemesterProgrammeItem::where('semester_programme_id', $programme->id)
            ->orderBy('position')->orderBy('id')->get();

        foreach ($ordered->values() as $index => $slot) {
            $expected = $index + 1;

            if ($slot->position !== $expected) {
                $slot->update(['position' => $expected]);
            }
        }
    }

    private function nextPosition(SemesterProgramme $programme): int
    {
        return ((int) $programme->items()->max('position')) + 1;
    }

    /**
     * Put the schedule into force.
     *
     * COMPLETENESS: every annual item allocated to this period must have at
     * least one slot -- but the number of slots is free, and items allocated to
     * a different period are irrelevant here.
     *
     * JP RECONCILIATION: only attempted where the annual item HAS a budget. In
     * that case every slot for it must state its own JP and the slots must sum
     * exactly to the budget; a partially-filled set is rejected rather than
     * guessed at. Where the annual item has no budget, slot JP stays optional
     * and nothing is reconciled.
     */
    public function activate(SemesterProgramme $programme): SemesterProgramme
    {
        if (! $programme->isDraft()) {
            $this->fail('status', 'Only a draft semester programme can be activated.');
        }

        $annual = $programme->annualProgramme;

        if (! $annual->isActive()) {
            $this->fail('status', "The annual programme is {$annual->status}. Activate it first.");
        }

        if ($programme->academicPeriod->academic_year_id !== $annual->academic_year_id) {
            $this->fail('academic_period_id', 'The period belongs to a different academic year.');
        }

        return DB::transaction(function () use ($programme) {
            $this->normalise($programme);
            $this->assertPlanIsComplete($programme);

            $programme->update(['status' => 'active']);

            return $programme->refresh();
        });
    }

    /**
     * THE INVARIANT. Everything an active semester plan must satisfy.
     *
     * Used twice: as the activation gate, and as the guard re-run after every
     * edit to an already-active plan. One implementation, so the two can never
     * disagree about what "valid" means.
     */
    public function assertPlanIsComplete(SemesterProgramme $programme): void
    {
        $allocated = $programme->annualProgramme->items()
            ->where('academic_period_id', $programme->academic_period_id)
            ->with('learningPathwayItem.learningObjective')
            ->get();

        if ($allocated->isEmpty()) {
            $this->fail('items', 'The annual programme has nothing allocated to this period.');
        }

        $slots = SemesterProgrammeItem::where('semester_programme_id', $programme->id)
            ->orderBy('position')->orderBy('id')->get();

        if ($slots->isEmpty()) {
            $this->fail('items', 'A semester programme needs at least one schedule slot.');
        }

        $allocatedIds = $allocated->pluck('id');

        // No orphans: a slot must schedule something this period allocates.
        // The composite keys already refuse it on write; this catches a slot
        // stranded by a later change on the annual side.
        foreach ($slots as $slot) {
            if (! $allocatedIds->contains($slot->annual_programme_item_id)) {
                $this->fail('items', 'A schedule slot points at an objective this period no longer allocates.');
            }
        }

        $slotsByItem = $slots->groupBy('annual_programme_item_id');

        foreach ($allocated as $item) {
            $itemSlots = $slotsByItem->get($item->id, collect());
            $label = $item->learningPathwayItem?->learningObjective?->code
                ?? 'Objective #'.$item->learningPathwayItem?->position;

            if ($itemSlots->isEmpty()) {
                $this->fail('items', "{$label} is allocated to this period but has no schedule slot yet.");
            }

            if ($item->planned_lesson_periods === null) {
                continue;
            }

            if ($itemSlots->contains(fn ($slot) => $slot->planned_lesson_periods === null)) {
                $this->fail('items', "{$label} has a {$item->planned_lesson_periods} JP budget, so every one of its slots needs its own JP.");
            }

            $total = (int) $itemSlots->sum('planned_lesson_periods');

            if ($total !== (int) $item->planned_lesson_periods) {
                $this->fail('items', "{$label}: the slots add up to {$total} JP but the annual budget is {$item->planned_lesson_periods} JP.");
            }
        }

        foreach ($slots as $slot) {
            $this->validateDates($programme, [
                'planned_start_date' => $slot->planned_start_date?->toDateString(),
                'planned_end_date' => $slot->planned_end_date?->toDateString(),
            ]);
        }

        if ($slots->pluck('position')->all() !== range(1, $slots->count())) {
            $this->fail('items', 'The schedule is not in a contiguous order.');
        }
    }

    /**
     * Re-assert the invariant against the state just written.
     *
     * Called inside the mutating transaction, so a violation rolls the edit
     * back. Draft plans are deliberately exempt.
     */
    private function guardActive(SemesterProgramme $programme): void
    {
        $current = $programme->fresh();

        if ($current === null || ! $current->isActive()) {
            return;
        }

        $this->assertPlanIsComplete($current);
    }

    public function archive(SemesterProgramme $programme): SemesterProgramme
    {
        if ($programme->isArchived()) {
            return $programme;
        }

        if ($programme->isDraft()) {
            $this->fail('status', 'A draft is deleted rather than archived.');
        }

        $programme->update(['status' => 'archived']);

        return $programme->refresh();
    }

    public function delete(SemesterProgramme $programme): void
    {
        if (! $programme->isDraft()) {
            $this->fail('status', 'Only an unused draft can be deleted. Archive an active programme instead.');
        }

        DB::transaction(function () use ($programme) {
            $programme->items()->get()->each->delete();
            $programme->delete();
        });
    }

    /**
     * Dates, when given, must sit inside the period. No fallback to a current
     * period, and an end without a start is rejected rather than assumed.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function validateDates(SemesterProgramme $programme, array $attributes): array
    {
        $start = ($attributes['planned_start_date'] ?? '') !== '' ? $attributes['planned_start_date'] : null;
        $end = ($attributes['planned_end_date'] ?? '') !== '' ? $attributes['planned_end_date'] : null;

        if ($start === null && $end === null) {
            return [null, null];
        }

        if ($start === null) {
            $this->fail('planned_start_date', 'An end date needs a start date.');
        }

        $period = $programme->academicPeriod;
        $startDate = Carbon::parse($start);
        $endDate = $end !== null ? Carbon::parse($end) : null;

        if ($endDate && $endDate->lt($startDate)) {
            $this->fail('planned_end_date', 'The end date cannot be before the start date.');
        }

        foreach (array_filter([$startDate, $endDate]) as $date) {
            if ($date->lt($period->start_date) || $date->gt($period->end_date)) {
                $this->fail(
                    'planned_start_date',
                    "Dates must fall inside {$period->name} ({$period->start_date->toDateString()} to {$period->end_date->toDateString()})."
                );
            }
        }

        return [$start, $end];
    }

    private function assertPositiveBudget(?int $lessonPeriods): void
    {
        if ($lessonPeriods !== null && $lessonPeriods < 1) {
            $this->fail('planned_lesson_periods', 'A lesson-period figure must be at least 1, or left blank.');
        }
    }

    private function assertEditable(SemesterProgramme $programme): void
    {
        if ($programme->isArchived()) {
            $this->fail('status', 'An archived semester programme is read-only.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
