<?php

namespace Tests\Feature;

use App\Livewire\Curricula\PathwayShow;
use App\Models\AuditLog;
use App\Models\ClassSubject;
use App\Models\Curriculum;
use App\Models\CurriculumScope;
use App\Models\EnglishLevel;
use App\Models\Grade;
use App\Models\LearningObjective;
use App\Models\LearningPathway;
use App\Models\LearningPathwayItem;
use App\Models\LearningPhase;
use App\Models\Position;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Subject;
use App\Models\TeachingGroup;
use App\Models\User;
use App\Services\CurriculumScopeService;
use App\Services\LearningObjectiveService;
use App\Services\LearningPathwayService;
use Database\Seeders\EnglishProgrammeSeeder;
use Database\Seeders\GradeSeeder;
use Database\Seeders\LearningPhaseSeeder;
use Database\Seeders\PositionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 5D: learning pathways (ATP).
 *
 * A linear, ordered route through one curriculum scope and subject. Several
 * may be active at once because they are alternative approved routes, and
 * teachers may draft one -- but only for a scope and subject they actually
 * teach right now.
 */
class LearningPathwayTest extends TestCase
{
    use RefreshDatabase;

    private ?Curriculum $national = null;

    // -------------------------------------------------------- anchor / schema

    public function test_a_pathway_is_anchored_to_a_scope_and_subject(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();

        $this->assertSame($this->phaseCScope()->id, $pathway->curriculum_scope_id);
        $this->assertSame($this->subject('Maths')->id, $pathway->subject_id);
        $this->assertTrue($pathway->isDraft());
    }

    public function test_a_pathway_carries_no_teaching_or_grade_columns(): void
    {
        foreach (['grade_id', 'class_id', 'teaching_group_id', 'class_subject_id', 'academic_year_id'] as $column) {
            $this->assertFalse(
                Schema::hasColumn('learning_pathways', $column),
                "learning_pathways must not carry {$column}"
            );
        }
    }

    public function test_the_anchor_cannot_be_changed_after_creation(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('fixed at creation');

        $pathway->update(['subject_id' => $this->subject('Eng')->id]);
    }

    public function test_teaching_assignments_still_have_no_pathway_column(): void
    {
        $this->assertFalse(
            Schema::hasColumn('class_subject', 'learning_pathway_id'),
            'a teaching assignment SELECTS a pathway later; it never owns one'
        );
    }

    public function test_a_pathway_carries_sequence_but_never_allocation(): void
    {
        // Prota (Phase 5E) owns when and how much; the pathway owns only order.
        foreach (['academic_period_id', 'planned_lesson_periods', 'planned_start_date'] as $column) {
            $this->assertFalse(
                Schema::hasColumn('learning_pathway_items', $column),
                "{$column} is an allocation fact and belongs to the annual programme"
            );
        }
    }

    public function test_teaching_module_and_journal_tables_do_not_exist_yet(): void
    {
        foreach (['teaching_modules', 'daily_journals', 'lesson_journals'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "{$table} belongs to a later phase");
        }
    }

    // -------------------------------------------------------------- items

    public function test_items_are_appended_in_order(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();

        foreach ([1, 2, 3] as $n) {
            $this->pathways()->addItem($pathway, $this->objective("TP {$n}", $n));
        }

        $this->assertSame([1, 2, 3], $pathway->fresh()->items()->pluck('position')->all());
    }

    public function test_pathway_order_is_independent_of_library_reference_order(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();

        $first = $this->objective('TP one', 1);
        $second = $this->objective('TP two', 2);
        $third = $this->objective('TP three', 3);

        // Sequenced deliberately out of library order.
        $this->pathways()->addItem($pathway, $third);
        $this->pathways()->addItem($pathway, $first);
        $this->pathways()->addItem($pathway, $second);

        $this->assertSame(
            ['TP three', 'TP one', 'TP two'],
            $pathway->fresh()->items()->with('learningObjective')->get()
                ->pluck('learningObjective.objective_text')->all()
        );
    }

    public function test_the_same_objective_cannot_appear_twice(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();
        $tp = $this->objective('TP', 1);

        $this->pathways()->addItem($pathway, $tp);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('already in this pathway');

        $this->pathways()->addItem($pathway, $tp);
    }

