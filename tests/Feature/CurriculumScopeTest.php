<?php

namespace Tests\Feature;

use App\Livewire\Curricula\ScopeShow;
use App\Livewire\Curricula\Show as CurriculumShow;
use App\Models\AuditLog;
use App\Models\Curriculum;
use App\Models\CurriculumScope;
use App\Models\EnglishLevel;
use App\Models\Grade;
use App\Models\LearningOutcome;
use App\Models\LearningPhase;
use App\Models\Subject;
use App\Models\User;
use App\Services\CurriculumScopeService;
use Database\Seeders\EnglishProgrammeSeeder;
use Database\Seeders\GradeSeeder;
use Database\Seeders\LearningPhaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 5B: curriculum scopes and learning outcomes.
 *
 * One outcome engine serves both frameworks -- a national Capaian Pembelajaran
 * scoped by Learning Phase, and a Rahai English Learning Outcome scoped by
 * English Level. Only the wording differs.
 */
class CurriculumScopeTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------- scope basis

    public function test_a_national_curriculum_can_be_scoped_to_a_learning_phase(): void
    {
        $this->seedReferenceData();
        $scope = $this->scopes()->addPhase($this->national(), $this->phase('C'));

        $this->assertTrue($scope->isPhaseBased());
        $this->assertSame('Phase C', $scope->displayName());
        $this->assertNull($scope->english_level_id);
        $this->assertNull($scope->english_programme_id);
    }

    public function test_a_national_curriculum_cannot_be_scoped_to_an_english_level(): void
    {
        $this->seedReferenceData();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('scoped by learning phase');

        $this->scopes()->addEnglishLevel($this->national(), $this->level('Green'));
    }

    public function test_a_primary_english_curriculum_can_be_scoped_to_its_own_levels(): void
    {
        $this->seedReferenceData();
        $scope = $this->scopes()->addEnglishLevel($this->primaryEnglish(), $this->level('Green'));

        $this->assertFalse($scope->isPhaseBased());
        $this->assertSame('Green', $scope->displayName());
        $this->assertSame($this->programmeId('Primary English Programme'), $scope->english_programme_id);
    }

    public function test_a_primary_english_curriculum_cannot_use_a_junior_high_level(): void
    {
        $this->seedReferenceData();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Junior High English Programme');

        $this->scopes()->addEnglishLevel($this->primaryEnglish(), $this->level('Level B'));
    }

    public function test_a_junior_high_curriculum_cannot_use_a_primary_level(): void
    {
        $this->seedReferenceData();

        $this->expectException(ValidationException::class);
        $this->scopes()->addEnglishLevel($this->juniorEnglish(), $this->level('Green'));
    }

    public function test_an_english_curriculum_cannot_be_scoped_to_a_learning_phase(): void
    {
        $this->seedReferenceData();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('scoped by English level');

        $this->scopes()->addPhase($this->primaryEnglish(), $this->phase('C'));
    }

    // -------------------------------------------- database-level integrity

    public function test_the_database_refuses_a_scope_with_both_bases(): void
    {
        $this->seedReferenceData();

        $this->expectException(QueryException::class);
        CurriculumScope::create([
            'curriculum_id' => $this->national()->id,
            'english_programme_id' => null,
            'learning_phase_id' => $this->phase('C')->id,
            'english_level_id' => $this->level('Green')->id,
        ]);
    }

    public function test_the_database_refuses_a_scope_with_neither_basis(): void
    {
        $this->seedReferenceData();

        $this->expectException(QueryException::class);
        CurriculumScope::create([
            'curriculum_id' => $this->national()->id,
            'english_programme_id' => null,
            'learning_phase_id' => null,
            'english_level_id' => null,
        ]);
    }

    /**
     * The service is not the only guard. Writing straight to the table with a
     * level from the wrong programme must still fail -- this is what the
     * composite foreign keys are for.
     */
    public function test_the_database_refuses_a_cross_programme_level_even_bypassing_the_service(): void
    {
        $this->seedReferenceData();
        $curriculum = $this->primaryEnglish();

        $this->expectException(QueryException::class);
        CurriculumScope::create([
            'curriculum_id' => $curriculum->id,
            'english_programme_id' => $curriculum->english_programme_id,   // Primary
            'learning_phase_id' => null,
            'english_level_id' => $this->level('Level B')->id,             // Junior High
        ]);
    }

    /**
     * And lying about the discriminator to make the level agree does not help,
     * because the other composite key checks it against the curriculum.
     */
    public function test_the_database_refuses_a_falsified_programme_discriminator(): void
    {
        $this->seedReferenceData();

        $this->expectException(QueryException::class);
        CurriculumScope::create([
            'curriculum_id' => $this->primaryEnglish()->id,
            'english_programme_id' => $this->programmeId('Junior High English Programme'),
            'learning_phase_id' => null,
            'english_level_id' => $this->level('Level B')->id,
        ]);
    }

    public function test_the_database_refuses_an_english_level_scope_on_a_national_curriculum(): void
    {
        $this->seedReferenceData();

        $this->expectException(QueryException::class);
        CurriculumScope::create([
            'curriculum_id' => $this->national()->id,
            'english_programme_id' => $this->programmeId('Primary English Programme'),
            'learning_phase_id' => null,
            'english_level_id' => $this->level('Green')->id,
        ]);
    }

    // ------------------------------------------------------ scope uniqueness

    public function test_the_same_phase_cannot_be_scoped_twice_in_one_version(): void
    {
        $this->seedReferenceData();
        $curriculum = $this->national();
        $this->scopes()->addPhase($curriculum, $this->phase('C'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('already part of this curriculum version');

        $this->scopes()->addPhase($curriculum, $this->phase('C'));
    }

    public function test_the_same_level_cannot_be_scoped_twice_in_one_version(): void
    {
        $this->seedReferenceData();
        $curriculum = $this->primaryEnglish();
        $this->scopes()->addEnglishLevel($curriculum, $this->level('Green'));

        $this->expectException(ValidationException::class);
        $this->scopes()->addEnglishLevel($curriculum, $this->level('Green'));
    }

    public function test_the_same_phase_may_appear_in_a_different_curriculum_version(): void
    {
        $this->seedReferenceData();

        $this->scopes()->addPhase($this->national(), $this->phase('C'));
        $next = $this->curriculum('NATIONAL', '2027');
        $this->scopes()->addPhase($next, $this->phase('C'));

        $this->assertSame(2, CurriculumScope::where('learning_phase_id', $this->phase('C')->id)->count());
    }

    public function test_the_same_level_may_appear_in_a_different_curriculum_version(): void
    {
        $this->seedReferenceData();

        $this->scopes()->addEnglishLevel($this->primaryEnglish(), $this->level('Green'));
        $next = $this->curriculum('PRI-ENG', '2', ['english_programme_id' => $this->programmeId('Primary English Programme')]);
        $this->scopes()->addEnglishLevel($next, $this->level('Green'));

        $this->assertSame(2, CurriculumScope::where('english_level_id', $this->level('Green')->id)->count());
    }

    public function test_a_curriculum_may_hold_several_level_scopes(): void
    {
        $this->seedReferenceData();
        $curriculum = $this->primaryEnglish();

        foreach (['Purple', 'Green', 'Red'] as $name) {
            $this->scopes()->addEnglishLevel($curriculum, $this->level($name));
        }

        $this->assertSame(3, $curriculum->scopes()->count());
    }

    // ----------------------------------------------------- learning outcomes

    public function test_a_phase_scope_can_hold_capaian_pembelajaran(): void
    {
        $this->seedReferenceData();
        $scope = $this->scopes()->addPhase($this->national(), $this->phase('C'));

        $outcome = $this->outcome($scope, $this->subject('Mathematics'), 'Learners can reason with fractions.');

        $this->assertSame('Mathematics', $outcome->subject->name);
        $this->assertSame($scope->id, $outcome->curriculum_scope_id);
    }

    public function test_an_english_level_scope_can_hold_learning_outcomes(): void
    {
        $this->seedReferenceData();
        $scope = $this->scopes()->addEnglishLevel($this->primaryEnglish(), $this->level('Green'));

        $outcome = $this->outcome($scope, $this->subject('English'), 'Learners can hold a short conversation.');

        $this->assertSame($scope->id, $outcome->curriculum_scope_id);
    }

    public function test_outcomes_reuse_ordinary_subject_rows(): void
    {
        $this->seedReferenceData();
        $maths = $this->subject('Mathematics');
        $scope = $this->scopes()->addPhase($this->national(), $this->phase('C'));

        $this->outcome($scope, $maths, 'Something.');

        $this->assertSame(1, Subject::where('name', 'Mathematics')->count(), 'no duplicate subject catalogue');
    }

    public function test_a_learning_outcome_has_no_grade_column(): void
    {
        $this->assertFalse(
            Schema::hasColumn('learning_outcomes', 'grade_id'),
            'CP is scoped through a learning phase, never a grade'
        );
    }

    public function test_a_phase_scope_resolves_its_grades_without_storing_them(): void
    {
        $this->seedReferenceData();
        $scope = $this->scopes()->addPhase($this->national(), $this->phase('C'));

        $this->assertSame(['Year 5', 'Year 6'], $scope->grades()->pluck('name')->all());
    }

    public function test_several_outcomes_may_share_a_scope_and_subject(): void
    {
        $this->seedReferenceData();
        $scope = $this->scopes()->addPhase($this->national(), $this->phase('C'));
        $maths = $this->subject('Mathematics');

        $this->outcome($scope, $maths, 'Element one.', 1);
        $this->outcome($scope, $maths, 'Element two.', 2);

        $this->assertSame(2, $scope->learningOutcomes()->where('subject_id', $maths->id)->count());
    }

    public function test_two_outcomes_cannot_share_a_sequence_within_a_subject(): void
    {
        $this->seedReferenceData();
        $scope = $this->scopes()->addPhase($this->national(), $this->phase('C'));
        $maths = $this->subject('Mathematics');

        $this->outcome($scope, $maths, 'Element one.', 1);

        $this->expectException(QueryException::class);
        $this->outcome($scope, $maths, 'Element two.', 1);
    }

    public function test_outcomes_come_back_in_sequence_order(): void
    {
        $this->seedReferenceData();
        $scope = $this->scopes()->addPhase($this->national(), $this->phase('C'));
        $maths = $this->subject('Mathematics');

        $this->outcome($scope, $maths, 'Third.', 3);
        $this->outcome($scope, $maths, 'First.', 1);
        $this->outcome($scope, $maths, 'Second.', 2);

        $this->assertSame(
            ['First.', 'Second.', 'Third.'],
            $scope->learningOutcomes()->pluck('outcome_text')->all()
        );
    }

    public function test_a_long_curriculum_narrative_persists_intact(): void
    {
        $this->seedReferenceData();
        $scope = $this->scopes()->addPhase($this->national(), $this->phase('C'));

        $narrative = str_repeat('Peserta didik mampu memahami dan menerapkan konsep bilangan. ', 200);
        $outcome = $this->outcome($scope, $this->subject('Mathematics'), $narrative);

        $this->assertSame($narrative, $outcome->fresh()->outcome_text);
        $this->assertGreaterThan(10000, strlen($outcome->fresh()->outcome_text));
    }

    public function test_an_outcome_cannot_reference_a_nonexistent_scope(): void
    {
        $this->seedReferenceData();

        $this->expectException(QueryException::class);
        LearningOutcome::create([
            'curriculum_scope_id' => 99999,
            'subject_id' => $this->subject('Mathematics')->id,
            'outcome_text' => 'Orphan.',
            'sequence' => 1,
        ]);
    }

    // ------------------------------------------------------------- lifecycle

    public function test_draft_scopes_and_outcomes_are_editable_and_removable(): void
    {
        $this->seedReferenceData();
        $curriculum = $this->national();
        $scope = $this->scopes()->addPhase($curriculum, $this->phase('C'));
        $outcome = $this->outcome($scope, $this->subject('Mathematics'), 'Original.');

        $outcome->update(['outcome_text' => 'Corrected.']);
        $this->assertSame('Corrected.', $outcome->fresh()->outcome_text);

        $outcome->delete();
        $this->assertSame(0, $scope->learningOutcomes()->count());

        $this->scopes()->remove($scope->fresh());
        $this->assertSame(0, $curriculum->scopes()->count());
    }

    public function test_a_draft_outcome_can_be_reordered(): void
    {
        $this->seedReferenceData();
        $curriculum = $this->national();
        $scope = $this->scopes()->addPhase($curriculum, $this->phase('C'));
        $maths = $this->subject('Mathematics');

        $first = $this->outcome($scope, $maths, 'First.', 1);
        $second = $this->outcome($scope, $maths, 'Second.', 2);

        Livewire::actingAs($this->userWithRole('admin_staff'))
            ->test(ScopeShow::class, ['curriculum' => $curriculum, 'scope' => $scope])
            ->call('move', $second->id, 'up');

        $this->assertSame(
            ['Second.', 'First.'],
            $scope->fresh()->learningOutcomes()->pluck('outcome_text')->all()
        );
    }

    public function test_an_active_curriculums_outcome_text_cannot_be_changed(): void
    {
        $this->seedReferenceData();
        [$curriculum, $scope, $outcome] = $this->activatedCurriculumWithContent();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('left draft');

        $outcome->update(['outcome_text' => 'Rewritten history.']);
    }

    public function test_an_active_curriculums_outcome_subject_cannot_be_changed(): void
    {
        $this->seedReferenceData();
        [$curriculum, $scope, $outcome] = $this->activatedCurriculumWithContent();

        $this->expectException(\LogicException::class);
        $outcome->update(['subject_id' => $this->subject('English')->id]);
    }

    public function test_an_active_curriculums_outcome_cannot_be_deleted(): void
    {
        $this->seedReferenceData();
        [$curriculum, $scope, $outcome] = $this->activatedCurriculumWithContent();

        $this->expectException(\LogicException::class);
        $outcome->delete();
    }

    public function test_an_active_curriculums_scope_cannot_be_repointed(): void
    {
        $this->seedReferenceData();
        [$curriculum, $scope] = $this->activatedCurriculumWithContent();

        $this->expectException(\LogicException::class);
        $scope->update(['learning_phase_id' => $this->phase('D')->id]);
    }

    public function test_an_active_curriculum_cannot_gain_a_new_scope(): void
    {
        $this->seedReferenceData();
        [$curriculum] = $this->activatedCurriculumWithContent();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('create a new version');

        $this->scopes()->addPhase($curriculum->fresh(), $this->phase('D'));
    }

    public function test_an_active_curriculums_scope_cannot_be_deleted(): void
    {
        $this->seedReferenceData();
        [$curriculum, $scope] = $this->activatedCurriculumWithContent();

        $this->expectException(\LogicException::class);
        $scope->delete();
    }

    public function test_archiving_a_curriculum_keeps_its_scopes_and_outcomes(): void
    {
        $this->seedReferenceData();
        [$curriculum, $scope, $outcome] = $this->activatedCurriculumWithContent();

        $curriculum->update(['status' => 'archived']);

        $this->assertDatabaseHas('curriculum_scopes', ['id' => $scope->id]);
        $this->assertDatabaseHas('learning_outcomes', ['id' => $outcome->id]);
        $this->assertSame('Original.', $outcome->fresh()->outcome_text);
    }

    public function test_a_new_version_carries_its_own_scopes_and_outcomes(): void
    {
        $this->seedReferenceData();
        [$old, $oldScope, $oldOutcome] = $this->activatedCurriculumWithContent();

        $new = $this->curriculum('NATIONAL', '2027');
        $newScope = $this->scopes()->addPhase($new, $this->phase('C'));
        $this->outcome($newScope, $this->subject('Mathematics'), 'Revised standard.');

        $this->assertSame('Original.', $oldOutcome->fresh()->outcome_text, 'the 2026 standard is untouched');
        $this->assertNotSame($oldScope->id, $newScope->id);
        $this->assertSame(2, CurriculumScope::where('learning_phase_id', $this->phase('C')->id)->count());
    }

    // --------------------------------------------------------- delete safety

    public function test_reference_data_behind_a_scope_cannot_be_deleted(): void
    {
        $this->seedReferenceData();
        $this->scopes()->addPhase($this->national(), $this->phase('C'));

        $this->expectException(QueryException::class);
        LearningPhase::where('code', 'C')->firstOrFail()->delete();
    }

    public function test_a_subject_behind_an_outcome_cannot_be_deleted(): void
    {
        $this->seedReferenceData();
        $scope = $this->scopes()->addPhase($this->national(), $this->phase('C'));
        $this->outcome($scope, $this->subject('Mathematics'), 'Something.');

        $this->expectException(QueryException::class);
        $this->subject('Mathematics')->delete();
    }

    public function test_a_scope_with_outcomes_cannot_be_removed(): void
    {
        $this->seedReferenceData();
        $scope = $this->scopes()->addPhase($this->national(), $this->phase('C'));
        $this->outcome($scope, $this->subject('Mathematics'), 'Something.');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('learning outcomes first');

        $this->scopes()->remove($scope);
    }

    public function test_an_english_level_behind_a_scope_cannot_be_deleted(): void
    {
        $this->seedReferenceData();
        $this->scopes()->addEnglishLevel($this->primaryEnglish(), $this->level('Green'));

        $this->expectException(QueryException::class);
        EnglishLevel::where('name', 'Green')->firstOrFail()->delete();
    }

    // -------------------------------------------------------------- UI / vocab

    public function test_the_scope_selector_offers_only_the_right_bases(): void
    {
        $this->seedReferenceData();

        $englishBases = $this->scopes()->availableBases($this->primaryEnglish())->pluck('name');
        $this->assertSame(['Purple', 'Pink', 'Gold', 'Green', 'Blue', 'Red'], $englishBases->all());
        $this->assertNotContains('Level B', $englishBases->all());

        $nationalBases = $this->scopes()->availableBases($this->national())->pluck('name');
        $this->assertSame(
            ['Foundation', 'Phase A', 'Phase B', 'Phase C', 'Phase D', 'Phase E', 'Phase F'],
            $nationalBases->all()
        );
    }

    public function test_vocabulary_follows_the_curriculum_kind(): void
    {
        $this->seedReferenceData();

        $this->assertSame('Capaian Pembelajaran (CP)', $this->national()->vocabulary()['outcome']);
        $this->assertSame('Fase', $this->national()->vocabulary()['basis']);

        $this->assertSame('Learning Outcome', $this->primaryEnglish()->vocabulary()['outcome']);
        $this->assertSame('Level', $this->primaryEnglish()->vocabulary()['basis']);
    }

    public function test_an_admin_can_add_a_scope_through_the_curriculum_screen(): void
    {
        $this->seedReferenceData();
        $curriculum = $this->national();

        Livewire::actingAs($this->userWithRole('admin_staff'))
            ->test(CurriculumShow::class, ['curriculum' => $curriculum])
            ->set('showAddScope', true)
            ->set('scope_basis_id', (string) $this->phase('C')->id)
            ->call('addScope')
            ->assertHasNoErrors();

        $this->assertSame(1, $curriculum->scopes()->count());
    }

    public function test_the_screen_reports_a_cross_programme_attempt_as_a_field_error(): void
    {
        $this->seedReferenceData();

        Livewire::actingAs($this->userWithRole('admin_staff'))
            ->test(CurriculumShow::class, ['curriculum' => $this->primaryEnglish()])
            ->set('showAddScope', true)
            ->set('scope_basis_id', (string) $this->level('Level B')->id)
            ->call('addScope')
            ->assertHasErrors('english_level_id');
    }

    // --------------------------------------------------------- authorization

    public function test_managers_can_edit_draft_content_and_teachers_cannot(): void
    {
        $this->seedReferenceData();
        $curriculum = $this->national();
        $scope = $this->scopes()->addPhase($curriculum, $this->phase('C'));
        $outcome = $this->outcome($scope, $this->subject('Mathematics'), 'Draft text.');

        foreach (['admin_staff', 'principal'] as $role) {
            $user = $this->userWithRole($role);
            $this->assertTrue($user->can('create', LearningOutcome::class), "{$role} should create");
            $this->assertTrue($user->can('update', $outcome), "{$role} should edit a draft outcome");
            $this->assertTrue($user->can('delete', $scope), "{$role} should remove a draft scope");
        }

        $teacher = $this->userWithRole('teacher');
        $this->assertTrue($teacher->can('view', $scope));
        $this->assertTrue($teacher->can('view', $outcome));
        $this->assertFalse($teacher->can('create', LearningOutcome::class));
        $this->assertFalse($teacher->can('update', $outcome));
        $this->assertFalse($teacher->can('delete', $scope));
    }

    /**
     * The activation rule is not a permission question -- it binds managers
     * too. Only a new version changes a published standard.
     */
    public function test_even_a_principal_cannot_edit_activated_content(): void
    {
        $this->seedReferenceData();
        [$curriculum, $scope, $outcome] = $this->activatedCurriculumWithContent();

        $principal = $this->userWithRole('principal');

        $this->assertTrue($principal->can('view', $outcome));
        $this->assertFalse($principal->can('update', $outcome));
        $this->assertFalse($principal->can('delete', $outcome));
        $this->assertFalse($principal->can('update', $scope));
    }

    public function test_a_teacher_may_read_scope_and_outcome_screens(): void
    {
        $this->seedReferenceData();
        $curriculum = $this->national();
        $scope = $this->scopes()->addPhase($curriculum, $this->phase('C'));
        $this->outcome($scope, $this->subject('Mathematics'), 'Readable.');

        Livewire::actingAs($this->userWithRole('teacher'))
            ->test(ScopeShow::class, ['curriculum' => $curriculum, 'scope' => $scope])
            ->assertOk()
            ->assertSee('Readable.');
    }

    // ----------------------------------------------------------------- audit

    public function test_scope_and_outcome_changes_are_audited(): void
    {
        $this->seedReferenceData();
        $curriculum = $this->national();

        $scopeCreates = $this->auditCount(CurriculumScope::class, 'created');
        $scope = $this->scopes()->addPhase($curriculum, $this->phase('C'));
        $this->assertSame($scopeCreates + 1, $this->auditCount(CurriculumScope::class, 'created'));

        $outcomeCreates = $this->auditCount(LearningOutcome::class, 'created');
        $outcome = $this->outcome($scope, $this->subject('Mathematics'), 'First.');
        $this->assertSame($outcomeCreates + 1, $this->auditCount(LearningOutcome::class, 'created'));

        $updates = $this->auditCount(LearningOutcome::class, 'updated');
        $outcome->update(['outcome_text' => 'Corrected.']);
        $this->assertSame($updates + 1, $this->auditCount(LearningOutcome::class, 'updated'));

        $deletes = $this->auditCount(LearningOutcome::class, 'deleted');
        $outcome->delete();
        $this->assertSame($deletes + 1, $this->auditCount(LearningOutcome::class, 'deleted'));

        $scopeDeletes = $this->auditCount(CurriculumScope::class, 'deleted');
        $this->scopes()->remove($scope->fresh());
        $this->assertSame($scopeDeletes + 1, $this->auditCount(CurriculumScope::class, 'deleted'));
    }

    public function test_reordering_is_audited(): void
    {
        $this->seedReferenceData();
        $curriculum = $this->national();
        $scope = $this->scopes()->addPhase($curriculum, $this->phase('C'));
        $maths = $this->subject('Mathematics');

        $this->outcome($scope, $maths, 'First.', 1);
        $second = $this->outcome($scope, $maths, 'Second.', 2);

        $before = $this->auditCount(LearningOutcome::class, 'updated');

        Livewire::actingAs($this->userWithRole('admin_staff'))
            ->test(ScopeShow::class, ['curriculum' => $curriculum, 'scope' => $scope])
            ->call('move', $second->id, 'up');

        $this->assertGreaterThan($before, $this->auditCount(LearningOutcome::class, 'updated'));
    }

    // --------------------------------------------------------------- helpers

    private function seedReferenceData(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(GradeSeeder::class);
        $this->seed(EnglishProgrammeSeeder::class);
        $this->seed(LearningPhaseSeeder::class);

        Subject::firstOrCreate(['name' => 'Mathematics']);
        Subject::firstOrCreate(['name' => 'English']);
    }

    private function scopes(): CurriculumScopeService
    {
        return app(CurriculumScopeService::class);
    }

    private function national(): Curriculum
    {
        return $this->curriculum('NATIONAL', '2026');
    }

    private function primaryEnglish(): Curriculum
    {
        return $this->curriculum('PRI-ENG', '1', [
            'english_programme_id' => $this->programmeId('Primary English Programme'),
        ]);
    }

    private function juniorEnglish(): Curriculum
    {
        return $this->curriculum('JHS-ENG', '1', [
            'english_programme_id' => $this->programmeId('Junior High English Programme'),
        ]);
    }

    private function curriculum(string $code, string $version, array $overrides = []): Curriculum
    {
        return Curriculum::firstOrCreate(
            ['code' => $code, 'version' => $version],
            array_merge([
                'name' => $code.' curriculum',
                'effective_from' => '2026-07-01',
                'status' => 'draft',
            ], $overrides),
        );
    }

    /** @return array{0: Curriculum, 1: CurriculumScope, 2: LearningOutcome} */
    private function activatedCurriculumWithContent(): array
    {
        $curriculum = $this->national();
        $scope = $this->scopes()->addPhase($curriculum, $this->phase('C'));
        $outcome = $this->outcome($scope, $this->subject('Mathematics'), 'Original.');

        $curriculum->update(['status' => 'active']);

        return [$curriculum->fresh(), $scope->fresh(), $outcome->fresh()];
    }

    private function outcome(CurriculumScope $scope, Subject $subject, string $text, int $sequence = 1): LearningOutcome
    {
        return LearningOutcome::create([
            'curriculum_scope_id' => $scope->id,
            'subject_id' => $subject->id,
            'outcome_text' => $text,
            'sequence' => $sequence,
        ]);
    }

    private function phase(string $code): LearningPhase
    {
        return LearningPhase::where('code', $code)->firstOrFail();
    }

    private function level(string $name): EnglishLevel
    {
        return EnglishLevel::where('name', $name)->firstOrFail();
    }

    private function subject(string $name): Subject
    {
        return Subject::where('name', $name)->firstOrFail();
    }

    private function programmeId(string $name): int
    {
        return \App\Models\EnglishProgramme::where('name', $name)->firstOrFail()->id;
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
