<?php

namespace Tests\Feature;

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
use App\Services\DailyJournalService;
use App\Services\SemesterProgrammeService;
use App\Services\TeachingModuleService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Feature\Concerns\BuildsPlanningFixtures;
use Tests\TestCase;

/**
 * Jurnal Harian Guru: what actually happened.
 *
 * The tests worth reading are the ones about DATES (never guessed), about the
 * separation of "responsible teacher" from "who actually taught", and about
 * plan-versus-actual: a journal's objectives are its own, not the module's.
 */
class DailyJournalTest extends TestCase
{
    use BuildsPlanningFixtures;
    use RefreshDatabase;

    private function journals(): DailyJournalService
    {
        return app(DailyJournalService::class);
    }

    private function modules(): TeachingModuleService
    {
        return app(TeachingModuleService::class);
    }

    private function activeScope(string $phaseCode): CurriculumScope
    {
        $scope = $this->scopeFor($phaseCode);
        $this->restoreActive($this->curriculum());

        return $scope;
    }

    private function activeEnglishScope(string $levelName): CurriculumScope
    {
        $scope = $this->englishScope($levelName);
        $this->restoreActive($this->englishCurriculum());

        return $scope;
    }

    private function content(array $overrides = []): array
    {
        return $overrides + [
            'topic' => 'Pecahan senilai',
            'actual_activity' => 'Kerja kelompok kertas lipat; dua kelompok belum selesai.',
        ];
    }

    private function objective(int $order = 1, string $subject = 'Maths'): LearningObjective
    {
        return $this->objectiveIn($this->activeScope('C'), $subject, "TP {$order}", $order);
    }

    /** A journal on Sarah's Year 5A Mathematics assignment, dated in Semester 1. */
    private function journal(array $overrides = [], string $date = '2026-09-15'): DailyJournal
    {
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');

        return $this->journals()->create(
            $assignment,
            $this->activeScope('C'),
            $this->period('Semester 1'),
            $date,
            $this->staff('sarah'),
            $this->content($overrides),
        );
    }

    // ------------------------------------------------------------ anchor

    public function test_a_class_backed_journal_mirrors_its_assignment(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');

        $journal = $this->journal();

        $this->assertSame($assignment->id, $journal->class_subject_id);
        $this->assertSame($assignment->class_id, $journal->class_id);
        $this->assertNull($journal->teaching_group_id);
        $this->assertSame($this->year->id, $journal->academic_year_id);
        $this->assertSame($this->period('Semester 1')->id, $journal->academic_period_id);
        $this->assertTrue($journal->isDraft());
    }

    public function test_a_group_backed_journal_mirrors_its_teaching_group(): void
    {
        $this->seedReferenceData();
        $assignment = $this->groupAssignmentFor('Green', 'Eng', 'lena');

        $journal = $this->journals()->create(
            $assignment,
            $this->activeEnglishScope('Green'),
            $this->period('Semester 1'),
            '2026-09-15',
            $this->staff('lena'),
            $this->content(),
        );

        $this->assertSame($assignment->teaching_group_id, $journal->teaching_group_id);
        $this->assertNull($journal->class_id);
        $this->assertSame('Teaching Group', $journal->rosterLabel());
    }

    public function test_the_assignment_anchor_is_immutable_even_while_draft(): void
    {
        $this->seedReferenceData();
        $journal = $this->journal();
        $other = $this->assignmentFor('Year 6A', 'Maths', 'eka');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('fixed at creation');

        $journal->update(['class_subject_id' => $other->id]);
    }

    // -------------------------------------------------------------- date

    public function test_a_date_before_the_assignment_began_is_refused(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah', '2026-09-01');

        try {
            $this->journals()->create(
                $assignment, $this->activeScope('C'), $this->period('Semester 1'),
                '2026-08-15', $this->staff('sarah'), $this->content(),
            );
            $this->fail('a journal was dated before its assignment existed');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('did not begin until', $e->errors()['journal_date'][0]);
        }

        $this->assertSame(0, DailyJournal::count());
    }

