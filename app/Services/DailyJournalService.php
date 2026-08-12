<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\CurriculumScope;
use App\Models\DailyJournal;
use App\Models\DailyJournalAssessment;
use App\Models\DailyJournalLearningObjective;
use App\Models\LearningObjective;
use App\Models\SemesterProgrammeItem;
use App\Models\Staff;
use App\Models\TeachingModule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Jurnal Harian Guru: what actually happened, recorded against the assignment
 * that was in force on the day.
 *
 * THE DATE IS THE HARD PART, and nothing about it is guessed. journal_date must
 * sit inside the assignment's effective range and inside the stored academic
 * period; the period is chosen, never inferred from a first() match, because
 * academic periods carry no guarantee of being non-overlapping or complete.
 * periodsFor() therefore returns every candidate and lets the caller decide, so
 * zero and several are both reported rather than resolved.
 *
 * A normal teacher writes only for their ACTIVE assignment. A manager may
 * backfill onto a closed one -- an explicit, audited correction privilege --
 * but only for a date that assignment actually covered. Modules are stricter:
 * no new module against closed history, ever, because a plan cannot be written
 * after the teaching.
 */
class DailyJournalService
{
    public function __construct(private TeachingModuleService $modules) {}

    // ------------------------------------------------------------ period

    /**
     * Every academic period of this assignment's year containing the date.
     *
     * Returns a collection on purpose. Exactly one is the normal case and the
     * UI may preselect it; zero and several are configuration problems the user
     * must see, not something to paper over with first().
     */
    public function periodsFor(ClassSubject $assignment, string $date)
    {
        $on = Carbon::parse($date);

        return AcademicPeriod::where('academic_year_id', $assignment->academicYear()->id)
            ->whereDate('start_date', '<=', $on)
            ->whereDate('end_date', '>=', $on)
            ->orderBy('sequence')
            ->get();
    }

    /** Scopes are resolved by exactly the same rule the module uses. */
    public function eligibleScopes(ClassSubject $assignment)
    {
        return $this->modules->eligibleScopes($assignment);
    }

    // ------------------------------------------------------------ create

    /**
     * @param  array{topic: string, actual_activity: string, reflection?: ?string, follow_up?: ?string, meeting_number?: int|null, actual_lesson_periods?: int|null}  $attributes
     */
    public function create(
        ClassSubject $assignment,
        CurriculumScope $scope,
        AcademicPeriod $period,
        string $journalDate,
        Staff $conductedBy,
        array $attributes,
        ?TeachingModule $module = null,
        ?SemesterProgrammeItem $slot = null,
        bool $historicalBackfill = false,
    ): DailyJournal {
        if (! $assignment->isActive() && ! $historicalBackfill) {
            $this->fail('class_subject_id', 'That teaching assignment has ended. Only a manager may backfill a journal onto closed history.');
        }

        $this->modules->assertScopeFitsAssignment($assignment, $scope);
        $this->assertDateFitsAssignment($assignment, $journalDate);
        $this->assertPeriodFits($assignment, $period, $journalDate);
        $this->assertContent($attributes);
        $this->assertPositive($attributes['actual_lesson_periods'] ?? null, 'actual_lesson_periods', 'A lesson-period figure');
        $this->assertPositive($attributes['meeting_number'] ?? null, 'meeting_number', 'A meeting number');

        if ($module) {
            $this->assertModuleFits($module, $assignment, $scope);
        }

        if ($slot) {
            $this->assertSlotFits($slot, $assignment, $scope, $period, $journalDate);
        }

        return DailyJournal::create([
            'class_subject_id' => $assignment->id,
            'class_id' => $assignment->class_id,
            'teaching_group_id' => $assignment->teaching_group_id,
            'subject_id' => $assignment->subject_id,
            'curriculum_scope_id' => $scope->id,
            'academic_year_id' => $assignment->academicYear()->id,
            'academic_period_id' => $period->id,
            'journal_date' => $journalDate,
            'conducted_by_staff_id' => $conductedBy->id,
            'teaching_module_id' => $module?->id,
            'semester_programme_item_id' => $slot?->id,
            'meeting_number' => $attributes['meeting_number'] ?? null,
            'topic' => $attributes['topic'],
            'actual_activity' => $attributes['actual_activity'],
            'reflection' => $this->orNull($attributes['reflection'] ?? null),
            'follow_up' => $this->orNull($attributes['follow_up'] ?? null),
            'actual_lesson_periods' => $attributes['actual_lesson_periods'] ?? null,
            'status' => 'draft',
        ]);
    }

