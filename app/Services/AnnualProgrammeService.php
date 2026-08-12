<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\AnnualProgramme;
use App\Models\AnnualProgrammeItem;
use App\Models\LearningPathway;
use App\Models\LearningPathwayItem;
use App\Models\SchoolClass;
use App\Models\SemesterProgramme;
use App\Models\Subject;
use App\Models\TeachingGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Program Tahunan: selecting a pathway for a real roster, and allocating its
 * items to reporting periods.
 *
 * The database already refuses a mismatched year, period or pathway anchor.
 * What it cannot see -- and what lives here -- is whether the ROSTER actually
 * resolves to the pathway's curriculum scope: class -> grade -> learning phase,
 * or teaching group -> English level. That is a multi-hop traversal no foreign
 * key can express, and it is the rule that stops Green A following Blue's
 * pathway or Year 5A following a Phase D one.
 */
class AnnualProgrammeService
{
    public function createForClass(SchoolClass $class, Subject $subject, LearningPathway $pathway, array $attributes = []): AnnualProgramme
    {
        $this->assertPathwayFitsRoster($pathway, $subject, $class, null);

        return $this->create([
            'class_id' => $class->id,
            'teaching_group_id' => null,
            'academic_year_id' => $class->academic_year_id,
        ], $subject, $pathway, $attributes);
    }

    public function createForGroup(TeachingGroup $group, Subject $subject, LearningPathway $pathway, array $attributes = []): AnnualProgramme
    {
        $this->assertPathwayFitsRoster($pathway, $subject, null, $group);

        return $this->create([
            'class_id' => null,
            'teaching_group_id' => $group->id,
            'academic_year_id' => $group->academic_year_id,
        ], $subject, $pathway, $attributes);
    }

    private function create(array $roster, Subject $subject, LearningPathway $pathway, array $attributes): AnnualProgramme
    {
        return AnnualProgramme::create($roster + [
            'subject_id' => $subject->id,
            // Mirrored from the pathway so the composite key can verify it.
            'curriculum_scope_id' => $pathway->curriculum_scope_id,
            'learning_pathway_id' => $pathway->id,
            'title' => $attributes['title'] ?? null,
            'notes' => $attributes['notes'] ?? null,
            'status' => 'draft',
        ]);
    }

    /**
     * THE APPLICATION-LEVEL ELIGIBILITY RULE.
     *
     * National: the class's grade must map to the pathway's learning phase.
     * English: the teaching group's level must be the pathway's level.
     * Both: the subject must match. No is_current fallback anywhere.
     */
    public function assertPathwayFitsRoster(LearningPathway $pathway, Subject $subject, ?SchoolClass $class, ?TeachingGroup $group): void
    {
        if ($pathway->subject_id !== $subject->id) {
            $this->fail('learning_pathway_id', "{$pathway->title} is a {$pathway->subject->name} pathway, not {$subject->name}.");
        }

        $scope = $pathway->curriculumScope;

        if ($class) {
            if (! $scope->isPhaseBased()) {
                $this->fail('learning_pathway_id', "{$pathway->title} belongs to an English level, so it cannot be followed by a class.");
            }

            $phaseId = $class->grade?->learningPhaseLink?->learning_phase_id;

            if ($phaseId === null) {
                $this->fail('learning_pathway_id', "{$class->grade?->name} is not mapped to a learning phase, so no pathway can be selected for it.");
            }

            if ($phaseId !== $scope->learning_phase_id) {
                $this->fail('learning_pathway_id', "{$class->name} is in {$class->grade->learningPhase()?->name}, but {$pathway->title} covers {$scope->displayName()}.");
            }

            return;
        }

        if ($scope->isPhaseBased()) {
            $this->fail('learning_pathway_id', "{$pathway->title} belongs to a learning phase, so it cannot be followed by a teaching group.");
        }

        if ($group->english_level_id === null) {
            $this->fail('learning_pathway_id', "{$group->name} is not tied to an English level, so no English pathway can be selected for it.");
        }

        if ($group->english_level_id !== $scope->english_level_id) {
            $this->fail('learning_pathway_id', "{$group->name} teaches {$group->englishLevel?->name}, but {$pathway->title} covers {$scope->displayName()}.");
        }
    }