    public function test_a_date_after_the_assignment_ended_is_refused(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $this->closeAssignment($assignment, '2026-08-31');

        $this->expectException(ValidationException::class);

        $this->journals()->create(
            $assignment->fresh(), $this->activeScope('C'), $this->period('Semester 1'),
            '2026-09-15', $this->staff('sarah'), $this->content(), historicalBackfill: true,
        );
    }

    public function test_a_date_outside_the_selected_period_is_refused(): void
    {
        $this->seedReferenceData();

        try {
            // Semester 1 runs to 31 Dec; the date sits in Semester 2.
            $this->journal([], '2027-03-01');
            $this->fail('a journal was filed under a period that does not contain its date');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('falls outside Semester 1', $e->errors()['academic_period_id'][0]);
        }
    }

    public function test_a_period_from_another_year_is_refused(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');

        $otherYear = \App\Models\AcademicYear::create([
            'name' => '2027/2028', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'is_current' => false,
        ]);
        $foreign = \App\Models\AcademicPeriod::create([
            'academic_year_id' => $otherYear->id, 'name' => 'Semester 1', 'sequence' => 1,
            'start_date' => '2027-07-01', 'end_date' => '2027-12-31',
        ]);

        $this->expectException(ValidationException::class);

        $this->journals()->create(
            $assignment, $this->activeScope('C'), $foreign,
            '2026-09-15', $this->staff('sarah'), $this->content(),
        );
    }

    public function test_the_database_refuses_a_period_year_mismatch_even_in_raw_sql(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');

        $otherYear = \App\Models\AcademicYear::create([
            'name' => '2027/2028', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'is_current' => false,
        ]);
        $foreign = \App\Models\AcademicPeriod::create([
            'academic_year_id' => $otherYear->id, 'name' => 'Semester 1', 'sequence' => 1,
            'start_date' => '2027-07-01', 'end_date' => '2027-12-31',
        ]);

        $this->expectException(QueryException::class);

        DB::table('daily_journals')->insert([
            'class_subject_id' => $assignment->id,
            'class_id' => $assignment->class_id,
            'teaching_group_id' => null,
            'subject_id' => $assignment->subject_id,
            'curriculum_scope_id' => $this->activeScope('C')->id,
            'academic_year_id' => $this->year->id,      // this year...
            'academic_period_id' => $foreign->id,       // ...next year's period
            'journal_date' => '2026-09-15',
            'conducted_by_staff_id' => $this->staff('sarah')->id,
            'topic' => 'Forged',
            'actual_activity' => 'x',
            'status' => 'draft',
        ]);
    }

    // ------------------------------------------------- period resolution

    public function test_period_resolution_returns_every_candidate_and_never_guesses(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');

        // Exactly one: the normal case a screen may preselect.
        $this->assertCount(1, $this->journals()->periodsFor($assignment, '2026-09-15'));

        // Zero: a gap in the calendar is reported, not filled in.
        $this->period('Semester 1')->update(['start_date' => '2026-10-01']);
        $this->assertCount(0, $this->journals()->periodsFor($assignment, '2026-09-15'));

        // Several: overlapping periods are a configuration problem the user
        // must see. PostgreSQL happily stores overlapping ranges.
        $this->period('Semester 1')->update(['start_date' => '2026-07-01']);
        $this->period('Semester 2')->update(['start_date' => '2026-09-01']);
        $this->assertCount(2, $this->journals()->periodsFor($assignment, '2026-09-15'));
    }

    // -------------------------------------------------------- conducted by

    public function test_the_conductor_may_differ_from_the_assigned_teacher(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $budi = $this->staff('budi');

        $journal = $this->journals()->create(
            $assignment, $this->activeScope('C'), $this->period('Semester 1'),
            '2026-09-15', $budi, $this->content(),
        );

        $this->assertSame($budi->id, $journal->conducted_by_staff_id);
        $this->assertTrue($journal->wasSubstituted());
        // And the assignment is untouched: no reassignment side-effect.
        $this->assertSame($this->staff('sarah')->id, $assignment->fresh()->staff_id);
        $this->assertTrue($assignment->fresh()->isActive());
    }