    public function test_the_database_also_refuses_a_duplicate_objective(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();
        $tp = $this->objective('TP', 1);
        $this->pathways()->addItem($pathway, $tp);

        $this->expectException(QueryException::class);
        LearningPathwayItem::create([
            'learning_pathway_id' => $pathway->id, 'learning_objective_id' => $tp->id,
            'curriculum_scope_id' => $pathway->curriculum_scope_id, 'subject_id' => $pathway->subject_id,
            'position' => 99,
        ]);
    }

    // ----------------------------------------------------- database integrity

    public function test_the_database_rejects_a_cross_phase_item(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();
        $foreign = $this->objectiveIn($this->phaseDScope(), 'Maths', 'Phase D TP', 1);

        $this->expectException(QueryException::class);
        LearningPathwayItem::create([
            'learning_pathway_id' => $pathway->id, 'learning_objective_id' => $foreign->id,
            'curriculum_scope_id' => $pathway->curriculum_scope_id, 'subject_id' => $pathway->subject_id,
            'position' => 1,
        ]);
    }

    public function test_the_database_rejects_a_cross_subject_item(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();
        $foreign = $this->objectiveIn($this->phaseCScope(), 'Eng', 'English TP', 1);

        $this->expectException(QueryException::class);
        LearningPathwayItem::create([
            'learning_pathway_id' => $pathway->id, 'learning_objective_id' => $foreign->id,
            'curriculum_scope_id' => $pathway->curriculum_scope_id, 'subject_id' => $pathway->subject_id,
            'position' => 1,
        ]);
    }

    public function test_the_database_rejects_a_national_to_english_programme_item(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();
        $foreign = $this->objectiveIn($this->greenScope(), 'Eng', 'Green TP', 1);

        $this->expectException(QueryException::class);
        LearningPathwayItem::create([
            'learning_pathway_id' => $pathway->id, 'learning_objective_id' => $foreign->id,
            'curriculum_scope_id' => $pathway->curriculum_scope_id, 'subject_id' => $pathway->subject_id,
            'position' => 1,
        ]);
    }

    public function test_the_database_rejects_a_falsified_mirrored_anchor(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();
        $foreign = $this->objectiveIn($this->phaseDScope(), 'Maths', 'Phase D TP', 1);

        $this->expectException(QueryException::class);
        LearningPathwayItem::create([
            'learning_pathway_id' => $pathway->id, 'learning_objective_id' => $foreign->id,
            'curriculum_scope_id' => $foreign->curriculum_scope_id, 'subject_id' => $foreign->subject_id,
            'position' => 1,
        ]);
    }

    public function test_the_service_rejects_a_cross_scope_item_readably(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();
        $foreign = $this->objectiveIn($this->phaseDScope(), 'Maths', 'Phase D TP', 1);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('different curriculum scope or subject');

        $this->pathways()->addItem($pathway, $foreign);
    }

    // ------------------------------------------------- position normalisation

    public function test_positions_stay_contiguous_after_a_removal(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();

        $items = collect([1, 2, 3, 4])->map(fn ($n) => $this->pathways()->addItem($pathway, $this->objective("TP {$n}", $n)));

        $this->pathways()->removeItem($pathway, $items[1]);

        $this->assertSame([1, 2, 3], $pathway->fresh()->items()->pluck('position')->all(), 'the gap must close');
    }

    public function test_positions_stay_contiguous_after_a_move(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();

        $items = collect([1, 2, 3])->map(fn ($n) => $this->pathways()->addItem($pathway, $this->objective("TP {$n}", $n)));

        $this->pathways()->moveItem($pathway, $items[2], 'up');

        $ordered = $pathway->fresh()->items()->with('learningObjective')->get();

        $this->assertSame([1, 2, 3], $ordered->pluck('position')->all());
        $this->assertSame(['TP 1', 'TP 3', 'TP 2'], $ordered->pluck('learningObjective.objective_text')->all());
    }

    public function test_normalisation_repairs_a_gapped_sequence(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();

        $items = collect([1, 2, 3])->map(fn ($n) => $this->pathways()->addItem($pathway, $this->objective("TP {$n}", $n)));

        // Raw SQL can still leave a draft gapped -- this is the documented
        // application-level constraint. Normalisation repairs it.
        \DB::table('learning_pathway_items')->where('id', $items[0]->id)->update(['position' => 1]);
        \DB::table('learning_pathway_items')->where('id', $items[1]->id)->update(['position' => 1]);
        \DB::table('learning_pathway_items')->where('id', $items[2]->id)->update(['position' => 7]);

        $this->pathways()->normalise($pathway->fresh());

        $this->assertSame([1, 2, 3], $pathway->fresh()->items()->pluck('position')->all());
    }