    /**
     * Allocate a pathway item to a reporting period.
     */
    public function addItem(AnnualProgramme $programme, LearningPathwayItem $pathwayItem, AcademicPeriod $period, ?int $lessonPeriods = null, ?string $notes = null): AnnualProgrammeItem
    {
        $this->assertEditable($programme);

        if ($pathwayItem->learning_pathway_id !== $programme->learning_pathway_id) {
            $this->fail('learning_pathway_item_id', 'That item belongs to a different learning pathway.');
        }

        if ($period->academic_year_id !== $programme->academic_year_id) {
            $this->fail('academic_period_id', "{$period->name} belongs to a different academic year.");
        }

        if ($programme->items()->where('learning_pathway_item_id', $pathwayItem->id)->exists()) {
            $this->fail('learning_pathway_item_id', 'That objective is already allocated in this programme.');
        }

        $this->assertPeriodHasNoActiveSchedule($programme, $period);
        $this->assertPositiveBudget($lessonPeriods);

        return AnnualProgrammeItem::create([
            'annual_programme_id' => $programme->id,
            'learning_pathway_item_id' => $pathwayItem->id,
            'learning_pathway_id' => $programme->learning_pathway_id,
            'academic_year_id' => $programme->academic_year_id,
            'academic_period_id' => $period->id,
            'planned_lesson_periods' => $lessonPeriods,
            'notes' => $notes,
        ]);
    }

    /**
     * Move an allocation to a different period, or change its budget.
     *
     * Moving an item that is already scheduled would orphan its slots -- the
     * composite key would refuse it anyway, so this turns a constraint
     * violation into a sentence an administrator can act on.
     */
    public function updateItem(AnnualProgrammeItem $item, ?AcademicPeriod $period = null, ?int $lessonPeriods = null, bool $touchBudget = false, ?string $notes = null): AnnualProgrammeItem
    {
        $programme = $item->annualProgramme;
        $this->assertEditable($programme);

        $changes = [];

        if ($period && $period->id !== $item->academic_period_id) {
            if ($item->semesterItems()->exists()) {
                $this->fail(
                    'academic_period_id',
                    'This item is already scheduled in its current period. Revise that semester programme before moving it.'
                );
            }

            if ($period->academic_year_id !== $item->academic_year_id) {
                $this->fail('academic_period_id', "{$period->name} belongs to a different academic year.");
            }

            // Unscheduled here, but arriving in a period whose plan is already
            // in force would leave that plan incomplete the moment it lands.
            $this->assertPeriodHasNoActiveSchedule($programme, $period);

            $changes['academic_period_id'] = $period->id;
        }

        if ($touchBudget) {
            $this->assertPositiveBudget($lessonPeriods);
            $this->assertBudgetStillReconciles($item, $lessonPeriods);
            $changes['planned_lesson_periods'] = $lessonPeriods;
        }

        if ($notes !== null) {
            $changes['notes'] = $notes !== '' ? $notes : null;
        }

        if ($changes !== []) {
            $item->update($changes);
        }

        return $item->refresh();
    }

    public function removeItem(AnnualProgrammeItem $item): void
    {
        $this->assertEditable($item->annualProgramme);

        if ($item->semesterItems()->exists()) {
            $this->fail('items', 'This item is scheduled in a semester programme. Remove those slots first.');
        }

        $item->delete();
    }

    /**
     * Put the plan into force. Everything is checked before anything is
     * written, and the write runs in a transaction.
     */
    public function activate(AnnualProgramme $programme): AnnualProgramme
    {
        if (! $programme->isDraft()) {
            $this->fail('status', 'Only a draft annual programme can be activated.');
        }

        $pathway = $programme->learningPathway;

        if (! $pathway->isActive()) {
            $this->fail('learning_pathway_id', "{$pathway->title} is {$pathway->status}. Only an active pathway can be put into an operating plan.");
        }

        if (! $pathway->curriculumScope->curriculum->isActive()) {
            $this->fail('learning_pathway_id', 'The curriculum version behind this pathway is not active.');
        }

        // Re-check eligibility: a class could have been re-graded since the
        // draft was written.
        $this->assertPathwayFitsRoster($pathway, $programme->subject, $programme->schoolClass, $programme->teachingGroup);

        $items = $programme->items()->with('academicPeriod')->get();

        if ($items->isEmpty()) {
            $this->fail('items', 'Allocate at least one objective before activating this programme.');
        }

        foreach ($items as $item) {
            if ($item->learning_pathway_id !== $programme->learning_pathway_id) {
                $this->fail('items', 'An allocated item no longer belongs to the selected pathway.');
            }

            if ($item->academicPeriod->academic_year_id !== $programme->academic_year_id) {
                $this->fail('items', "{$item->academicPeriod->name} belongs to a different academic year.");
            }

            $this->assertPositiveBudget($item->planned_lesson_periods);
        }

        // Deliberately NOT required: that the programme covers the whole
        // pathway. Year 5 takes one portion of Phase C and Year 6 the rest.
        $clash = AnnualProgramme::where('subject_id', $programme->subject_id)
            ->when($programme->isClassBacked(),
                fn ($q) => $q->where('class_id', $programme->class_id),
                fn ($q) => $q->where('teaching_group_id', $programme->teaching_group_id))
            ->whereKeyNot($programme->id)
            ->active()
            ->exists();

        if ($clash) {
            $this->fail('status', "{$programme->rosterName()} already has an active {$programme->subject->name} programme. Archive it first.");
        }

        return DB::transaction(function () use ($programme) {
            $programme->update(['status' => 'active']);

            return $programme->refresh();
        });
    }