    public function test_a_conductor_who_has_since_left_may_still_be_recorded(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $budi = $this->staff('budi');
        // Budi left after the lesson. His having taught it is still a fact.
        $budi->update(['status' => 'terminated']);

        $journal = $this->journals()->create(
            $assignment, $this->activeScope('C'), $this->period('Semester 1'),
            '2026-09-15', $budi->fresh(), $this->content(),
        );

        $this->assertSame($budi->id, $journal->conducted_by_staff_id);
        $this->assertSame('terminated', $journal->conductedBy->status);
    }

    // ------------------------------------------------------------ module

    private function readyModule(?ClassSubject $assignment = null): TeachingModule
    {
        $assignment ??= $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $module = $this->modules()->create($assignment, $this->activeScope('C'), [
            'title' => 'Pecahan', 'planned_activity' => 'Kertas lipat.',
        ]);
        $this->modules()->linkObjective($module, $this->objective(1));
        $this->modules()->linkObjective($module->fresh(), $this->objective(2));

        return $this->modules()->markReady($module->fresh());
    }

    public function test_a_journal_may_cite_a_ready_module(): void
    {
        $this->seedReferenceData();
        $module = $this->readyModule();

        $journal = $this->journals()->linkModule($this->journal(), $module);

        $this->assertSame($module->id, $journal->teaching_module_id);
    }

    public function test_a_journal_may_never_cite_a_draft_module(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $draft = $this->modules()->create($assignment, $this->activeScope('C'), [
            'title' => 'Half written', 'planned_activity' => 'x',
        ]);

        try {
            $this->journals()->linkModule($this->journal(), $draft);
            $this->fail('a journal cited a draft module');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('still a draft', $e->errors()['teaching_module_id'][0]);
        }
    }

    public function test_a_module_from_another_roster_is_refused(): void
    {
        $this->seedReferenceData();
        $greenAssignment = $this->groupAssignmentFor('Green', 'Eng', 'lena');
        $greenModule = $this->modules()->create($greenAssignment, $this->activeEnglishScope('Green'), [
            'title' => 'Colours', 'planned_activity' => 'Flashcards.',
        ]);
        $this->modules()->linkObjective($greenModule, $this->objectiveIn($this->activeEnglishScope('Green'), 'Eng', 'E1', 1));
        $greenModule = $this->modules()->markReady($greenModule->fresh());

        try {
            $this->journals()->linkModule($this->journal(), $greenModule);
            $this->fail('a Year 5A Mathematics journal cited a Green English module');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('written for Green A', $e->errors()['teaching_module_id'][0]);
        }
    }

    public function test_the_database_refuses_an_incompatible_module_even_in_raw_sql(): void
    {
        $this->seedReferenceData();
        $journal = $this->journal();

        $greenAssignment = $this->groupAssignmentFor('Green', 'Eng', 'lena');
        $greenModule = $this->modules()->create($greenAssignment, $this->activeEnglishScope('Green'), [
            'title' => 'Colours', 'planned_activity' => 'Flashcards.',
        ]);

        $this->expectException(QueryException::class);

        DB::table('daily_journals')->where('id', $journal->id)
            ->update(['teaching_module_id' => $greenModule->id]);
    }

    /** The successor case: Eka deliberately teaches Sarah's historical plan. */
    public function test_a_successor_may_cite_a_predecessors_compatible_module(): void
    {
        $this->seedReferenceData();
        $sarahAssignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $sarahModule = $this->readyModule($sarahAssignment);

        $this->closeAssignment($sarahAssignment, '2026-11-30');
        $ekaAssignment = $this->assignmentFor('Year 5A', 'Maths', 'eka', '2026-12-01');

        $journal = $this->journals()->create(
            $ekaAssignment, $this->activeScope('C'), $this->period('Semester 1'),
            '2026-12-05', $this->staff('eka'), $this->content(), module: $sarahModule,
        );

        // Authorship stays Sarah's; the journal is Eka's.
        $this->assertSame($sarahModule->id, $journal->teaching_module_id);
        $this->assertSame($sarahAssignment->id, $sarahModule->fresh()->class_subject_id);
        $this->assertSame($ekaAssignment->id, $journal->class_subject_id);
        $this->assertSame($this->staff('eka')->id, $journal->conducted_by_staff_id);
    }

