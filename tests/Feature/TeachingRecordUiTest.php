<?php

namespace Tests\Feature;

use App\Livewire\Teaching\JournalShow;
use App\Livewire\Teaching\Journals;
use App\Livewire\Teaching\ModuleShow;
use App\Livewire\Teaching\Modules;
use App\Livewire\Teaching\MyAssignments;
use App\Models\ClassSubject;
use App\Models\CurriculumScope;
use App\Models\DailyJournal;
use App\Models\LearningObjective;
use App\Models\TeachingModule;
use App\Services\DailyJournalService;
use App\Services\TeachingModuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\BuildsPlanningFixtures;
use Tests\TestCase;

/**
 * The module and journal screens, and how they hang off the teacher workspace.
 */
class TeachingRecordUiTest extends TestCase
{
    use BuildsPlanningFixtures;
    use RefreshDatabase;

    private function activeScope(string $phaseCode): CurriculumScope
    {
        $scope = $this->scopeFor($phaseCode);
        $this->restoreActive($this->curriculum());

        return $scope;
    }

    private function objective(int $order = 1): LearningObjective
    {
        return $this->objectiveIn($this->activeScope('C'), 'Maths', "TP {$order}", $order);
    }

    // ----------------------------------------------------------- modules

    public function test_a_teacher_can_write_a_module_from_the_screen(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $scope = $this->activeScope('C');

        Livewire::actingAs($this->staff('sarah')->user->fresh())
            ->test(Modules::class, ['classSubject' => $assignment])
            ->assertSet('curriculum_scope_id', (string) $scope->id) // exactly one candidate
            ->set('showCreate', true)
            ->set('title', 'Pecahan Senilai')
            ->set('planned_activity', 'Kertas lipat lalu diskusi.')
            ->call('create')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertSame(1, TeachingModule::count());
        $this->assertSame($assignment->id, TeachingModule::first()->class_subject_id);
    }

    public function test_the_screen_does_not_preselect_a_scope_when_several_are_eligible(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $this->activeScope('C');

        $second = \App\Models\Curriculum::create([
            'code' => 'KM', 'version' => '2', 'name' => 'Kurikulum Merdeka rev',
            'effective_from' => '2026-07-01', 'status' => 'draft',
        ]);
        app(\App\Services\CurriculumScopeService::class)
            ->addPhase($second, \App\Models\LearningPhase::where('code', 'C')->firstOrFail());
        $second->update(['status' => 'active']);

        Livewire::actingAs($this->staff('sarah')->user->fresh())
            ->test(Modules::class, ['classSubject' => $assignment])
            ->assertSet('curriculum_scope_id', '')
            ->set('showCreate', true)
            ->assertSee('More than one curriculum version');
    }

    public function test_a_closed_assignment_offers_no_new_module(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $this->closeAssignment($assignment, '2026-10-31');

        Livewire::actingAs($this->userWithRole('principal'))
            ->test(Modules::class, ['classSubject' => $assignment->fresh()])
            ->assertViewHas('canCreate', false)
            ->assertSee('no new one may be written against it');
    }

    public function test_the_module_screen_marks_ready_and_then_freezes_the_plan(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $module = app(TeachingModuleService::class)->create($assignment, $this->activeScope('C'), [
            'title' => 'Pecahan', 'planned_activity' => 'Kertas lipat.',
        ]);
        $teacher = $this->staff('sarah')->user->fresh();

        Livewire::actingAs($teacher)
            ->test(ModuleShow::class, ['teachingModule' => $module])
            ->set('learning_objective_id', (string) $this->objective(1)->id)
            ->call('addObjective')
            ->assertHasNoErrors()
            ->call('markReady')
            ->assertHasNoErrors();

        $this->assertTrue($module->fresh()->isReady());

        Livewire::actingAs($teacher)
            ->test(ModuleShow::class, ['teachingModule' => $module->fresh()])
            ->set('plan.planned_activity', 'rewritten')
            ->call('savePlan')
            ->assertHasErrors('status');

        $this->assertSame('Kertas lipat.', $module->fresh()->planned_activity);
    }

    public function test_the_module_screen_reports_a_missing_objective_rather_than_throwing(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $module = app(TeachingModuleService::class)->create($assignment, $this->activeScope('C'), [
            'title' => 'Pecahan', 'planned_activity' => 'Kertas lipat.',
        ]);

        Livewire::actingAs($this->staff('sarah')->user->fresh())
            ->test(ModuleShow::class, ['teachingModule' => $module])
            ->call('markReady')
            ->assertHasErrors('objectives');

        $this->assertTrue($module->fresh()->isDraft());
    }

    // ---------------------------------------------------------- journals

    public function test_the_journal_screen_offers_the_period_from_the_date(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');

        Livewire::actingAs($this->staff('sarah')->user->fresh())
            ->test(Journals::class, ['classSubject' => $assignment])
            ->assertSet('academic_period_id', '')
            ->set('showCreate', true)
            ->set('journal_date', '2026-09-15')
            ->assertSet('academic_period_id', (string) $this->period('Semester 1')->id);
    }