    /**
     * Edit the record. A finalized journal is only reachable here through a
     * manager correction, which the caller authorises; the audit log is the
     * correction history, so there is no separate 'corrected' state.
     */
    public function update(DailyJournal $journal, array $attributes): DailyJournal
    {
        $this->assertContent($attributes + $journal->only(['topic', 'actual_activity']));
        $this->assertPositive($attributes['actual_lesson_periods'] ?? null, 'actual_lesson_periods', 'A lesson-period figure');
        $this->assertPositive($attributes['meeting_number'] ?? null, 'meeting_number', 'A meeting number');

        if (array_key_exists('journal_date', $attributes) && $attributes['journal_date'] !== $journal->journal_date->toDateString()) {
            $this->assertDateFitsAssignment($journal->classSubject, $attributes['journal_date']);
            $this->assertPeriodFits($journal->classSubject, $journal->academicPeriod, $attributes['journal_date']);
        }

        $changes = [];

        foreach (['journal_date', 'topic', 'actual_activity', 'meeting_number', 'actual_lesson_periods'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $changes[$field] = $attributes[$field];
            }
        }

        foreach (['reflection', 'follow_up'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $changes[$field] = $this->orNull($attributes[$field]);
            }
        }

        if ($changes !== []) {
            $journal->update($changes);
        }

        return $journal->refresh();
    }

    public function setConductor(DailyJournal $journal, Staff $conductedBy): DailyJournal
    {
        // Deliberately NOT requiring active staff: a 2025 session conducted by
        // someone who left in 2026 is still a fact about 2025, and current
        // status cannot prove historical status.
        $journal->update(['conducted_by_staff_id' => $conductedBy->id]);

        return $journal->refresh();
    }

    // ------------------------------------------------------------- links

    public function linkModule(DailyJournal $journal, ?TeachingModule $module): DailyJournal
    {
        if ($module) {
            $this->assertModuleFits($module, $journal->classSubject, $journal->curriculumScope);
        }

        $journal->update(['teaching_module_id' => $module?->id]);

        return $journal->refresh();
    }

    public function linkSlot(DailyJournal $journal, ?SemesterProgrammeItem $slot): DailyJournal
    {
        if ($slot) {
            $this->assertSlotFits(
                $slot,
                $journal->classSubject,
                $journal->curriculumScope,
                $journal->academicPeriod,
                $journal->journal_date->toDateString(),
            );
        }

        $journal->update(['semester_programme_item_id' => $slot?->id]);

        return $journal->refresh();
    }

    public function linkObjective(DailyJournal $journal, LearningObjective $objective): DailyJournalLearningObjective
    {
        if ($objective->curriculum_scope_id !== $journal->curriculum_scope_id || $objective->subject_id !== $journal->subject_id) {
            $this->fail('learning_objective_id', "{$objective->code} belongs to a different scope or subject.");
        }

        if ($journal->objectiveLinks()->where('learning_objective_id', $objective->id)->exists()) {
            $this->fail('learning_objective_id', 'That objective is already recorded on this journal.');
        }

        // No subset rule against the module: a lesson may cover something that
        // was never planned, and that is precisely what a journal is for.
        return DailyJournalLearningObjective::create([
            'daily_journal_id' => $journal->id,
            'learning_objective_id' => $objective->id,
            'curriculum_scope_id' => $journal->curriculum_scope_id,
            'subject_id' => $journal->subject_id,
        ]);
    }

    public function unlinkObjective(DailyJournal $journal, LearningObjective $objective): void
    {
        $journal->objectiveLinks()->where('learning_objective_id', $objective->id)->get()->each->delete();
    }