    // ----------------------------------------------------------- prosem

    /** @return array{0: ClassSubject, 1: SemesterProgrammeItem} */
    private function scheduleFor(string $className = 'Year 5A', string $gradeName = 'Year 5'): array
    {
        $assignment = $this->assignmentFor($className, 'Maths', $className === 'Year 5A' ? 'sarah' : 'eka');

        $annual = $this->programmes()->createForClass($this->class($gradeName, $className), $this->subject('Maths'), $this->pathway());
        $item = $this->programmes()->addItem($annual, $this->pathwayItem(1), $this->period('Semester 1'), 8);
        $annual = $this->programmes()->activate($annual->fresh());

        $semester = app(SemesterProgrammeService::class)->create($annual, $this->period('Semester 1'));
        $slot = app(SemesterProgrammeService::class)->addSlot($semester, $item, [
            'week_label' => 'Minggu 3',
            'planned_start_date' => '2026-08-17',
            'planned_end_date' => '2026-08-21',
            'planned_lesson_periods' => 8,
        ]);

        return [$assignment, $slot];
    }

    public function test_a_journal_may_fulfil_a_scheduled_slot(): void
    {
        $this->seedReferenceData();
        [, $slot] = $this->scheduleFor();

        $journal = $this->journals()->linkSlot($this->journal(), $slot);

        $this->assertSame($slot->id, $journal->semester_programme_item_id);
    }

    public function test_a_journal_needs_no_slot_at_all(): void
    {
        $this->seedReferenceData();
        $journal = $this->journal();

        $this->assertNull($journal->semester_programme_item_id);

        $this->journals()->finalize($journal);

        $this->assertTrue($journal->fresh()->isFinalized());
    }

    public function test_the_actual_date_may_differ_from_the_planned_slot_dates(): void
    {
        $this->seedReferenceData();
        [, $slot] = $this->scheduleFor();

        // Planned for 17-21 August; actually taught on 15 September. Still in
        // Semester 1, so still valid -- teaching slips.
        $journal = $this->journals()->linkSlot($this->journal([], '2026-09-15'), $slot);

        $this->assertSame($slot->id, $journal->semester_programme_item_id);
        $this->assertSame('2026-09-15', $journal->journal_date->toDateString());
    }

    public function test_a_slot_from_another_roster_is_refused(): void
    {
        $this->seedReferenceData();
        [, $foreignSlot] = $this->scheduleFor('Year 6A', 'Year 6');

        try {
            $this->journals()->linkSlot($this->journal(), $foreignSlot);
            $this->fail('a journal fulfilled another roster schedule slot');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Year 6A', $e->errors()['semester_programme_item_id'][0]);
        }
    }

    public function test_a_slot_scheduled_in_another_period_is_refused(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');

        $annual = $this->programmes()->createForClass($this->class('Year 5', 'Year 5A'), $this->subject('Maths'), $this->pathway());
        $item = $this->programmes()->addItem($annual, $this->pathwayItem(1), $this->period('Semester 2'), 8);
        $annual = $this->programmes()->activate($annual->fresh());
        $semester = app(SemesterProgrammeService::class)->create($annual, $this->period('Semester 2'));
        $slot = app(SemesterProgrammeService::class)->addSlot($semester, $item, ['planned_lesson_periods' => 8]);

        $this->expectException(ValidationException::class);

        $this->journals()->linkSlot($this->journal(), $slot);
    }

    // -------------------------------------------------------- objectives

