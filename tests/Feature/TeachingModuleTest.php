<?php

namespace Tests\Feature;

use App\Models\ClassSubject;
use App\Models\CurriculumScope;
use App\Models\LearningObjective;
use App\Models\SemesterProgrammeItem;
use App\Models\TeachingModule;
use App\Models\TeachingModuleLearningObjective;
use App\Services\SemesterProgrammeService;
use App\Services\TeachingModuleService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Feature\Concerns\BuildsPlanningFixtures;
use Tests\TestCase;

/**
 * Modul Ajar: instructional design anchored to a teaching assignment.
 *
 * The tests that matter most are the ones proving this layer stores nothing
 * that already exists upstream, and that authorship does NOT change hands at a
 * teacher handover -- the deliberate opposite of Prota and Prosem.
 */
class TeachingModuleTest extends TestCase
{
    use BuildsPlanningFixtures;
    use RefreshDatabase;

    private function modules(): TeachingModuleService
    {
        return app(TeachingModuleService::class);
    }

    /**
     * A scope whose curriculum is left ACTIVE.
     *
     * The shared fixture flips a curriculum to draft to add a scope to it and
     * leaves it there; planning requires an active version, so these restore it.
     */
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
            'title' => 'Pecahan Senilai',
            'planned_activity' => 'Kerja kelompok dengan kertas lipat, lalu diskusi kelas.',
        ];
    }

    private function draftModule(?ClassSubject $assignment = null): TeachingModule
    {
        $assignment ??= $this->assignmentFor('Year 5A', 'Maths', 'sarah');

        return $this->modules()->create($assignment, $this->activeScope('C'), $this->content());
    }

    private function objective(int $order = 1, string $subject = 'Maths'): LearningObjective
    {
        return $this->objectiveIn($this->activeScope('C'), $subject, "TP {$order}", $order);
    }

    private function readyModule(): TeachingModule
    {
        $module = $this->draftModule();
        $this->modules()->linkObjective($module, $this->objective(1));

        return $this->modules()->markReady($module->fresh());
    }

    // ------------------------------------------------------------ anchor

    public function test_a_class_backed_module_mirrors_its_assignment(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');

        $module = $this->modules()->create($assignment, $this->activeScope('C'), $this->content());

        $this->assertSame($assignment->id, $module->class_subject_id);
        $this->assertSame($assignment->class_id, $module->class_id);
        $this->assertNull($module->teaching_group_id);
        $this->assertSame($assignment->subject_id, $module->subject_id);
        $this->assertTrue($module->isDraft());
        // Teacher identity is NOT duplicated here.
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('teaching_modules', 'staff_id'));
    }

    public function test_a_group_backed_module_mirrors_its_teaching_group(): void
    {
        $this->seedReferenceData();
        $assignment = $this->groupAssignmentFor('Green', 'Eng', 'lena');

        $module = $this->modules()->create($assignment, $this->activeEnglishScope('Green'), $this->content());

        $this->assertSame($assignment->teaching_group_id, $module->teaching_group_id);
        $this->assertNull($module->class_id);
        $this->assertSame('Teaching Group', $module->rosterLabel());
    }

    public function test_the_anchor_cannot_be_changed_after_creation(): void
    {
        $this->seedReferenceData();
        $module = $this->draftModule();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('fixed at creation');

        $module->update(['subject_id' => $this->subject('Eng')->id]);
    }

    public function test_the_database_refuses_a_module_whose_mirror_contradicts_its_assignment(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $otherClass = $this->class('Year 6', 'Year 6A');

        $this->expectException(QueryException::class);

        DB::table('teaching_modules')->insert([
            'class_subject_id' => $assignment->id,
            'class_id' => $otherClass->id, // lying about the roster
            'teaching_group_id' => null,
            'subject_id' => $assignment->subject_id,
            'curriculum_scope_id' => $this->activeScope('C')->id,
            'title' => 'Forged',
            'planned_activity' => 'x',
            'status' => 'draft',
        ]);
    }

    public function test_the_database_refuses_both_roster_sources_at_once(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');

        $this->expectException(QueryException::class);

        DB::table('teaching_modules')->insert([
            'class_subject_id' => $assignment->id,
            'class_id' => $assignment->class_id,
            'teaching_group_id' => $this->group('Green')->id,
            'subject_id' => $assignment->subject_id,
            'curriculum_scope_id' => $this->activeScope('C')->id,
            'title' => 'Forged',
            'planned_activity' => 'x',
            'status' => 'draft',
        ]);
    }

    // ------------------------------------------------------------- scope

    public function test_eligible_scopes_are_resolved_from_the_grade_phase_mapping(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $this->activeScope('C');
        $this->activeScope('D');
        $this->restoreActive($this->curriculum());

        $eligible = $this->modules()->eligibleScopes($assignment);

        $this->assertCount(1, $eligible);
        $this->assertSame('Phase C', $eligible->first()->displayName());
    }

    public function test_several_eligible_scopes_are_all_returned_and_never_silently_reduced(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $first = $this->activeScope('C');
        $this->restoreActive($this->curriculum());

        // A second active curriculum version covering the same phase.
        $second = \App\Models\Curriculum::create([
            'code' => 'KM', 'version' => '2', 'name' => 'Kurikulum Merdeka rev',
            'effective_from' => '2026-07-01', 'status' => 'draft',
        ]);
        $secondScope = app(\App\Services\CurriculumScopeService::class)
            ->addPhase($second, \App\Models\LearningPhase::where('code', 'C')->firstOrFail());
        $second->update(['status' => 'active']);

        $eligible = $this->modules()->eligibleScopes($assignment);

        $this->assertCount(2, $eligible);
        $this->assertEqualsCanonicalizing([$first->id, $secondScope->id], $eligible->pluck('id')->all());
    }

    public function test_a_class_with_no_phase_mapping_yields_no_eligible_scope(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        \App\Models\LearningPhaseGrade::query()->delete();

        $this->assertTrue($this->modules()->eligibleScopes($assignment)->isEmpty());

        $this->expectException(ValidationException::class);

        $this->modules()->create($assignment, $this->activeScope('C'), $this->content());
    }

    public function test_a_class_cannot_plan_against_the_wrong_phase(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $phaseD = $this->activeScope('D');
        $this->restoreActive($this->curriculum());

        try {
            $this->modules()->create($assignment, $phaseD, $this->content());
            $this->fail('a Year 5 module was created against Phase D');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('not in Phase D', $e->errors()['curriculum_scope_id'][0]);
        }

        $this->assertSame(0, TeachingModule::count());
    }

    public function test_a_class_cannot_plan_against_an_english_level_scope(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');

        $this->expectException(ValidationException::class);

        $this->modules()->create($assignment, $this->activeEnglishScope('Green'), $this->content());
    }

    public function test_a_teaching_group_cannot_plan_against_another_level(): void
    {
        $this->seedReferenceData();
        $assignment = $this->groupAssignmentFor('Green', 'Eng', 'lena');
        $blue = $this->activeEnglishScope('Blue');
        $this->restoreActive($this->englishCurriculum());

        $this->expectException(ValidationException::class);

        $this->modules()->create($assignment, $blue, $this->content());
    }

    public function test_a_draft_curriculum_cannot_be_planned_against(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $scope = $this->activeScope('C');
        $this->curriculum()->update(['status' => 'draft']);

        $this->expectException(ValidationException::class);

        $this->modules()->create($assignment, $scope, $this->content());
    }

    // -------------------------------------------------------- objectives

    public function test_several_objectives_may_be_linked(): void
    {
        $this->seedReferenceData();
        $module = $this->draftModule();

        $this->modules()->linkObjective($module, $this->objective(1));
        $this->modules()->linkObjective($module, $this->objective(2));

        $this->assertSame(2, $module->fresh()->objectiveLinks()->count());
        $this->assertSame(['TP 1', 'TP 2'], $module->fresh()->objectives()->pluck('objective_text')->all());
    }

    public function test_the_same_objective_cannot_be_linked_twice(): void
    {
        $this->seedReferenceData();
        $module = $this->draftModule();
        $this->modules()->linkObjective($module, $this->objective(1));

        $this->expectException(ValidationException::class);

        $this->modules()->linkObjective($module->fresh(), $this->objective(1));
    }

    public function test_a_cross_phase_objective_is_refused_by_the_service(): void
    {
        $this->seedReferenceData();
        $module = $this->draftModule();
        $phaseD = $this->objectiveIn($this->activeScope('D'), 'Maths', 'Phase D objective', 1);
        $this->restoreActive($this->curriculum());

        $this->expectException(ValidationException::class);

        $this->modules()->linkObjective($module, $phaseD);
    }

    public function test_a_cross_subject_objective_is_refused_by_the_service(): void
    {
        $this->seedReferenceData();
        $module = $this->draftModule();
        $english = $this->objectiveIn($this->activeScope('C'), 'Eng', 'Phase C English', 9);
        $this->restoreActive($this->curriculum());

        $this->expectException(ValidationException::class);

        $this->modules()->linkObjective($module, $english);
    }

    public function test_the_database_refuses_a_cross_phase_link_even_with_a_falsified_anchor(): void
    {
        $this->seedReferenceData();
        $module = $this->draftModule();
        $phaseD = $this->objectiveIn($this->activeScope('D'), 'Maths', 'Phase D objective', 1);
        $this->restoreActive($this->curriculum());

        $this->expectException(QueryException::class);

        // Claim the objective's scope so the module side of the key breaks.
        DB::table('teaching_module_learning_objective')->insert([
            'teaching_module_id' => $module->id,
            'learning_objective_id' => $phaseD->id,
            'curriculum_scope_id' => $phaseD->curriculum_scope_id,
            'subject_id' => $phaseD->subject_id,
        ]);
    }

    public function test_the_database_refuses_a_green_module_linking_a_blue_objective(): void
    {
        $this->seedReferenceData();
        $assignment = $this->groupAssignmentFor('Green', 'Eng', 'lena');
        $module = $this->modules()->create($assignment, $this->activeEnglishScope('Green'), $this->content());
        $blue = $this->objectiveIn($this->activeEnglishScope('Blue'), 'Eng', 'Blue objective', 1);
        $this->restoreActive($this->englishCurriculum());

        $this->expectException(QueryException::class);

        DB::table('teaching_module_learning_objective')->insert([
            'teaching_module_id' => $module->id,
            'learning_objective_id' => $blue->id,
            'curriculum_scope_id' => $module->curriculum_scope_id,
            'subject_id' => $module->subject_id,
        ]);
    }

    // ------------------------------------------------------------- slots

    /** @return array{0: TeachingModule, 1: SemesterProgrammeItem, 2: SemesterProgrammeItem} */
    private function moduleWithSchedule(): array
    {
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $module = $this->modules()->create($assignment, $this->activeScope('C'), $this->content());

        $annual = $this->programmes()->createForClass($this->class('Year 5', 'Year 5A'), $this->subject('Maths'), $this->pathway());
        $item = $this->programmes()->addItem($annual, $this->pathwayItem(1), $this->period('Semester 1'), 8);
        $annual = $this->programmes()->activate($annual->fresh());

        $semester = app(SemesterProgrammeService::class)->create($annual, $this->period('Semester 1'));
        $one = app(SemesterProgrammeService::class)->addSlot($semester, $item, ['week_label' => 'Minggu 3', 'planned_lesson_periods' => 3]);
        $two = app(SemesterProgrammeService::class)->addSlot($semester, $item, ['week_label' => 'Minggu 4', 'planned_lesson_periods' => 5]);

        return [$module, $one, $two];
    }

    public function test_one_module_may_serve_several_slots(): void
    {
        $this->seedReferenceData();
        [$module, $one, $two] = $this->moduleWithSchedule();

        $this->modules()->linkSlot($module, $one);
        $this->modules()->linkSlot($module->fresh(), $two);

        $this->assertSame(2, $module->fresh()->slotLinks()->count());
    }

    public function test_a_module_need_not_cover_every_slot_of_its_objective(): void
    {
        $this->seedReferenceData();
        [$module, $one] = $this->moduleWithSchedule();

        $this->modules()->linkSlot($module, $one);

        // Deliberately leaves the second slot unlinked: weeks 3 and 4 but not 6
        // is exactly the case a derived TP intersection could not express.
        $this->assertSame(1, $module->fresh()->slotLinks()->count());
    }

    public function test_a_slot_from_another_roster_is_refused(): void
    {
        $this->seedReferenceData();
        [$module] = $this->moduleWithSchedule();

        // A Year 6A schedule following the same Phase C pathway.
        $other = $this->programmes()->createForClass($this->class('Year 6', 'Year 6A'), $this->subject('Maths'), $this->pathway());
        $otherItem = $this->programmes()->addItem($other, $this->pathwayItem(1), $this->period('Semester 1'), 4);
        $other = $this->programmes()->activate($other->fresh());
        $otherSemester = app(SemesterProgrammeService::class)->create($other, $this->period('Semester 1'));
        $otherSlot = app(SemesterProgrammeService::class)->addSlot($otherSemester, $otherItem, ['planned_lesson_periods' => 4]);

        try {
            $this->modules()->linkSlot($module, $otherSlot);
            $this->fail('a module was linked to another roster schedule slot');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Year 6A', $e->errors()['semester_programme_item_id'][0]);
        }

        $this->assertSame(0, $module->fresh()->slotLinks()->count());
    }

    public function test_one_slot_may_be_served_by_modules_from_predecessor_and_successor(): void
    {
        $this->seedReferenceData();
        [$sarahModule, $slot] = $this->moduleWithSchedule();
        $this->modules()->linkSlot($sarahModule, $slot);

        $sarahAssignment = $sarahModule->classSubject;
        $this->closeAssignment($sarahAssignment, '2026-11-30');
        $ekaAssignment = $this->assignmentFor('Year 5A', 'Maths', 'eka', '2026-12-01');

        $ekaModule = $this->modules()->create($ekaAssignment, $this->activeScope('C'), $this->content(['title' => 'Eka version']));
        $this->modules()->linkSlot($ekaModule, $slot);

        // Prosem is shared across the handover; the modules are not.
        $this->assertSame(2, \App\Models\TeachingModuleSemesterProgrammeItem::where('semester_programme_item_id', $slot->id)->count());
        $this->assertNotSame($sarahModule->class_subject_id, $ekaModule->class_subject_id);
    }

    public function test_slots_cannot_be_linked_once_the_module_is_ready(): void
    {
        $this->seedReferenceData();
        [$module, $one, $two] = $this->moduleWithSchedule();
        $this->modules()->linkObjective($module, $this->objective(1));
        $this->modules()->linkSlot($module->fresh(), $one);
        $ready = $this->modules()->markReady($module->fresh());

        $this->expectException(ValidationException::class);

        $this->modules()->linkSlot($ready, $two);
    }

    // --------------------------------------------------------- lifecycle

    public function test_a_draft_may_have_no_objectives_at_all(): void
    {
        $this->seedReferenceData();
        $module = $this->draftModule();

        $this->assertSame(0, $module->objectiveLinks()->count());
        $this->assertTrue($module->isDraft());
    }

    public function test_ready_requires_at_least_one_objective(): void
    {
        $this->seedReferenceData();
        $module = $this->draftModule();

        try {
            $this->modules()->markReady($module);
            $this->fail('a module with no objectives was marked ready');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('at least one learning objective', $e->errors()['objectives'][0]);
        }

        $this->assertTrue($module->fresh()->isDraft());
    }

    public function test_ready_refuses_a_draft_objective(): void
    {
        $this->seedReferenceData();
        $module = $this->draftModule();
        $objective = $this->objective(1);
        $objective->update(['status' => 'draft']);
        $this->modules()->linkObjective($module, $objective->fresh());

        $this->expectException(ValidationException::class);

        $this->modules()->markReady($module->fresh());
    }

    public function test_ready_re_checks_scope_eligibility(): void
    {
        $this->seedReferenceData();
        $module = $this->draftModule();
        $this->modules()->linkObjective($module, $this->objective(1));

        // The class is re-graded into a different phase after the draft.
        $this->class('Year 5', 'Year 5A')->update(['grade_id' => \App\Models\Grade::where('name', 'Year 8')->firstOrFail()->id]);

        $this->expectException(ValidationException::class);

        $this->modules()->markReady($module->fresh());
    }

    public function test_ready_freezes_the_plan_but_not_teacher_notes(): void
    {
        $this->seedReferenceData();
        $module = $this->readyModule();

        try {
            $this->modules()->update($module, ['planned_activity' => 'rewritten']);
            $this->fail('a ready module accepted a plan edit');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('frozen', $e->errors()['status'][0]);
        }

        $this->modules()->update($module->fresh(), ['teacher_notes' => 'Bring extra paper.']);

        $this->assertSame('Bring extra paper.', $module->fresh()->teacher_notes);
        $this->assertStringContainsString('kertas lipat', $module->fresh()->planned_activity);
    }

    public function test_ready_freezes_objective_links(): void
    {
        $this->seedReferenceData();
        $module = $this->readyModule();

        $this->expectException(ValidationException::class);

        $this->modules()->linkObjective($module, $this->objective(2));
    }

    public function test_ready_may_return_to_draft_while_no_journal_refers_to_it(): void
    {
        $this->seedReferenceData();
        $module = $this->readyModule();

        $this->modules()->returnToDraft($module);

        $this->assertTrue($module->fresh()->isDraft());
    }

    public function test_an_archived_module_is_read_only(): void
    {
        $this->seedReferenceData();
        $module = $this->modules()->archive($this->readyModule());

        $this->assertTrue($module->isArchived());

        $this->expectException(LogicException::class);

        $module->update(['status' => 'ready']);
    }

    public function test_a_draft_cannot_be_archived_and_a_ready_module_cannot_be_deleted(): void
    {
        $this->seedReferenceData();
        $draft = $this->draftModule();

        try {
            $this->modules()->archive($draft);
            $this->fail('a draft was archived');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('deleted rather than archived', $e->errors()['status'][0]);
        }

        $ready = $this->readyModule();

        $this->expectException(ValidationException::class);

        $this->modules()->delete($ready);
    }

    public function test_an_unused_draft_deletes_with_its_links(): void
    {
        $this->seedReferenceData();
        [$module, $slot] = $this->moduleWithSchedule();
        $this->modules()->linkObjective($module, $this->objective(1));
        $this->modules()->linkSlot($module->fresh(), $slot);

        $this->modules()->delete($module->fresh());

        $this->assertSame(0, TeachingModule::count());
        $this->assertSame(0, TeachingModuleLearningObjective::count());
        $this->assertSame(0, \App\Models\TeachingModuleSemesterProgrammeItem::count());
    }

    public function test_an_objective_archived_later_does_not_invalidate_a_ready_module(): void
    {
        $this->seedReferenceData();
        $module = $this->readyModule();
        $objective = $this->objective(1);

        $objective->update(['status' => 'archived']);

        $this->assertTrue($module->fresh()->isReady());
        $this->assertSame(1, $module->fresh()->objectiveLinks()->count());
    }

    // -------------------------------------------------------- succession

    public function test_a_module_stays_with_its_author_across_a_handover(): void
    {
        $this->seedReferenceData();
        $sarahAssignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $module = $this->modules()->create($sarahAssignment, $this->activeScope('C'), $this->content());

        $this->closeAssignment($sarahAssignment, '2026-11-30');
        $ekaAssignment = $this->assignmentFor('Year 5A', 'Maths', 'eka', '2026-12-01');

        // Unchanged: still Sarah's assignment, still Sarah's row.
        $this->assertSame($sarahAssignment->id, $module->fresh()->class_subject_id);

        $eka = $this->staff('eka')->user->fresh();
        $sarah = $this->staff('sarah')->user->fresh();

        $this->assertTrue($eka->can('view', $module));
        $this->assertFalse($eka->can('update', $module));
        $this->assertFalse($sarah->can('update', $module));
        $this->assertTrue($eka->can('createFor', [TeachingModule::class, $ekaAssignment]));
    }

    public function test_no_new_module_may_be_written_against_a_closed_assignment_by_anyone(): void
    {
        $this->seedReferenceData();
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $this->closeAssignment($assignment, '2026-11-30');

        try {
            $this->modules()->create($assignment->fresh(), $this->activeScope('C'), $this->content());
            $this->fail('a module was written against closed history');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('closed history', $e->errors()['class_subject_id'][0]);
        }

        $manager = $this->userWithRole('principal');
        $this->assertFalse($manager->can('createFor', [TeachingModule::class, $assignment->fresh()]));
    }

    // ----------------------------------------------------- authorization

    public function test_a_teacher_may_only_touch_their_own_module(): void
    {
        $this->seedReferenceData();
        $module = $this->draftModule();
        $this->assignmentFor('Year 6A', 'Maths', 'eka');

        $sarah = $this->staff('sarah')->user->fresh();
        $eka = $this->staff('eka')->user->fresh();

        $this->assertTrue($sarah->can('update', $module));
        $this->assertFalse($eka->can('update', $module));
        $this->assertTrue($eka->can('view', $module));
    }

    public function test_a_manager_may_edit_and_transition_an_open_module(): void
    {
        $this->seedReferenceData();
        $module = $this->draftModule();
        $manager = $this->userWithRole('principal');

        $this->assertTrue($manager->can('update', $module));
        $this->assertTrue($manager->can('transition', $module));
    }

    // ------------------------------------------------------------ audit

    public function test_module_and_link_writes_are_audited(): void
    {
        $this->seedReferenceData();

        $createdBefore = $this->auditCount(TeachingModule::class, 'created');
        $linkedBefore = $this->auditCount(TeachingModuleLearningObjective::class, 'created');
        $updatedBefore = $this->auditCount(TeachingModule::class, 'updated');

        $module = $this->draftModule();
        $this->modules()->linkObjective($module, $this->objective(1));
        $this->modules()->markReady($module->fresh());

        $this->assertSame($createdBefore + 1, $this->auditCount(TeachingModule::class, 'created'));
        $this->assertSame($linkedBefore + 1, $this->auditCount(TeachingModuleLearningObjective::class, 'created'));
        $this->assertSame($updatedBefore + 1, $this->auditCount(TeachingModule::class, 'updated'));
    }

    public function test_a_refused_write_leaves_no_audit_trail(): void
    {
        $this->seedReferenceData();
        $module = $this->readyModule();
        $before = $this->auditCount(TeachingModule::class, 'updated');

        try {
            $this->modules()->update($module, ['planned_activity' => 'rewritten']);
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame($before, $this->auditCount(TeachingModule::class, 'updated'));
    }

    // --------------------------------------------------------- boundary

    public function test_a_module_stores_no_scheduling_or_actual_facts(): void
    {
        $this->seedReferenceData();

        foreach ([
            'academic_period_id', 'academic_year_id', 'week_label',
            'planned_lesson_periods', 'planned_start_date', 'planned_end_date',
            'actual_activity', 'actual_lesson_periods', 'journal_date', 'staff_id',
            'grade_id', 'english_level_id', 'assessment_id',
        ] as $column) {
            $this->assertFalse(
                \Illuminate\Support\Facades\Schema::hasColumn('teaching_modules', $column),
                "{$column} belongs to another layer and must not be copied onto a module"
            );
        }
    }
}