    public function test_the_journal_screen_reports_a_date_no_period_covers(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $this->period('Semester 1')->update(['start_date' => '2026-10-01']);

        Livewire::actingAs($this->staff('sarah')->user->fresh())
            ->test(Journals::class, ['classSubject' => $assignment])
            ->set('showCreate', true)
            ->set('journal_date', '2026-09-15')
            ->assertSet('academic_period_id', '')
            ->assertSee('No reporting period covers that date');
    }

    public function test_a_teacher_records_a_session_and_finalizes_it(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $scope = $this->activeScope('C');
        $teacher = $this->staff('sarah')->user->fresh();

        Livewire::actingAs($teacher)
            ->test(Journals::class, ['classSubject' => $assignment])
            ->set('showCreate', true)
            ->set('journal_date', '2026-09-15')
            ->set('curriculum_scope_id', (string) $scope->id)
            ->set('topic', 'Pecahan senilai')
            ->set('actual_activity', 'Dua kelompok belum selesai.')
            ->call('create')
            ->assertHasNoErrors()
            ->assertRedirect();

        $journal = DailyJournal::firstOrFail();
        $this->assertSame($this->staff('sarah')->id, $journal->conducted_by_staff_id);

        Livewire::actingAs($teacher)
            ->test(JournalShow::class, ['dailyJournal' => $journal])
            ->call('finalize')
            ->assertHasNoErrors();

        $this->assertTrue($journal->fresh()->isFinalized());
    }

    public function test_the_journal_screen_records_a_substitute_without_touching_the_assignment(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $budi = $this->staff('budi');
        $scope = $this->activeScope('C');

        Livewire::actingAs($this->staff('sarah')->user->fresh())
            ->test(Journals::class, ['classSubject' => $assignment])
            ->assertSet('conducted_by_staff_id', (string) $assignment->staff_id)
            ->set('showCreate', true)
            ->set('journal_date', '2026-09-15')
            ->set('curriculum_scope_id', (string) $scope->id)
            ->set('conducted_by_staff_id', (string) $budi->id)
            ->set('topic', 'Pecahan')
            ->set('actual_activity', 'Budi mengajar.')
            ->call('create')
            ->assertHasNoErrors();

        $journal = DailyJournal::firstOrFail();
        $this->assertSame($budi->id, $journal->conducted_by_staff_id);
        $this->assertSame($this->staff('sarah')->id, $assignment->fresh()->staff_id);
    }

    public function test_a_finalized_journal_is_read_only_to_its_teacher_on_screen(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $journal = app(DailyJournalService::class)->create(
            $assignment, $this->activeScope('C'), $this->period('Semester 1'),
            '2026-09-15', $this->staff('sarah'),
            ['topic' => 'Pecahan', 'actual_activity' => 'Selesai.'],
        );
        app(DailyJournalService::class)->finalize($journal);

        Livewire::actingAs($this->staff('sarah')->user->fresh())
            ->test(JournalShow::class, ['dailyJournal' => $journal->fresh()])
            ->assertViewHas('canEdit', false)
            ->assertSee('only a manager may correct it', false);

        Livewire::actingAs($this->userWithRole('principal'))
            ->test(JournalShow::class, ['dailyJournal' => $journal->fresh()])
            ->assertViewHas('canEdit', true);
    }

    public function test_a_group_journal_says_no_attendance_exists_rather_than_showing_zero(): void
    {
        $this->seedReferenceData();
        $assignment = $this->groupAssignmentFor('Green', 'Eng', 'lena');
        $scope = $this->englishScope('Green');
        $this->restoreActive($this->englishCurriculum());

        $journal = app(DailyJournalService::class)->create(
            $assignment, $scope, $this->period('Semester 1'),
            '2026-09-15', $this->staff('lena'),
            ['topic' => 'Colours', 'actual_activity' => 'Flashcards.'],
        );

        Livewire::actingAs($this->staff('lena')->user->fresh())
            ->test(JournalShow::class, ['dailyJournal' => $journal])
            ->assertViewHas('attendance', null)
            ->assertSee('teaching groups have no attendance of their own yet');
    }

    // -------------------------------------------------- teacher workspace

    public function test_the_workspace_links_to_modules_and_journals(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');

        Livewire::actingAs($this->staff('sarah')->user->fresh())
            ->test(MyAssignments::class)
            ->assertSee(route('teaching.modules.index', $assignment), false)
            ->assertSee(route('teaching.journal.index', $assignment), false);
    }

    public function test_a_successor_sees_both_assignments_history_separately(): void
    {
        $this->seedReferenceData();
        $sarahAssignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        app(TeachingModuleService::class)->create($sarahAssignment, $this->activeScope('C'), [
            'title' => 'Sarah plan', 'planned_activity' => 'x',
        ]);

        $this->closeAssignment($sarahAssignment, '2026-11-30');
        $ekaAssignment = $this->assignmentFor('Year 5A', 'Maths', 'eka', '2026-12-01');

        $eka = $this->staff('eka')->user->fresh();

        // Eka's own card points at her own assignment's records...
        Livewire::actingAs($eka)
            ->test(MyAssignments::class)
            ->assertSee(route('teaching.modules.index', $ekaAssignment), false);

        // ...and Sarah's module list is readable but offers her nothing new.
        Livewire::actingAs($eka)
            ->test(Modules::class, ['classSubject' => $sarahAssignment->fresh()])
            ->assertSee('Sarah plan')
            ->assertViewHas('canCreate', false);
    }
}