    public function test_actual_objectives_are_independent_of_the_planned_ones(): void
    {
        $this->seedReferenceData();
        $module = $this->readyModule();       // plans TP 1 and TP 2
        $journal = $this->journals()->linkModule($this->journal(), $module);

        // Only TP 1 was actually reached.
        $this->journals()->linkObjective($journal, $this->objective(1));

        $this->assertSame(2, $module->objectiveLinks()->count());
        $this->assertSame(1, $journal->fresh()->objectiveLinks()->count());
        $this->assertSame(['TP 1'], $journal->fresh()->objectives()->pluck('objective_text')->all());
    }

    public function test_an_objective_outside_the_journal_scope_is_refused(): void
    {
        $this->seedReferenceData();
        $journal = $this->journal();
        $phaseD = $this->objectiveIn($this->activeScope('D'), 'Maths', 'Phase D objective', 1);

        $this->expectException(ValidationException::class);

        $this->journals()->linkObjective($journal, $phaseD);
    }

    public function test_the_database_refuses_a_cross_scope_objective_link(): void
    {
        $this->seedReferenceData();
        $journal = $this->journal();
        $phaseD = $this->objectiveIn($this->activeScope('D'), 'Maths', 'Phase D objective', 1);

        $this->expectException(QueryException::class);

        DB::table('daily_journal_learning_objective')->insert([
            'daily_journal_id' => $journal->id,
            'learning_objective_id' => $phaseD->id,
            'curriculum_scope_id' => $phaseD->curriculum_scope_id,
            'subject_id' => $phaseD->subject_id,
        ]);
    }

    public function test_finalization_refuses_a_draft_objective(): void
    {
        $this->seedReferenceData();
        $journal = $this->journal();
        $objective = $this->objective(1);
        $this->journals()->linkObjective($journal, $objective);
        $objective->update(['status' => 'draft']);

        $this->expectException(ValidationException::class);

        $this->journals()->finalize($journal->fresh());
    }

    /** Historical backfill: a once-used objective may since have been archived. */
    public function test_finalization_accepts_an_archived_objective(): void
    {
        $this->seedReferenceData();
        $journal = $this->journal();
        $objective = $this->objective(1);
        $this->journals()->linkObjective($journal, $objective);
        $objective->update(['status' => 'archived']);

        $this->journals()->finalize($journal->fresh());

        $this->assertTrue($journal->fresh()->isFinalized());
    }

    // ------------------------------------------------------- assessments

    private function assessment(ClassSubject $assignment, string $name = 'Kuis Pecahan'): Assessment
    {
        return Assessment::create([
            'class_subject_id' => $assignment->id,
            'academic_period_id' => $this->period('Semester 1')->id,
            'name' => $name,
            'max_score' => 100,
            'assessment_date' => '2026-09-15',
        ]);
    }

    public function test_several_assessments_may_be_recorded_as_used(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $journal = $this->journal();

        $this->journals()->linkAssessment($journal, $this->assessment($assignment, 'Kuis 1'));
        $this->journals()->linkAssessment($journal->fresh(), $this->assessment($assignment, 'Kuis 2'));

        $this->assertSame(2, $journal->fresh()->assessmentLinks()->count());
        // And no score has been copied anywhere.
        $this->assertFalse(Schema::hasColumn('daily_journal_assessment', 'score'));
    }

    public function test_an_assessment_from_another_assignment_is_refused(): void
    {
        $this->seedReferenceData();
        $journal = $this->journal();
        $other = $this->assignmentFor('Year 6A', 'Maths', 'eka');

        try {
            $this->journals()->linkAssessment($journal, $this->assessment($other, 'Year 6 quiz'));
            $this->fail('a journal cited another assignment assessment');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('different teaching assignment', $e->errors()['assessment_id'][0]);
        }
    }