    public function linkAssessment(DailyJournal $journal, Assessment $assessment): DailyJournalAssessment
    {
        if ($assessment->class_subject_id !== $journal->class_subject_id) {
            $this->fail('assessment_id', "{$assessment->name} belongs to a different teaching assignment.");
        }

        if ($journal->assessmentLinks()->where('assessment_id', $assessment->id)->exists()) {
            $this->fail('assessment_id', 'That assessment is already recorded on this journal.');
        }

        return DailyJournalAssessment::create([
            'daily_journal_id' => $journal->id,
            'assessment_id' => $assessment->id,
            'class_subject_id' => $journal->class_subject_id,
        ]);
    }

    public function unlinkAssessment(DailyJournal $journal, Assessment $assessment): void
    {
        $journal->assessmentLinks()->where('assessment_id', $assessment->id)->get()->each->delete();
    }

    // --------------------------------------------------------- lifecycle

    /**
     * Everything a finalized record must satisfy. Nothing optional is made
     * compulsory here: real teaching is sometimes ad hoc, so a journal may
     * finalize with no module, no slot, no assessment and no meeting number.
     */
    public function finalize(DailyJournal $journal): DailyJournal
    {
        if (! $journal->isDraft()) {
            $this->fail('status', 'Only a draft journal can be finalized.');
        }

        $assignment = $journal->classSubject;

        $this->assertDateFitsAssignment($assignment, $journal->journal_date->toDateString());
        $this->assertPeriodFits($assignment, $journal->academicPeriod, $journal->journal_date->toDateString());
        $this->modules->assertScopeFitsAssignment($assignment, $journal->curriculumScope);
        $this->assertContent($journal->only(['topic', 'actual_activity']));
        $this->assertPositive($journal->actual_lesson_periods, 'actual_lesson_periods', 'A lesson-period figure');

        if (! $journal->conductedBy) {
            $this->fail('conducted_by_staff_id', 'A journal needs to say who conducted the session.');
        }

        if ($journal->teachingModule) {
            $this->assertModuleFits($journal->teachingModule, $assignment, $journal->curriculumScope);
        }

        if ($journal->semesterProgrammeItem) {
            $this->assertSlotFits(
                $journal->semesterProgrammeItem,
                $assignment,
                $journal->curriculumScope,
                $journal->academicPeriod,
                $journal->journal_date->toDateString(),
            );
        }

        foreach ($journal->objectiveLinks()->with('learningObjective')->get() as $link) {
            $objective = $link->learningObjective;

            if ($objective->status === 'draft') {
                $this->fail('objectives', "{$objective->code} is still a draft objective and cannot be recorded as taught.");
            }

            if ($link->curriculum_scope_id !== $journal->curriculum_scope_id || $link->subject_id !== $journal->subject_id) {
                $this->fail('objectives', 'A recorded objective no longer matches this journal scope and subject.');
            }
        }

        foreach ($journal->assessmentLinks()->with('assessment')->get() as $link) {
            if ($link->assessment?->class_subject_id !== $journal->class_subject_id) {
                $this->fail('assessments', 'A linked assessment belongs to a different teaching assignment.');
            }
        }

        return DB::transaction(function () use ($journal) {
            $journal->update(['status' => 'finalized']);

            return $journal->refresh();
        });
    }

    public function delete(DailyJournal $journal): void
    {
        if (! $journal->isDraft()) {
            $this->fail('status', 'A finalized journal is a record of what happened and is never deleted.');
        }

        DB::transaction(function () use ($journal) {
            $journal->objectiveLinks()->get()->each->delete();
            $journal->assessmentLinks()->get()->each->delete();
            $journal->delete();
        });
    }

    /** The next meeting number to OFFER. Advisory: never unique, never identity. */
    public function suggestMeetingNumber(ClassSubject $assignment, AcademicPeriod $period): int
    {
        return ((int) DailyJournal::where('class_subject_id', $assignment->id)
            ->where('academic_period_id', $period->id)
            ->max('meeting_number')) + 1;
    }

    // ----------------------------------------------------------- guards

