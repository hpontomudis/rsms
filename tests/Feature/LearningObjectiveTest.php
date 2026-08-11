<?php

namespace Tests\Feature;

use App\Livewire\Curricula\ScopeShow;
use App\Models\AuditLog;
use App\Models\Curriculum;
use App\Models\CurriculumScope;
use App\Models\LearningObjective;
use App\Models\LearningObjectiveLearningOutcome;
use App\Models\LearningOutcome;
use App\Models\LearningPhase;
use App\Models\Subject;
use App\Models\User;
use App\Services\CurriculumScopeService;
use App\Services\LearningObjectiveService;
use Database\Seeders\EnglishProgrammeSeeder;
use Database\Seeders\GradeSeeder;
use Database\Seeders\LearningPhaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 5C: Tujuan Pembelajaran / Learning Objectives.
 *
 * TP is the school's own formulation derived from CP, so unlike CP it carries
 * its own draft/active/archived lifecycle and may be authored while a
 * curriculum is in force. What it may never do is drift off the standard it
 * serves -- hence the anchor immutability and the composite-key link integrity.
 */
class LearningObjectiveTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------- schema / anchor

    public function test_an_objective_is_anchored_to_a_scope_and_subject(): void
    {
        $this->seedReferenceData();
        [$scope] = $this->nationalScope();

        $objective = $this->draft($scope, 'Maths', 'Use fractions in context.');

        $this->assertSame($scope->id, $objective->curriculum_scope_id);
        $this->assertSame($this->subject('Maths')->id, $objective->subject_id);
        $this->assertSame('draft', $objective->status);
    }

    public function test_an_objective_carries_no_teaching_or_grade_columns(): void
    {
        foreach (['grade_id', 'class_subject_id', 'teaching_group_id', 'academic_year_id'] as $column) {
            $this->assertFalse(
                Schema::hasColumn('learning_objectives', $column),
                "learning_objectives must not carry {$column} -- TP stays phase/level based"
            );
        }
    }

    public function test_the_anchor_cannot_be_changed_after_creation(): void
    {
        $this->seedReferenceData();
        [$scope, $curriculum] = $this->nationalScope();
        $objective = $this->draft($scope, 'Maths', 'Original.');

        $otherScope = $this->scopes()->addPhase($curriculum, $this->phase('D'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('fixed at creation');

        $objective->update(['curriculum_scope_id' => $otherScope->id]);
    }

    public function test_the_subject_cannot_be_changed_after_creation(): void
    {
        $this->seedReferenceData();
        [$scope] = $this->nationalScope();
        $objective = $this->draft($scope, 'Maths', 'Original.');

        $this->expectException(\LogicException::class);
        $objective->update(['subject_id' => $this->subject('Eng')->id]);
    }

    public function test_a_long_objective_statement_persists_intact(): void
    {
        $this->seedReferenceData();
        [$scope] = $this->nationalScope();

        $text = str_repeat('Peserta didik mampu menerapkan konsep pengukuran dalam konteks nyata. ', 200);
        $objective = $this->draft($scope, 'Maths', $text);

        $this->assertSame($text, $objective->fresh()->objective_text);
    }

    // ------------------------------------------------------------ many-to-many

    public function test_one_objective_may_derive_from_several_outcomes(): void
    {
        $this->seedReferenceData();
        [$scope] = $this->nationalScope();
        $objective = $this->draft($scope, 'Maths', 'Synthesis.');

        $this->objectives()->linkOutcome($objective, $this->outcome($scope, 'Maths', 'Element one.', 1));
        $this->objectives()->linkOutcome($objective, $this->outcome($scope, 'Maths', 'Element two.', 2));

        $this->assertSame(2, $objective->outcomeLinks()->count());
    }

    public function test_one_outcome_may_inform_several_objectives(): void
    {
        $this->seedReferenceData();
        [$scope] = $this->nationalScope();
        $cp = $this->outcome($scope, 'Maths', 'Broad standard.', 1);

        $first = $this->draft($scope, 'Maths', 'TP one.');
        $second = $this->draft($scope, 'Maths', 'TP two.');

        $this->objectives()->linkOutcome($first, $cp);
        $this->objectives()->linkOutcome($second, $cp);

        $this->assertSame(2, $cp->objectiveLinks()->count(), 'reverse traceability');
    }

    public function test_a_duplicate_link_is_rejected(): void
    {
        $this->seedReferenceData();
        [$scope] = $this->nationalScope();
        $objective = $this->draft($scope, 'Maths', 'TP.');
        $cp = $this->outcome($scope, 'Maths', 'CP.', 1);

        $this->objectives()->linkOutcome($objective, $cp);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('already linked');

        $this->objectives()->linkOutcome($objective, $cp);
    }

    public function test_the_service_rejects_a_cross_scope_link_readably(): void
    {
        $this->seedReferenceData();
        [$scope, $curriculum] = $this->nationalScope();
        $phaseD = $this->scopes()->addPhase($curriculum, $this->phase('D'));

        $objective = $this->draft($scope, 'Maths', 'Phase C TP.');
        $foreign = $this->outcome($phaseD, 'Maths', 'Phase D CP.', 1);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('different curriculum scope or subject');

        $this->objectives()->linkOutcome($objective, $foreign);
    }

    /**
     * And the database refuses it too, bypassing the service entirely -- this
     * is what the composite foreign keys are for.
     */
    public function test_the_database_rejects_a_cross_phase_link(): void
    {
        $this->seedReferenceData();
        [$scope, $curriculum] = $this->nationalScope();
        $phaseD = $this->scopes()->addPhase($curriculum, $this->phase('D'));

        $objective = $this->draft($scope, 'Maths', 'Phase C TP.');
        $foreign = $this->outcome($phaseD, 'Maths', 'Phase D CP.', 1);

        $this->expectException(QueryException::class);
        LearningObjectiveLearningOutcome::create([
            'learning_objective_id' => $objective->id,
            'learning_outcome_id' => $foreign->id,
            'curriculum_scope_id' => $objective->curriculum_scope_id,
            'subject_id' => $objective->subject_id,
        ]);
    }

    public function test_the_database_rejects_a_cross_subject_link(): void
    {
        $this->seedReferenceData();
        [$scope] = $this->nationalScope();

        $objective = $this->draft($scope, 'Maths', 'Maths TP.');
        $englishCp = $this->outcome($scope, 'Eng', 'English CP.', 1);

        $this->expectException(QueryException::class);
        LearningObjectiveLearningOutcome::create([
            'learning_objective_id' => $objective->id,
            'learning_outcome_id' => $englishCp->id,
            'curriculum_scope_id' => $objective->curriculum_scope_id,
            'subject_id' => $objective->subject_id,
        ]);
    }

    public function test_the_database_rejects_a_national_to_english_programme_link(): void
    {
        $this->seedReferenceData();
        [$nationalScope] = $this->nationalScope();
        $englishScope = $this->englishScope();

        $objective = $this->draft($nationalScope, 'Eng', 'National English TP.');
        $programmeCp = $this->outcome($englishScope, 'Eng', 'Green outcome.', 1);

        $this->expectException(QueryException::class);
        LearningObjectiveLearningOutcome::create([
            'learning_objective_id' => $objective->id,
            'learning_outcome_id' => $programmeCp->id,
            'curriculum_scope_id' => $objective->curriculum_scope_id,
            'subject_id' => $objective->subject_id,
        ]);
    }

    public function test_the_database_rejects_a_falsified_mirrored_anchor(): void
    {
        $this->seedReferenceData();
        [$scope, $curriculum] = $this->nationalScope();
        $phaseD = $this->scopes()->addPhase($curriculum, $this->phase('D'));

        $objective = $this->draft($scope, 'Maths', 'Phase C TP.');
        $foreign = $this->outcome($phaseD, 'Maths', 'Phase D CP.', 1);

        // Mirror the OUTCOME's anchor instead, to make its key match.
        $this->expectException(QueryException::class);
        LearningObjectiveLearningOutcome::create([
            'learning_objective_id' => $objective->id,
            'learning_outcome_id' => $foreign->id,
            'curriculum_scope_id' => $foreign->curriculum_scope_id,
            'subject_id' => $foreign->subject_id,
        ]);
    }

    // ------------------------------------------------------------------ draft

    public function test_a_draft_may_start_with_no_outcome_links(): void
    {
        $this->seedReferenceData();
        [$scope] = $this->nationalScope();

        $objective = $this->draft($scope, 'Maths', 'Still being written.');

        $this->assertSame(0, $objective->outcomeLinks()->count());
        $this->assertTrue($objective->isDraft());
    }

    public function test_draft_content_links_and_order_are_editable(): void
    {
        $this->seedReferenceData();
        [$scope] = $this->nationalScope();
        $objective = $this->draft($scope, 'Maths', 'First wording.');
        $cp = $this->outcome($scope, 'Maths', 'CP.', 1);

        $objective->update(['objective_text' => 'Better wording.', 'code' => 'TP-01', 'reference_order' => 5]);
        $this->assertSame('Better wording.', $objective->fresh()->objective_text);
        $this->assertSame(5, $objective->fresh()->reference_order);

        $this->objectives()->linkOutcome($objective, $cp);
        $this->assertSame(1, $objective->outcomeLinks()->count());

        $this->objectives()->unlinkOutcome($objective, $cp);
        $this->assertSame(0, $objective->fresh()->outcomeLinks()->count());
    }

    public function test_an_unused_draft_can_be_deleted_with_its_links(): void
    {
        $this->seedReferenceData();
        [$scope] = $this->nationalScope();
        $objective = $this->draft($scope, 'Maths', 'Mistake.');
        $this->objectives()->linkOutcome($objective, $this->outcome($scope, 'Maths', 'CP.', 1));

        $deletes = $this->auditCount(LearningObjective::class, 'deleted');
        $linkDeletes = $this->auditCount(LearningObjectiveLearningOutcome::class, 'deleted');

        $this->objectives()->delete($objective);

        $this->assertSame(0, LearningObjective::count());
        $this->assertSame(0, LearningObjectiveLearningOutcome::count());
        $this->assertSame($deletes + 1, $this->auditCount(LearningObjective::class, 'deleted'));
        $this->assertSame($linkDeletes + 1, $this->auditCount(LearningObjectiveLearningOutcome::class, 'deleted'));
    }

    // ------------------------------------------------------------- activation

    public function test_an_objective_under_a_draft_curriculum_cannot_be_activated(): void
    {
        $this->seedReferenceData();
        [$scope] = $this->nationalScope();          // curriculum still draft
        $objective = $this->draft($scope, 'Maths', 'TP.');
        $this->objectives()->linkOutcome($objective, $this->outcome($scope, 'Maths', 'CP.', 1));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Activate the curriculum version first');

        $this->objectives()->activate($objective);
    }

    public function test_an_objective_with_no_outcome_link_cannot_be_activated(): void
    {
        $this->seedReferenceData();
        [$scope, $curriculum] = $this->nationalScope();
        $objective = $this->draft($scope, 'Maths', 'TP without a standard.');
        $curriculum->update(['status' => 'active']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Link at least one learning outcome');

        $this->objectives()->activate($objective->fresh());
    }

    public function test_a_linked_objective_under_an_active_curriculum_activates(): void
    {
        $this->seedReferenceData();
        [$scope, $curriculum] = $this->nationalScope();
        $objective = $this->draft($scope, 'Maths', 'TP.');
        $this->objectives()->linkOutcome($objective, $this->outcome($scope, 'Maths', 'CP.', 1));
        $curriculum->update(['status' => 'active']);

        $activated = $this->objectives()->activate($objective->fresh());

        $this->assertTrue($activated->isActive());
    }

    public function test_an_objective_under_an_archived_curriculum_cannot_be_activated(): void
    {
        $this->seedReferenceData();
        [$scope, $curriculum] = $this->nationalScope();
        $objective = $this->draft($scope, 'Maths', 'TP.');
        $this->objectives()->linkOutcome($objective, $this->outcome($scope, 'Maths', 'CP.', 1));
        $curriculum->update(['status' => 'archived']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('archived');

        $this->objectives()->activate($objective->fresh());
    }

    public function test_activation_is_atomic_when_validation_fails(): void
    {
        $this->seedReferenceData();
        [$scope, $curriculum] = $this->nationalScope();
        $curriculum->update(['status' => 'active']);

        $objective = $this->draft($scope, 'Maths', 'Unlinked.');

        try {
            $this->objectives()->activate($objective->fresh());
            $this->fail('activation should have been refused');
        } catch (ValidationException) {
            // Nothing partially applied.
        }

        $this->assertTrue($objective->fresh()->isDraft());
    }

    // ------------------------------------------------------- active / archived

    public function test_an_active_objectives_text_cannot_be_changed(): void
    {
        $this->seedReferenceData();
        $objective = $this->activatedObjective();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot be edited');

        $objective->update(['objective_text' => 'Rewritten.']);
    }

    public function test_an_active_objectives_anchor_cannot_be_changed(): void
    {
        $this->seedReferenceData();
        $objective = $this->activatedObjective();

        $this->expectException(\LogicException::class);
        $objective->update(['subject_id' => $this->subject('Eng')->id]);
    }

    public function test_an_active_objectives_links_cannot_be_changed(): void
    {
        $this->seedReferenceData();
        [$scope, $curriculum] = $this->nationalScope();

        // Both outcomes must exist while the curriculum is still a draft --
        // Phase 5B refuses new CP under an active version.
        $objective = $this->draft($scope, 'Maths', 'TP.');
        $this->objectives()->linkOutcome($objective, $this->outcome($scope, 'Maths', 'CP.', 1));
        $another = $this->outcome($scope, 'Maths', 'Another CP.', 2);

        $curriculum->update(['status' => 'active']);
        $objective = $this->objectives()->activate($objective->fresh());

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cannot change which outcomes');

        $this->objectives()->linkOutcome($objective, $another);
    }

    public function test_an_active_objective_cannot_be_deleted(): void
    {
        $this->seedReferenceData();
        $objective = $this->activatedObjective();

        $this->expectException(ValidationException::class);
        $this->objectives()->delete($objective);
    }

    public function test_an_active_objective_can_be_archived_and_stays_readable(): void
    {
        $this->seedReferenceData();
        $objective = $this->activatedObjective();

        $archived = $this->objectives()->archive($objective);

        $this->assertTrue($archived->isArchived());
        $this->assertSame('TP.', $archived->objective_text);
        $this->assertSame(1, $archived->outcomeLinks()->count(), 'traceability survives archiving');
    }

    public function test_an_archived_objective_cannot_be_edited(): void
    {
        $this->seedReferenceData();
        $objective = $this->objectives()->archive($this->activatedObjective());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('read-only');

        $objective->update(['objective_text' => 'Changed.']);
    }

    // ----------------------------------------------------- revision coexistence

    public function test_a_draft_replacement_may_share_the_active_reference_order_and_code(): void
    {
        $this->seedReferenceData();
        $active = $this->activatedObjective('TP-01');

        $replacement = LearningObjective::create([
            'curriculum_scope_id' => $active->curriculum_scope_id,
            'subject_id' => $active->subject_id,
            'code' => 'TP-01',
            'objective_text' => 'Revised wording.',
            'reference_order' => $active->reference_order,
            'status' => 'draft',
        ]);

        $this->assertSame($active->reference_order, $replacement->reference_order);
        $this->assertSame('TP-01', $replacement->code);
        $this->assertSame(2, LearningObjective::count());
    }

    public function test_activating_a_replacement_while_the_original_is_active_is_refused(): void
    {
        $this->seedReferenceData();
        $active = $this->activatedObjective('TP-01');

        $replacement = LearningObjective::create([
            'curriculum_scope_id' => $active->curriculum_scope_id,
            'subject_id' => $active->subject_id,
            'code' => 'TP-01',
            'objective_text' => 'Revised wording.',
            'reference_order' => $active->reference_order,
            'status' => 'draft',
        ]);
        $this->objectives()->linkOutcome($replacement, $active->outcomeLinks()->first()->learningOutcome);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('reference order');

        $this->objectives()->activate($replacement);
    }

    public function test_archiving_the_original_lets_the_replacement_activate(): void
    {
        $this->seedReferenceData();
        $active = $this->activatedObjective('TP-01');
        $cp = $active->outcomeLinks()->first()->learningOutcome;

        $replacement = LearningObjective::create([
            'curriculum_scope_id' => $active->curriculum_scope_id,
            'subject_id' => $active->subject_id,
            'code' => 'TP-01',
            'objective_text' => 'Revised wording.',
            'reference_order' => $active->reference_order,
            'status' => 'draft',
        ]);
        $this->objectives()->linkOutcome($replacement, $cp);

        $this->objectives()->archive($active);
        $activated = $this->objectives()->activate($replacement->fresh());

        $this->assertTrue($activated->isActive());
        $this->assertTrue($active->fresh()->isArchived(), 'the superseded version is kept');
    }

    public function test_the_database_refuses_two_active_objectives_sharing_a_reference_order(): void
    {
        $this->seedReferenceData();
        $active = $this->activatedObjective();

        $this->expectException(QueryException::class);
        LearningObjective::create([
            'curriculum_scope_id' => $active->curriculum_scope_id,
            'subject_id' => $active->subject_id,
            'objective_text' => 'Second active.',
            'reference_order' => $active->reference_order,
            'status' => 'active',
        ]);
    }

    public function test_the_database_refuses_two_active_objectives_sharing_a_code(): void
    {
        $this->seedReferenceData();
        $active = $this->activatedObjective('TP-01');

        $this->expectException(QueryException::class);
        LearningObjective::create([
            'curriculum_scope_id' => $active->curriculum_scope_id,
            'subject_id' => $active->subject_id,
            'code' => 'TP-01',
            'objective_text' => 'Second active.',
            'reference_order' => 99,
            'status' => 'active',
        ]);
    }

    // ---------------------------------------------------- curriculum lifecycle

    public function test_a_draft_objective_may_be_created_under_a_draft_curriculum(): void
    {
        $this->seedReferenceData();
        [$scope] = $this->nationalScope();

        $this->assertTrue($this->draft($scope, 'Maths', 'TP.')->isDraft());
    }

    public function test_a_draft_objective_may_be_created_under_an_active_curriculum(): void
    {
        $this->seedReferenceData();
        [$scope, $curriculum] = $this->nationalScope();
        $curriculum->update(['status' => 'active']);

        $objective = $this->draft($scope->fresh(), 'Maths', 'Written while in force.');

        $this->assertTrue($objective->isDraft(), 'educators formulate TP while teaching the active curriculum');
    }

    public function test_no_objective_may_be_created_under_an_archived_curriculum(): void
    {
        $this->seedReferenceData();
        [$scope, $curriculum] = $this->nationalScope();
        $curriculum->update(['status' => 'archived']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('archived');

        $this->objectives()->create($scope->fresh(), $this->subject('Maths'), ['objective_text' => 'Too late.']);
    }

    public function test_archiving_a_curriculum_does_not_change_objective_status(): void
    {
        $this->seedReferenceData();
        $objective = $this->activatedObjective();

        $objective->curriculumScope->curriculum->update(['status' => 'archived']);

        $this->assertTrue($objective->fresh()->isActive(), 'historical status stays factual');
    }

    // ------------------------------------------------------------- vocabulary

    public function test_vocabulary_differs_between_national_and_english(): void
    {
        $this->seedReferenceData();
        [, $national] = $this->nationalScope();

        $this->assertSame('Tujuan Pembelajaran (TP)', $national->vocabulary()['objective']);
        $this->assertSame('Learning Objective', $this->englishScope()->curriculum->vocabulary()['objective']);
    }

    // ---------------------------------------------------------- authorization

    public function test_managers_manage_objectives_and_teachers_only_read(): void
    {
        $this->seedReferenceData();
        [$scope] = $this->nationalScope();
        $objective = $this->draft($scope, 'Maths', 'TP.');

        foreach (['admin_staff', 'principal'] as $role) {
            $user = $this->userWithRole($role);
            $this->assertTrue($user->can('create', LearningObjective::class));
            $this->assertTrue($user->can('update', $objective));
            $this->assertTrue($user->can('transition', $objective));
        }

        $teacher = $this->userWithRole('teacher');
        $this->assertTrue($teacher->can('view', $objective));
        $this->assertFalse($teacher->can('create', LearningObjective::class));
        $this->assertFalse($teacher->can('update', $objective));
        $this->assertFalse($teacher->can('transition', $objective));
    }

    public function test_even_a_principal_cannot_edit_an_active_objective(): void
    {
        $this->seedReferenceData();
        $objective = $this->activatedObjective();

        $this->assertFalse($this->userWithRole('principal')->can('update', $objective));
    }

    public function test_an_admin_can_author_and_activate_through_the_scope_screen(): void
    {
        $this->seedReferenceData();
        [$scope, $curriculum] = $this->nationalScope();
        $cp = $this->outcome($scope, 'Maths', 'CP.', 1);
        $curriculum->update(['status' => 'active']);

        $component = Livewire::actingAs($this->userWithRole('admin_staff'))
            ->test(ScopeShow::class, ['curriculum' => $curriculum->fresh(), 'scope' => $scope->fresh()])
            ->call('startAddingObjective')
            ->set('objective_subject_id', (string) $this->subject('Maths')->id)
            ->set('objective_text', 'Through the screen.')
            ->call('saveObjective')
            ->assertHasNoErrors();

        $objective = LearningObjective::firstOrFail();

        $component->call('startLinking', $objective->id)
            ->set('link_outcome_id', (string) $cp->id)
            ->call('linkOutcome')
            ->assertHasNoErrors()
            ->call('activateObjective', $objective->id)
            ->assertHasNoErrors();

        $this->assertTrue($objective->fresh()->isActive());
    }

    public function test_a_teacher_may_read_the_scope_screen(): void
    {
        $this->seedReferenceData();
        [$scope, $curriculum] = $this->nationalScope();
        $this->draft($scope, 'Maths', 'Readable objective.');

        Livewire::actingAs($this->userWithRole('teacher'))
            ->test(ScopeShow::class, ['curriculum' => $curriculum, 'scope' => $scope])
            ->assertOk()
            ->assertSee('Readable objective.');
    }

    // ----------------------------------------------------------------- audit

    public function test_objective_lifecycle_events_are_audited(): void
    {
        $this->seedReferenceData();
        [$scope, $curriculum] = $this->nationalScope();

        $creates = $this->auditCount(LearningObjective::class, 'created');
        $objective = $this->draft($scope, 'Maths', 'TP.');
        $this->assertSame($creates + 1, $this->auditCount(LearningObjective::class, 'created'));

        $updates = $this->auditCount(LearningObjective::class, 'updated');
        $objective->update(['objective_text' => 'Revised.']);
        $this->assertSame($updates + 1, $this->auditCount(LearningObjective::class, 'updated'));

        $this->objectives()->linkOutcome($objective, $this->outcome($scope, 'Maths', 'CP.', 1));
        $curriculum->update(['status' => 'active']);

        $this->objectives()->activate($objective->fresh());
        $this->assertSame($updates + 2, $this->auditCount(LearningObjective::class, 'updated'), 'activation audited');

        $this->objectives()->archive($objective->fresh());
        $this->assertSame($updates + 3, $this->auditCount(LearningObjective::class, 'updated'), 'archiving audited');
    }

    public function test_link_changes_are_audited_and_attach_is_not_used(): void
    {
        $this->seedReferenceData();
        [$scope] = $this->nationalScope();
        $objective = $this->draft($scope, 'Maths', 'TP.');
        $cp = $this->outcome($scope, 'Maths', 'CP.', 1);

        $before = $this->auditCount(LearningObjectiveLearningOutcome::class, 'created');

        // The read-only belongsToMany records nothing, which is exactly why
        // writes go through the model.
        $objective->learningOutcomes()->attach($cp->id, [
            'curriculum_scope_id' => $scope->id, 'subject_id' => $this->subject('Maths')->id,
        ]);
        $this->assertSame($before, $this->auditCount(LearningObjectiveLearningOutcome::class, 'created'), 'attach() records nothing');

        LearningObjectiveLearningOutcome::where('learning_objective_id', $objective->id)->get()->each->delete();

        $this->objectives()->linkOutcome($objective, $cp);
        $this->assertSame($before + 1, $this->auditCount(LearningObjectiveLearningOutcome::class, 'created'), 'model writes audit');

        $deletes = $this->auditCount(LearningObjectiveLearningOutcome::class, 'deleted');
        $this->objectives()->unlinkOutcome($objective, $cp);
        $this->assertGreaterThan($deletes, $this->auditCount(LearningObjectiveLearningOutcome::class, 'deleted'));
    }

    // ---------------------------------------------------------- delete safety

    public function test_an_outcome_linked_to_an_objective_cannot_be_deleted(): void
    {
        $this->seedReferenceData();
        [$scope] = $this->nationalScope();
        $objective = $this->draft($scope, 'Maths', 'TP.');
        $cp = $this->outcome($scope, 'Maths', 'CP.', 1);
        $this->objectives()->linkOutcome($objective, $cp);

        $this->expectException(QueryException::class);
        $cp->delete();
    }

    public function test_a_scope_holding_objectives_cannot_be_removed(): void
    {
        $this->seedReferenceData();
        [$scope] = $this->nationalScope();
        $this->draft($scope, 'Maths', 'TP.');

        $this->expectException(QueryException::class);
        $scope->delete();
    }

    // --------------------------------------------------------------- helpers

    private function seedReferenceData(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(GradeSeeder::class);
        $this->seed(EnglishProgrammeSeeder::class);
        $this->seed(LearningPhaseSeeder::class);

        Subject::firstOrCreate(['name' => 'Maths']);
        Subject::firstOrCreate(['name' => 'Eng']);
    }

    /** @return array{0: CurriculumScope, 1: Curriculum} */
    private function nationalScope(): array
    {
        $curriculum = Curriculum::firstOrCreate(
            ['code' => 'NAT', 'version' => '1'],
            ['name' => 'National', 'effective_from' => '2026-07-01', 'status' => 'draft'],
        );

        $scope = $curriculum->scopes()->where('learning_phase_id', $this->phase('C')->id)->first()
            ?? $this->scopes()->addPhase($curriculum, $this->phase('C'));

        return [$scope, $curriculum];
    }

    private function englishScope(): CurriculumScope
    {
        $curriculum = Curriculum::firstOrCreate(
            ['code' => 'ENG', 'version' => '1'],
            [
                'name' => 'Primary English', 'effective_from' => '2026-07-01', 'status' => 'draft',
                'english_programme_id' => \App\Models\EnglishProgramme::where('name', 'Primary English Programme')->firstOrFail()->id,
            ],
        );

        return $curriculum->scopes()->first()
            ?? $this->scopes()->addEnglishLevel($curriculum, \App\Models\EnglishLevel::where('name', 'Green')->firstOrFail());
    }

    private function draft(CurriculumScope $scope, string $subject, string $text): LearningObjective
    {
        return $this->objectives()->create($scope, $this->subject($subject), ['objective_text' => $text]);
    }

    private function activatedObjective(?string $code = null): LearningObjective
    {
        [$scope, $curriculum] = $this->nationalScope();
        $objective = $this->draft($scope, 'Maths', 'TP.');

        if ($code) {
            $objective->update(['code' => $code]);
        }

        $this->objectives()->linkOutcome($objective, $this->outcome($scope, 'Maths', 'CP.', 1));
        $curriculum->update(['status' => 'active']);

        return $this->objectives()->activate($objective->fresh());
    }

    private function outcome(CurriculumScope $scope, string $subject, string $text, int $sequence): LearningOutcome
    {
        return LearningOutcome::create([
            'curriculum_scope_id' => $scope->id,
            'subject_id' => $this->subject($subject)->id,
            'outcome_text' => $text,
            'sequence' => $sequence,
        ]);
    }

    private function subject(string $name): Subject
    {
        return Subject::where('name', $name)->firstOrFail();
    }

    private function phase(string $code): LearningPhase
    {
        return LearningPhase::where('code', $code)->firstOrFail();
    }

    private function scopes(): CurriculumScopeService
    {
        return app(CurriculumScopeService::class);
    }

    private function objectives(): LearningObjectiveService
    {
        return app(LearningObjectiveService::class);
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