    /**
     * Archiving is bottom-up: operational semester plans first, then the annual
     * plan above them.
     *
     * An archived annual programme is read-only, so an active semester plan
     * hanging beneath one would be a live schedule whose budget nobody can
     * correct. Nothing cascades -- archived semester programmes keep every row
     * they had, and are never rewritten by anything that happens here.
     */
    public function archive(AnnualProgramme $programme): AnnualProgramme
    {
        if ($programme->isArchived()) {
            return $programme;
        }

        if ($programme->isDraft()) {
            $this->fail('status', 'A draft is deleted rather than archived.');
        }

        $stillActive = $programme->semesterProgrammes()->where('status', 'active')->with('academicPeriod')->get();

        if ($stillActive->isNotEmpty()) {
            $names = $stillActive->map(fn ($sp) => $sp->academicPeriod->name)->implode(', ');

            $this->fail('status', "Archive the active semester programme for {$names} first, then archive the annual programme.");
        }

        $programme->update(['status' => 'archived']);

        return $programme->refresh();
    }

    public function delete(AnnualProgramme $programme): void
    {
        if (! $programme->isDraft()) {
            $this->fail('status', 'Only an unused draft can be deleted. Archive an active programme instead.');
        }

        if ($programme->semesterProgrammes()->exists()) {
            $this->fail('status', 'Remove this programme\'s semester plans first.');
        }

        DB::transaction(function () use ($programme) {
            $programme->items()->get()->each->delete();
            $programme->delete();
        });
    }

    /**
     * Pathways this roster could legitimately follow -- active ones whose
     * scope the roster resolves to, for this subject.
     */
    public function eligiblePathways(Subject $subject, ?SchoolClass $class, ?TeachingGroup $group)
    {
        return LearningPathway::active()
            ->where('subject_id', $subject->id)
            ->with('curriculumScope')
            ->get()
            ->filter(function (LearningPathway $pathway) use ($subject, $class, $group) {
                try {
                    $this->assertPathwayFitsRoster($pathway, $subject, $class, $group);

                    return true;
                } catch (ValidationException) {
                    return false;
                }
            })
            ->values();
    }

    /**
     * A period whose semester plan is IN FORCE will not accept a new objective.
     *
     * Allocating one would make that active plan incomplete the instant it
     * landed. The alternatives -- inventing a slot, or silently pushing the
     * semester plan back to draft -- both falsify data on the teacher's behalf,
     * so this refuses and names the workflow instead.
     */
    private function assertPeriodHasNoActiveSchedule(AnnualProgramme $programme, AcademicPeriod $period): void
    {
        $active = $programme->semesterProgrammes()
            ->where('academic_period_id', $period->id)
            ->where('status', 'active')
            ->exists();

        if ($active) {
            $this->fail(
                'academic_period_id',
                "{$period->name} already has an active Semester Programme. Add this objective through a planning revision that also schedules it."
            );
        }
    }

    /**
     * Changing a budget must not falsify a schedule that is already in force.
     *
     * To NULL is always allowed: it removes the reconciliation requirement
     * rather than contradicting the slots, and no slot data becomes untrue.
     * To a figure -- including from NULL -- the existing slots must already
     * state their own JP and sum to exactly that figure. Rebalance the slots
     * first (one atomic operation) and then move the budget.
     */
    private function assertBudgetStillReconciles(AnnualProgrammeItem $item, ?int $lessonPeriods): void
    {
        if ($lessonPeriods === null) {
            return;
        }

        $scheduled = SemesterProgramme::where('annual_programme_id', $item->annual_programme_id)
            ->where('academic_period_id', $item->academic_period_id)
            ->where('status', 'active')
            ->exists();

        if (! $scheduled) {
            return;
        }

        $slots = $item->semesterItems()->get();

        if ($slots->isEmpty()) {
            return;
        }

        if ($slots->contains(fn ($slot) => $slot->planned_lesson_periods === null)) {
            $this->fail(
                'planned_lesson_periods',
                "This item is scheduled in the active Semester Programme by slots that carry no JP. Give every slot its own JP before setting an annual budget of {$lessonPeriods} JP."
            );
        }

        $total = (int) $slots->sum('planned_lesson_periods');

        if ($total !== $lessonPeriods) {
            $this->fail(
                'planned_lesson_periods',
                "This item is scheduled for {$total} JP in the active Semester Programme. Update the semester allocation before changing the annual budget to {$lessonPeriods} JP."
            );
        }
    }

    private function assertPositiveBudget(?int $lessonPeriods): void
    {
        if ($lessonPeriods !== null && $lessonPeriods < 1) {
            $this->fail('planned_lesson_periods', 'A lesson-period budget must be at least 1, or left blank.');
        }
    }

    private function assertEditable(AnnualProgramme $programme): void
    {
        if ($programme->isArchived()) {
            $this->fail('status', 'An archived annual programme is read-only.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