    public function test_the_database_refuses_a_cross_assignment_assessment_link(): void
    {
        $this->seedReferenceData();
        $journal = $this->journal();
        $other = $this->assignmentFor('Year 6A', 'Maths', 'eka');
        $foreign = $this->assessment($other, 'Year 6 quiz');

        $this->expectException(QueryException::class);

        DB::table('daily_journal_assessment')->insert([
            'daily_journal_id' => $journal->id,
            'assessment_id' => $foreign->id,
            'class_subject_id' => $journal->class_subject_id,
        ]);
    }

    // --------------------------------------------------------- lifecycle

    public function test_finalization_requires_the_actual_record_to_be_present(): void
    {
        $this->seedReferenceData();
        $journal = $this->journal();

        DB::table('daily_journals')->where('id', $journal->id)->update(['actual_activity' => '   ']);

        $this->expectException(ValidationException::class);

        $this->journals()->finalize($journal->fresh());
    }

    public function test_a_finalized_journal_is_frozen_to_its_teacher_but_correctable_by_a_manager(): void
    {
        $this->seedReferenceData();
        $journal = $this->journals()->finalize($this->journal());
        $sarah = $this->staff('sarah')->user->fresh();
        $manager = $this->userWithRole('principal');

        $this->assertFalse($sarah->can('update', $journal));
        $this->assertTrue($manager->can('update', $journal));
        $this->assertFalse($sarah->can('delete', $journal));
        $this->assertFalse($manager->can('delete', $journal));
    }

    public function test_a_finalized_journal_is_never_deleted(): void
    {
        $this->seedReferenceData();
        $journal = $this->journals()->finalize($this->journal());

        try {
            $this->journals()->delete($journal);
            $this->fail('a finalized journal was deleted');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('never deleted', $e->errors()['status'][0]);
        }

        $this->expectException(LogicException::class);
        $journal->delete();
    }

    public function test_a_draft_journal_deletes_with_its_links(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $journal = $this->journal();
        $this->journals()->linkObjective($journal, $this->objective(1));
        $this->journals()->linkAssessment($journal->fresh(), $this->assessment($assignment));

        $this->journals()->delete($journal->fresh());

        $this->assertSame(0, DailyJournal::count());
        $this->assertSame(0, DailyJournalLearningObjective::count());
        $this->assertSame(0, DailyJournalAssessment::count());
    }

    // ---------------------------------------------------------- backfill

    public function test_a_teacher_may_not_open_a_journal_on_a_closed_assignment(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $this->closeAssignment($assignment, '2026-10-31');

        $sarah = $this->staff('sarah')->user->fresh();

        $this->assertFalse($sarah->can('createFor', [DailyJournal::class, $assignment->fresh()]));

        $this->expectException(ValidationException::class);

        $this->journals()->create(
            $assignment->fresh(), $this->activeScope('C'), $this->period('Semester 1'),
            '2026-09-15', $this->staff('sarah'), $this->content(),
        );
    }

    public function test_a_manager_may_backfill_inside_the_closed_assignment_dates(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $this->closeAssignment($assignment, '2026-10-31');
        $manager = $this->userWithRole('principal');

        $this->assertTrue($manager->can('backfillFor', [DailyJournal::class, $assignment->fresh()]));

        $journal = $this->journals()->create(
            $assignment->fresh(), $this->activeScope('C'), $this->period('Semester 1'),
            '2026-09-15', $this->staff('budi'), $this->content(), historicalBackfill: true,
        );

        $this->assertSame($assignment->id, $journal->class_subject_id);
        $this->assertSame($this->staff('budi')->id, $journal->conducted_by_staff_id);
    }

    public function test_a_backfill_outside_the_closed_assignment_dates_is_refused(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $this->closeAssignment($assignment, '2026-10-31');

        $this->expectException(ValidationException::class);

        $this->journals()->create(
            $assignment->fresh(), $this->activeScope('C'), $this->period('Semester 1'),
            '2026-11-15', $this->staff('sarah'), $this->content(), historicalBackfill: true,
        );
    }

