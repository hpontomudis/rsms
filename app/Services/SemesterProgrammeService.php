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

            return $slot->refresh();
        });
    }

    public function updateSlot(SemesterProgrammeItem $slot, array $attributes): SemesterProgrammeItem
    {
        $programme = $slot->semesterProgramme;
        $this->assertEditable($programme);

        [$start, $end] = $this->validateDates($programme, $attributes);
        $this->assertPositiveBudget($attributes['planned_lesson_periods'] ?? null);

        $slot->update([
            'week_label' => ($attributes['week_label'] ?? '') !== '' ? $attributes['week_label'] : null,
            'planned_start_date' => $start,
            'planned_end_date' => $end,
            'planned_lesson_periods' => $attributes['planned_lesson_periods'] ?? null,
            'notes' => ($attributes['notes'] ?? '') !== '' ? $attributes['notes'] : null,
        ]);

        return $slot->refresh();
    }

    public function removeSlot(SemesterProgrammeItem $slot): void
    {
        $programme = $slot->semesterProgramme;
        $this->assertEditable($programme);

        DB::transaction(function () use ($programme, $slot) {
            $slot->delete();
            $this->normalise($programme);
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

        $allocated = $annual->items()
            ->where('academic_period_id', $programme->academic_period_id)
            ->with('learningPathwayItem.learningObjective')
            ->get();

        if ($allocated->isEmpty()) {
            $this->fail('items', 'The annual programme has nothing allocated to this period.');
        }

        $slots = $programme->items()->get();

        if ($slots->isEmpty()) {
            $this->fail('items', 'Schedule at least one slot before activating this programme.');
        }

        $slotsByItem = $slots->groupBy('annual_programme_item_id');

        foreach ($allocated as $item) {
            $itemSlots = $slotsByItem->get($item->id, collect());

            if ($itemSlots->isEmpty()) {
                $label = $item->learningPathwayItem?->learningObjective?->code
                    ?? 'objective #'.$item->learningPathwayItem?->position;

                $this->fail('items', "{$label} is allocated to this period but has no schedule slot yet.");
            }

            if ($item->planned_lesson_periods === null) {
                continue;
            }

            $label = $item->learningPathwayItem?->learningObjective?->code ?? 'An allocated objective';

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

        return DB::transaction(function () use ($programme) {
            $this->normalise($programme);

            $positions = SemesterProgrammeItem::where('semester_programme_id', $programme->id)
                ->orderBy('position')->pluck('position');

            if ($positions->all() !== range(1, $positions->count())) {
                $this->fail('items', 'The schedule could not be normalised to a contiguous order.');
            }

            $programme->update(['status' => 'active']);

            return $programme->refresh();
        });
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