    private function assertDateFitsAssignment(ClassSubject $assignment, string $date): void
    {
        $on = Carbon::parse($date);

        if ($on->lt($assignment->started_on)) {
            $this->fail('journal_date', "That assignment did not begin until {$assignment->started_on->format('d M Y')}.");
        }

        if ($assignment->ended_on && $on->gt($assignment->ended_on)) {
            $this->fail('journal_date', "That assignment ended on {$assignment->ended_on->format('d M Y')}.");
        }
    }

    private function assertPeriodFits(ClassSubject $assignment, AcademicPeriod $period, string $date): void
    {
        if ($period->academic_year_id !== $assignment->academicYear()->id) {
            $this->fail('academic_period_id', "{$period->name} belongs to a different academic year.");
        }

        $on = Carbon::parse($date);

        if ($on->lt($period->start_date) || $on->gt($period->end_date)) {
            $this->fail(
                'academic_period_id',
                "{$on->format('d M Y')} falls outside {$period->name} ({$period->start_date->toDateString()} to {$period->end_date->toDateString()})."
            );
        }
    }

    /**
     * A journal may cite a module from a DIFFERENT assignment -- that is the
     * successor case, where Eka deliberately teaches Sarah's plan. What must
     * match is the teaching context: roster, subject and scope. Authorship
     * stays with the module's own assignment.
     */
    private function assertModuleFits(TeachingModule $module, ClassSubject $assignment, CurriculumScope $scope): void
    {
        if (! $module->isCitable()) {
            $this->fail('teaching_module_id', 'That module is still a draft. Only a module marked ready can be taught from.');
        }

        if ($module->class_id !== $assignment->class_id || $module->teaching_group_id !== $assignment->teaching_group_id) {
            $this->fail('teaching_module_id', "That module was written for {$module->rosterName()}, not for this roster.");
        }

        if ($module->subject_id !== $assignment->subject_id) {
            $this->fail('teaching_module_id', 'That module was written for a different subject.');
        }

        if ($module->curriculum_scope_id !== $scope->id) {
            $this->fail('teaching_module_id', 'That module follows a different curriculum scope.');
        }
    }

    private function assertSlotFits(SemesterProgrammeItem $slot, ClassSubject $assignment, CurriculumScope $scope, AcademicPeriod $period, string $date): void
    {
        $annual = $slot->semesterProgramme?->annualProgramme;

        if (! $annual) {
            $this->fail('semester_programme_item_id', 'That schedule slot has no resolvable annual programme.');
        }

        if ($annual->class_id !== $assignment->class_id || $annual->teaching_group_id !== $assignment->teaching_group_id) {
            $this->fail('semester_programme_item_id', "That slot belongs to {$annual->rosterName()}, not to this roster.");
        }

        if ($annual->subject_id !== $assignment->subject_id) {
            $this->fail('semester_programme_item_id', 'That slot belongs to a different subject.');
        }

        if ($annual->curriculum_scope_id !== $scope->id) {
            $this->fail('semester_programme_item_id', 'That slot follows a different curriculum scope.');
        }

        if ($annual->academic_year_id !== $assignment->academicYear()->id) {
            $this->fail('semester_programme_item_id', 'That slot belongs to a different academic year.');
        }

        if ($slot->academic_period_id !== $period->id) {
            $this->fail('semester_programme_item_id', "That slot was scheduled in a different period from {$period->name}.");
        }

        // Deliberately NOT compared against the slot's planned dates. Teaching
        // legitimately happens earlier or later than planned; the period is the
        // boundary that matters, and it is already checked.
        unset($date);
    }

    private function assertContent(array $attributes): void
    {
        if (trim((string) ($attributes['topic'] ?? '')) === '') {
            $this->fail('topic', 'A journal needs to say what was taught.');
        }

        if (trim((string) ($attributes['actual_activity'] ?? '')) === '') {
            $this->fail('actual_activity', 'A journal needs to say what actually happened.');
        }
    }

    private function assertPositive(?int $value, string $field, string $label): void
    {
        if ($value !== null && $value < 1) {
            $this->fail($field, "{$label} must be at least 1, or left blank.");
        }
    }

    private function orNull(?string $value): ?string
    {
        return ($value === null || trim($value) === '') ? null : $value;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