    public function test_a_successor_reads_but_does_not_write_predecessor_history(): void
    {
        $this->seedReferenceData();
        $sarahAssignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $journal = $this->journals()->finalize($this->journal());

        $this->closeAssignment($sarahAssignment, '2026-11-30');
        $this->assignmentFor('Year 5A', 'Maths', 'eka', '2026-12-01');

        $eka = $this->staff('eka')->user->fresh();

        $this->assertTrue($eka->can('view', $journal));
        $this->assertFalse($eka->can('update', $journal));
    }

    // -------------------------------------------------- advisory numbers

    public function test_meeting_number_is_advisory_and_never_unique(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');

        $this->assertSame(1, $this->journals()->suggestMeetingNumber($assignment, $this->period('Semester 1')));

        $this->journal(['meeting_number' => 3], '2026-09-15');
        $this->journal(['meeting_number' => 3], '2026-09-16');

        $this->assertSame(2, DailyJournal::where('meeting_number', 3)->count());
        $this->assertSame(4, $this->journals()->suggestMeetingNumber($assignment, $this->period('Semester 1')));
    }

    public function test_journals_order_by_date_not_by_meeting_number(): void
    {
        $this->seedReferenceData();
        $this->journal(['meeting_number' => 9], '2026-09-10');
        $this->journal(['meeting_number' => 1], '2026-09-20');

        $dates = DailyJournal::chronological()->pluck('journal_date')
            ->map(fn ($d) => $d->toDateString())->all();

        $this->assertSame(['2026-09-10', '2026-09-20'], $dates);
    }

    public function test_actual_lesson_periods_must_be_positive_or_blank(): void
    {
        $this->seedReferenceData();

        $this->assertNull($this->journal()->actual_lesson_periods);

        try {
            $this->journal(['actual_lesson_periods' => 0], '2026-09-16');
            $this->fail('a zero lesson-period figure was accepted');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('at least 1', $e->errors()['actual_lesson_periods'][0]);
        }

        $this->assertSame(2, $this->journal(['actual_lesson_periods' => 2], '2026-09-17')->actual_lesson_periods);
    }

    // ------------------------------------------------------------ audit

    public function test_journal_and_link_writes_are_audited(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');

        $created = $this->auditCount(DailyJournal::class, 'created');
        $linked = $this->auditCount(DailyJournalLearningObjective::class, 'created');
        $assessed = $this->auditCount(DailyJournalAssessment::class, 'created');
        $updated = $this->auditCount(DailyJournal::class, 'updated');

        $journal = $this->journal();
        $this->journals()->linkObjective($journal, $this->objective(1));
        $this->journals()->linkAssessment($journal->fresh(), $this->assessment($assignment));
        $this->journals()->finalize($journal->fresh());

        $this->assertSame($created + 1, $this->auditCount(DailyJournal::class, 'created'));
        $this->assertSame($linked + 1, $this->auditCount(DailyJournalLearningObjective::class, 'created'));
        $this->assertSame($assessed + 1, $this->auditCount(DailyJournalAssessment::class, 'created'));
        // linkAssessment writes nothing on the journal itself; finalize does.
        $this->assertSame($updated + 1, $this->auditCount(DailyJournal::class, 'updated'));
    }

    // --------------------------------------------------------- boundary

    public function test_a_journal_stores_no_attendance_and_no_planned_or_score_facts(): void
    {
        $this->seedReferenceData();

        foreach ([
            'attendance_id', 'attendance_record_id', 'present_count', 'absent_count',
            'planned_lesson_periods', 'week_label', 'planned_start_date', 'planned_end_date',
            'score', 'max_score', 'planned_activity',
        ] as $column) {
            $this->assertFalse(
                Schema::hasColumn('daily_journals', $column),
                "{$column} belongs to another layer and must not be copied onto a journal"
            );
        }

        // And no attendance table gained a journal column either.
        $this->assertFalse(Schema::hasColumn('attendance', 'daily_journal_id'));
    }

    public function test_no_session_attendance_engine_was_introduced(): void
    {
        $this->seedReferenceData();

        foreach (['session_attendance', 'subject_attendance', 'lesson_attendance'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "{$table} belongs to a later phase");
        }
    }
}