    // ------------------------------------------------------ TP eligibility

    public function test_a_draft_pathway_may_sequence_a_draft_objective(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();

        $item = $this->pathways()->addItem($pathway, $this->objective('Still draft', 1, 'draft'));

        $this->assertSame(1, $item->position);
    }

    public function test_an_archived_objective_cannot_be_newly_added(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();
        $tp = $this->objective('Retired', 1);
        $tp->update(['status' => 'archived']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('archived');

        $this->pathways()->addItem($pathway, $tp->fresh());
    }

    public function test_a_pathway_cannot_activate_while_an_item_is_a_draft_objective(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();
        $this->pathways()->addItem($pathway, $this->objective('Draft TP', 1, 'draft'));
        $this->activateCurriculum();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('must itself be active');

        $this->pathways()->activate($pathway->fresh());
    }

    public function test_an_objective_archived_after_activation_leaves_the_pathway_intact(): void
    {
        $this->seedReferenceData();
        $pathway = $this->activatedPathway();
        $item = $pathway->items()->first();

        $item->learningObjective->update(['status' => 'archived']);

        $pathway->refresh();

        $this->assertTrue($pathway->isActive(), 'history stays valid');
        $this->assertSame(1, $pathway->items()->count(), 'items are never rewritten');
        $this->assertSame('archived', $item->fresh()->learningObjective->status);
    }

    // -------------------------------------------------------------- activation

    public function test_a_pathway_under_a_draft_curriculum_cannot_activate(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();
        $this->pathways()->addItem($pathway, $this->objective('TP', 1));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Activate the curriculum version first');

        $this->pathways()->activate($pathway->fresh());
    }

    public function test_an_empty_pathway_cannot_activate(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();
        $this->activateCurriculum();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('at least one learning objective');

        $this->pathways()->activate($pathway->fresh());
    }

    public function test_a_pathway_under_an_archived_curriculum_cannot_activate(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();
        $this->pathways()->addItem($pathway, $this->objective('TP', 1));
        $this->national->update(['status' => 'archived']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('archived');

        $this->pathways()->activate($pathway->fresh());
    }

    public function test_a_valid_pathway_activates_and_normalises(): void
    {
        $this->seedReferenceData();
        $pathway = $this->activatedPathway();

        $this->assertTrue($pathway->isActive());
        $this->assertSame([1], $pathway->items()->pluck('position')->all());
    }

    public function test_activation_is_atomic_when_validation_fails(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();
        $this->activateCurriculum();

        try {
            $this->pathways()->activate($pathway->fresh());
            $this->fail('should have been refused');
        } catch (ValidationException) {
            // nothing applied
        }

        $this->assertTrue($pathway->fresh()->isDraft());
    }

    // ------------------------------------------------------------- variants

    public function test_two_pathways_may_be_active_at_once_for_one_scope_and_subject(): void
    {
        $this->seedReferenceData();
        $first = $this->activatedPathway('Route A', 'ATP-A');

        $second = $this->pathways()->create($this->phaseCScope(), $this->subject('Maths'), [
            'title' => 'Route B', 'code' => 'ATP-B',
        ]);
        $this->pathways()->addItem($second, $this->objective('Another TP', 2));
        $this->pathways()->activate($second->fresh());

        $this->assertTrue($first->fresh()->isActive(), 'activating an alternative must not retire the first');
        $this->assertSame(2, LearningPathway::active()->count());
    }

    public function test_two_active_pathways_cannot_share_a_code(): void
    {
        $this->seedReferenceData();
        $this->activatedPathway('Route A', 'ATP-01');

        $second = $this->pathways()->create($this->phaseCScope(), $this->subject('Maths'), [
            'title' => 'Route B', 'code' => 'ATP-01',
        ]);
        $this->pathways()->addItem($second, $this->objective('Another TP', 2));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('already uses the code');

        $this->pathways()->activate($second->fresh());
    }

    public function test_a_draft_may_share_the_code_of_an_active_pathway(): void
    {
        $this->seedReferenceData();
        $this->activatedPathway('Route A', 'ATP-01');

        $replacement = $this->pathways()->create($this->phaseCScope(), $this->subject('Maths'), [
            'title' => 'Route A revised', 'code' => 'ATP-01',
        ]);

        $this->assertSame('ATP-01', $replacement->code);
        $this->assertSame(2, LearningPathway::count());
    }

    public function test_the_database_refuses_two_active_pathways_with_one_code(): void
    {
        $this->seedReferenceData();
        $active = $this->activatedPathway('Route A', 'ATP-01');

        $this->expectException(QueryException::class);
        LearningPathway::create([
            'curriculum_scope_id' => $active->curriculum_scope_id,
            'subject_id' => $active->subject_id,
            'code' => 'ATP-01', 'title' => 'Sneaky', 'status' => 'active',
        ]);
    }

    // ------------------------------------------------------------- lifecycle

    public function test_an_active_pathway_is_frozen(): void
    {
        $this->seedReferenceData();
        $pathway = $this->activatedPathway();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot be edited');

        $pathway->update(['title' => 'Renamed']);
    }

    public function test_an_active_pathway_cannot_gain_items(): void
    {
        $this->seedReferenceData();
        $pathway = $this->activatedPathway();

        $this->expectException(ValidationException::class);
        $this->pathways()->addItem($pathway, $this->objective('Late addition', 5));
    }

    public function test_an_active_pathway_cannot_be_reordered_or_deleted(): void
    {
        $this->seedReferenceData();
        $pathway = $this->activatedPathway();

        try {
            $this->pathways()->moveItem($pathway, $pathway->items()->first(), 'up');
            $this->fail('reorder should be refused');
        } catch (ValidationException) {
        }

        $this->expectException(ValidationException::class);
        $this->pathways()->delete($pathway);
    }

    public function test_an_active_pathway_can_be_archived_and_keeps_its_items(): void
    {
        $this->seedReferenceData();
        $pathway = $this->activatedPathway();

        $archived = $this->pathways()->archive($pathway);

        $this->assertTrue($archived->isArchived());
        $this->assertSame(1, $archived->items()->count());
    }

    public function test_an_archived_pathway_is_read_only(): void
    {
        $this->seedReferenceData();
        $pathway = $this->pathways()->archive($this->activatedPathway());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('read-only');

        $pathway->update(['title' => 'Changed']);
    }

    public function test_an_unused_draft_can_be_deleted_with_its_items(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();
        $this->pathways()->addItem($pathway, $this->objective('TP', 1));

        $itemDeletes = $this->auditCount(LearningPathwayItem::class, 'deleted');
        $this->pathways()->delete($pathway);

        $this->assertSame(0, LearningPathway::count());
        $this->assertSame(0, LearningPathwayItem::count());
        $this->assertSame($itemDeletes + 1, $this->auditCount(LearningPathwayItem::class, 'deleted'));
    }

    // -------------------------------------------------- curriculum lifecycle

    public function test_a_draft_pathway_may_be_created_under_a_draft_or_active_curriculum(): void
    {
        $this->seedReferenceData();
        $this->assertTrue($this->draftPathway('Under draft')->isDraft());

        $this->activateCurriculum();
        $this->assertTrue($this->draftPathway('Under active')->isDraft());
    }

    public function test_no_pathway_may_be_created_under_an_archived_curriculum(): void
    {
        $this->seedReferenceData();
        $this->national->update(['status' => 'archived']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('archived');

        $this->pathways()->create($this->phaseCScope(), $this->subject('Maths'), ['title' => 'Too late']);
    }

    public function test_archiving_a_curriculum_leaves_pathway_status_factual(): void
    {
        $this->seedReferenceData();
        $pathway = $this->activatedPathway();

        $this->national->update(['status' => 'archived']);

        $this->assertTrue($pathway->fresh()->isActive());
    }

    // ------------------------------------------------ teacher authorisation

    public function test_a_year_5_maths_teacher_may_draft_the_phase_c_maths_pathway(): void
    {
        $this->seedReferenceData();
        $teacher = $this->teacherTeaching('Year 5', 'Maths');

        $this->assertTrue($teacher->can('createFor', [LearningPathway::class, $this->phaseCScope(), $this->subject('Maths')->id]));
    }

    public function test_a_year_6_maths_teacher_may_collaborate_on_the_same_phase_c_pathway(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();

        $year5 = $this->teacherTeaching('Year 5', 'Maths', 'Y5');
        $year6 = $this->teacherTeaching('Year 6', 'Maths', 'Y6');

        $this->assertTrue($year5->can('update', $pathway), 'Year 5 teaches Phase C');
        $this->assertTrue($year6->can('update', $pathway), 'Year 6 teaches Phase C too -- same pathway');
    }

    public function test_a_maths_teacher_cannot_draft_the_english_pathway_for_their_phase(): void
    {
        $this->seedReferenceData();
        $teacher = $this->teacherTeaching('Year 5', 'Maths');

        $this->assertFalse($teacher->can('createFor', [LearningPathway::class, $this->phaseCScope(), $this->subject('Eng')->id]));
    }

    public function test_a_year_5_teacher_cannot_draft_a_phase_d_pathway(): void
    {
        $this->seedReferenceData();
        $teacher = $this->teacherTeaching('Year 5', 'Maths');

        $this->assertFalse($teacher->can('createFor', [LearningPathway::class, $this->phaseDScope(), $this->subject('Maths')->id]));
    }

    public function test_a_green_english_teacher_may_draft_the_green_learning_path(): void
    {
        $this->seedReferenceData();
        $teacher = $this->teacherTeachingGroup('Green', 'Eng');

        $this->assertTrue($teacher->can('createFor', [LearningPathway::class, $this->greenScope(), $this->subject('Eng')->id]));
    }

    public function test_a_green_teacher_cannot_draft_the_blue_learning_path(): void
    {
        $this->seedReferenceData();
        $teacher = $this->teacherTeachingGroup('Green', 'Eng');

        $blueScope = $this->scopes()->addEnglishLevel($this->englishCurriculum(), $this->level('Blue'));

        $this->assertFalse($teacher->can('createFor', [LearningPathway::class, $blueScope, $this->subject('Eng')->id]));
    }

    public function test_a_teacher_with_no_matching_assignment_cannot_edit_a_draft(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();

        $unrelated = $this->userWithRole('teacher');

        $this->assertTrue($unrelated->can('academics.plan'), 'they hold the permission');
        $this->assertFalse($unrelated->can('update', $pathway), 'but teach nothing that matches');
    }

    public function test_a_closed_assignment_does_not_authorise_drafting(): void
    {
        $this->seedReferenceData();
        $teacher = $this->teacherTeaching('Year 5', 'Maths');

        ClassSubject::where('staff_id', $teacher->staff->id)->update(['ended_on' => '2026-12-15']);

        $this->assertFalse(
            $teacher->fresh()->can('createFor', [LearningPathway::class, $this->phaseCScope(), $this->subject('Maths')->id]),
            'planning is for teaching currently held'
        );
    }

    public function test_a_manager_may_manage_any_draft(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();

        foreach (['admin_staff', 'principal'] as $role) {
            $manager = $this->userWithRole($role);
            $this->assertTrue($manager->can('createFor', [LearningPathway::class, $this->greenScope(), $this->subject('Eng')->id]));
            $this->assertTrue($manager->can('update', $pathway));
            $this->assertTrue($manager->can('transition', $pathway));
        }
    }

    public function test_a_teacher_cannot_activate_or_archive(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();
        $teacher = $this->teacherTeaching('Year 5', 'Maths');

        $this->assertTrue($teacher->can('update', $pathway), 'may build the sequence');
        $this->assertFalse($teacher->can('transition', $pathway), 'but not put it into force');
    }

    public function test_nobody_may_edit_an_active_pathway_including_managers(): void
    {
        $this->seedReferenceData();
        $pathway = $this->activatedPathway();

        $this->assertFalse($this->userWithRole('principal')->can('update', $pathway));
        $this->assertFalse($this->teacherTeaching('Year 5', 'Maths')->can('update', $pathway));
    }

    public function test_a_teacher_may_view_any_pathway(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();

        $this->assertTrue($this->userWithRole('teacher')->can('view', $pathway));
    }

    // ------------------------------------------------------------ UI / audit

    public function test_a_teacher_can_build_a_sequence_through_the_screen(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();
        $teacher = $this->teacherTeaching('Year 5', 'Maths');
        $tp = $this->objective('TP one', 1);

        Livewire::actingAs($teacher)
            ->test(PathwayShow::class, [
                'curriculum' => $this->national, 'scope' => $this->phaseCScope(), 'pathway' => $pathway,
            ])
            ->set('showAddItem', true)
            ->set('learning_objective_id', (string) $tp->id)
            ->call('addItem')
            ->assertHasNoErrors();

        $this->assertSame(1, $pathway->fresh()->items()->count());
    }

    public function test_pathway_and_item_changes_are_audited(): void
    {
        $this->seedReferenceData();

        $creates = $this->auditCount(LearningPathway::class, 'created');
        $pathway = $this->draftPathway();
        $this->assertSame($creates + 1, $this->auditCount(LearningPathway::class, 'created'));

        $itemCreates = $this->auditCount(LearningPathwayItem::class, 'created');
        $first = $this->pathways()->addItem($pathway, $this->objective('TP 1', 1));
        $second = $this->pathways()->addItem($pathway, $this->objective('TP 2', 2));
        $this->assertSame($itemCreates + 2, $this->auditCount(LearningPathwayItem::class, 'created'));

        $itemUpdates = $this->auditCount(LearningPathwayItem::class, 'updated');
        $this->pathways()->moveItem($pathway, $second, 'up');
        $this->assertGreaterThan($itemUpdates, $this->auditCount(LearningPathwayItem::class, 'updated'), 'reorder audited per item');

        $updates = $this->auditCount(LearningPathway::class, 'updated');
        $this->activateCurriculum();
        $this->objective('TP 1', 1)->update(['status' => 'active']);
        $this->pathways()->activate($pathway->fresh());
        $this->assertSame($updates + 1, $this->auditCount(LearningPathway::class, 'updated'));
    }

    // --------------------------------------------------------- delete safety

    public function test_an_objective_used_by_a_pathway_cannot_be_deleted(): void
    {
        $this->seedReferenceData();
        $pathway = $this->draftPathway();

        // A DRAFT objective, which its own lifecycle would otherwise allow to
        // be deleted -- so what stops it here is the pathway item's RESTRICT,
        // which is the thing under test.
        $tp = $this->objective('TP', 1, 'draft');
        $this->pathways()->addItem($pathway, $tp);

        $this->expectException(QueryException::class);
        $tp->delete();
    }

    public function test_a_scope_holding_a_pathway_cannot_be_deleted(): void
    {
        $this->seedReferenceData();
        $this->draftPathway();

        $this->expectException(QueryException::class);
        $this->phaseCScope()->delete();
    }

    // ------------------------------------------------------------- vocabulary

    public function test_vocabulary_differs_between_national_and_english(): void
    {
        $this->seedReferenceData();

        $this->assertSame('Alur Tujuan Pembelajaran (ATP)', $this->national->vocabulary()['pathway']);
        $this->assertSame('Learning Path', $this->englishCurriculum()->vocabulary()['pathway']);
    }

    // ---------------------------------------------------------------- helpers

    private function seedReferenceData(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(GradeSeeder::class);
        $this->seed(PositionSeeder::class);
        $this->seed(EnglishProgrammeSeeder::class);
        $this->seed(LearningPhaseSeeder::class);

        Subject::firstOrCreate(['name' => 'Maths']);
        Subject::firstOrCreate(['name' => 'Eng']);

        \App\Models\AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_current' => true],
        );

        $this->national = Curriculum::firstOrCreate(
            ['code' => 'NAT', 'version' => '1'],
            ['name' => 'National', 'effective_from' => '2026-07-01', 'status' => 'draft'],
        );
    }

    private function activateCurriculum(): void
    {
        $this->national->update(['status' => 'active']);
        $this->national->refresh();
    }

    private function englishCurriculum(): Curriculum
    {
        return Curriculum::firstOrCreate(
            ['code' => 'ENG', 'version' => '1'],
            [
                'name' => 'Primary English', 'effective_from' => '2026-07-01', 'status' => 'draft',
                'english_programme_id' => \App\Models\EnglishProgramme::where('name', 'Primary English Programme')->firstOrFail()->id,
            ],
        );
    }

    private function phaseCScope(): CurriculumScope
    {
        return $this->scopeFor($this->national, 'C');
    }

    private function phaseDScope(): CurriculumScope
    {
        return $this->scopeFor($this->national, 'D');
    }

    private function scopeFor(Curriculum $curriculum, string $phaseCode): CurriculumScope
    {
        $phase = LearningPhase::where('code', $phaseCode)->firstOrFail();

        return $curriculum->scopes()->where('learning_phase_id', $phase->id)->first()
            ?? $this->scopes()->addPhase($curriculum, $phase);
    }

    private function greenScope(): CurriculumScope
    {
        $curriculum = $this->englishCurriculum();
        $green = $this->level('Green');

        return $curriculum->scopes()->where('english_level_id', $green->id)->first()
            ?? $this->scopes()->addEnglishLevel($curriculum, $green);
    }

    private function draftPathway(string $title = 'Phase C route', ?string $code = null): LearningPathway
    {
        return $this->pathways()->create($this->phaseCScope(), $this->subject('Maths'), [
            'title' => $title, 'code' => $code,
        ]);
    }

    private function activatedPathway(string $title = 'Phase C route', ?string $code = null): LearningPathway
    {
        $pathway = $this->draftPathway($title, $code);
        $this->pathways()->addItem($pathway, $this->objective('TP', 1));
        $this->activateCurriculum();

        return $this->pathways()->activate($pathway->fresh());
    }

    private function objective(string $text, int $order, string $status = 'active'): LearningObjective
    {
        return LearningObjective::firstOrCreate(
            ['curriculum_scope_id' => $this->phaseCScope()->id, 'subject_id' => $this->subject('Maths')->id, 'objective_text' => $text],
            ['reference_order' => $order, 'status' => $status],
        );
    }

    private function objectiveIn(CurriculumScope $scope, string $subject, string $text, int $order): LearningObjective
    {
        return LearningObjective::create([
            'curriculum_scope_id' => $scope->id,
            'subject_id' => $this->subject($subject)->id,
            'objective_text' => $text, 'reference_order' => $order, 'status' => 'active',
        ]);
    }

    /** A teacher holding an active class-backed assignment for a grade + subject. */
    private function teacherTeaching(string $gradeName, string $subject, string $key = 'T'): User
    {
        $year = \App\Models\AcademicYear::firstOrFail();
        $class = SchoolClass::firstOrCreate([
            'name' => $gradeName.'A', 'grade_id' => Grade::where('name', $gradeName)->firstOrFail()->id,
            'academic_year_id' => $year->id,
        ]);

        $staff = $this->staff($key);
        ClassSubject::firstOrCreate(
            ['class_id' => $class->id, 'subject_id' => $this->subject($subject)->id],
            ['staff_id' => $staff->id, 'started_on' => '2026-07-01'],
        );

        return $staff->user->fresh();
    }

    /** A teacher holding an active teaching-group assignment for an English level. */
    private function teacherTeachingGroup(string $levelName, string $subject, string $key = 'G'): User
    {
        $year = \App\Models\AcademicYear::firstOrFail();
        $group = TeachingGroup::firstOrCreate(
            ['academic_year_id' => $year->id, 'name' => $levelName.' A'],
            ['english_level_id' => $this->level($levelName)->id, 'status' => 'active'],
        );

        $staff = $this->staff($key);
        ClassSubject::firstOrCreate(
            ['teaching_group_id' => $group->id, 'subject_id' => $this->subject($subject)->id],
            ['staff_id' => $staff->id, 'started_on' => '2026-07-01'],
        );

        return $staff->user->fresh();
    }

    private function staff(string $key): Staff
    {
        $user = User::firstOrCreate(
            ['email' => strtolower($key).'@rahai.test'],
            ['name' => 'Teacher '.$key, 'password' => bcrypt('password'), 'status' => 'active'],
        );

        if (! $user->hasRole('teacher')) {
            $user->assignRole('teacher');
        }

        return Staff::firstOrCreate(
            ['staff_number' => 'S-'.$key],
            [
                'first_name' => 'Teacher', 'last_name' => $key,
                'position_id' => Position::firstOrFail()->id, 'phone' => '08000',
                'hire_date' => '2020-01-01', 'status' => 'active', 'user_id' => $user->id,
            ],
        );
    }

    private function level(string $name): EnglishLevel
    {
        return EnglishLevel::where('name', $name)->firstOrFail();
    }

    private function subject(string $name): Subject
    {
        return Subject::where('name', $name)->firstOrFail();
    }

    private function scopes(): CurriculumScopeService
    {
        return app(CurriculumScopeService::class);
    }

    private function pathways(): LearningPathwayService
    {
        return app(LearningPathwayService::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::firstOrCreate(
            ['email' => $role.'@rahai.test'],
            ['name' => ucfirst($role), 'password' => bcrypt('password'), 'status' => 'active'],
        );

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        return $user->fresh();
    }

    private function auditCount(string $model, string $action): int
    {
        return AuditLog::where('auditable_type', $model)->where('action', $action)->count();
    }
}
