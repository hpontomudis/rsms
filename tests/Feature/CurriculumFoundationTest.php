<?php

namespace Tests\Feature;

use App\Livewire\Curricula\Create as CurriculumCreate;
use App\Livewire\Curricula\Edit as CurriculumEdit;
use App\Livewire\Curricula\Show as CurriculumShow;
use App\Livewire\LearningPhases\Index as LearningPhaseIndex;
use App\Models\AuditLog;
use App\Models\Curriculum;
use App\Models\EnglishProgramme;
use App\Models\Grade;
use App\Models\LearningPhase;
use App\Models\LearningPhaseGrade;
use App\Models\User;
use Database\Seeders\EnglishProgrammeSeeder;
use Database\Seeders\GradeSeeder;
use Database\Seeders\LearningPhaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 5A: the curriculum and learning-phase reference layer.
 *
 * Learning phases and their grade mappings are approved national structure and
 * are seeded. Curriculum versions are NOT seeded -- a version carries a real
 * school decision, and inventing one to make the table non-empty would be
 * fabricating an effective date nobody set.
 */
class CurriculumFoundationTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------- learning phases

    public function test_exactly_seven_phases_are_seeded_in_order(): void
    {
        $this->seedReferenceData();

        $this->assertSame(
            ['FOUNDATION', 'A', 'B', 'C', 'D', 'E', 'F'],
            LearningPhase::orderBy('sequence')->pluck('code')->all()
        );
        $this->assertSame(
            ['Foundation', 'Phase A', 'Phase B', 'Phase C', 'Phase D', 'Phase E', 'Phase F'],
            LearningPhase::orderBy('sequence')->pluck('name')->all()
        );
    }

    public function test_phase_codes_are_unique(): void
    {
        $this->seedReferenceData();

        $this->expectException(QueryException::class);
        LearningPhase::create(['code' => 'C', 'name' => 'Duplicate', 'sequence' => 99, 'status' => 'active']);
    }

    public function test_phase_sequences_are_unique(): void
    {
        $this->seedReferenceData();

        $this->expectException(QueryException::class);
        LearningPhase::create(['code' => 'G', 'name' => 'Phase G', 'sequence' => 4, 'status' => 'active']);
    }

    /**
     * The approved mapping, checked phase by phase. Note the real seeded grade
     * names are "Kindergarten 1"/"Kindergarten 2", not the KG1/KG2 shorthand.
     */
    public static function phaseMappingProvider(): array
    {
        return [
            'Foundation' => ['FOUNDATION', ['Kindergarten 1', 'Kindergarten 2']],
            'Phase A' => ['A', ['Year 1', 'Year 2']],
            'Phase B' => ['B', ['Year 3', 'Year 4']],
            'Phase C' => ['C', ['Year 5', 'Year 6']],
            'Phase D' => ['D', ['Year 7', 'Year 8', 'Year 9']],
            'Phase E' => ['E', ['Year 10']],
            'Phase F' => ['F', ['Year 11', 'Year 12']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('phaseMappingProvider')]
    public function test_each_phase_maps_to_its_approved_grades(string $code, array $expected): void
    {
        $this->seedReferenceData();

        $grades = LearningPhase::where('code', $code)->firstOrFail()
            ->gradeLinks()->with('grade')->get()
            ->sortBy(fn ($link) => $link->grade->level_order)
            ->pluck('grade.name')->values()->all();

        $this->assertSame($expected, $grades);
    }

    public function test_a_grade_cannot_belong_to_two_learning_phases(): void
    {
        $this->seedReferenceData();
        $year5 = Grade::where('name', 'Year 5')->firstOrFail();

        // UNIQUE(grade_id) -- enforced by the database, not just the seeder.
        $this->expectException(QueryException::class);
        LearningPhaseGrade::create([
            'learning_phase_id' => LearningPhase::where('code', 'D')->firstOrFail()->id,
            'grade_id' => $year5->id,
        ]);
    }

    public function test_existing_grade_rows_are_reused_not_duplicated(): void
    {
        $this->seedReferenceData();

        $this->assertSame(14, Grade::count(), 'the seeder must map existing grades, never create new ones');
        $this->assertSame(14, LearningPhaseGrade::count(), 'every grade is mapped exactly once');
    }

    public function test_the_learning_phase_seeder_is_idempotent(): void
    {
        $this->seedReferenceData();
        $this->seed(LearningPhaseSeeder::class);

        $this->assertSame(7, LearningPhase::count());
        $this->assertSame(14, LearningPhaseGrade::count());
    }

    public function test_a_grade_referenced_by_a_phase_cannot_be_deleted(): void
    {
        $this->seedReferenceData();

        $this->expectException(QueryException::class);
        Grade::where('name', 'Year 5')->firstOrFail()->delete();
    }

    public function test_a_phase_with_grade_mappings_cannot_be_deleted(): void
    {
        $this->seedReferenceData();

        $this->expectException(QueryException::class);
        LearningPhase::where('code', 'C')->firstOrFail()->delete();
    }

    // ------------------------------------------------------------ curriculum

    public function test_no_curriculum_is_seeded_by_default(): void
    {
        $this->seedReferenceData();

        $this->assertSame(0, Curriculum::count(), 'a curriculum version records a real school decision');
    }

    public function test_a_curriculum_version_can_be_created(): void
    {
        $this->seedReferenceData();

        $curriculum = $this->curriculum('NATIONAL', '2026');

        $this->assertSame('draft', $curriculum->status);
        $this->assertFalse($curriculum->isEnglishProgrammeBound());
    }

    public function test_code_and_version_together_are_unique(): void
    {
        $this->seedReferenceData();
        $this->curriculum('NATIONAL', '2026');

        $this->expectException(QueryException::class);
        $this->curriculum('NATIONAL', '2026');
    }

    public function test_several_versions_of_one_code_coexist_as_history(): void
    {
        $this->seedReferenceData();

        $v2025 = $this->curriculum('NATIONAL', '2025', [
            'status' => 'archived', 'effective_from' => '2025-07-01', 'effective_to' => '2026-06-30',
        ]);
        $v2026 = $this->curriculum('NATIONAL', '2026', ['status' => 'active']);

        $this->assertSame(2, Curriculum::where('code', 'NATIONAL')->count());
        $this->assertTrue($v2025->fresh()->isArchived());
        $this->assertTrue($v2026->fresh()->isActive());
    }

    public function test_only_one_version_of_a_code_may_be_active(): void
    {
        $this->seedReferenceData();
        $this->curriculum('NATIONAL', '2025', ['status' => 'active']);

        // Partial unique index on code WHERE status = 'active'.
        $this->expectException(QueryException::class);
        $this->curriculum('NATIONAL', '2026', ['status' => 'active']);
    }

    public function test_different_curriculum_families_may_both_be_active(): void
    {
        $this->seedReferenceData();

        $this->curriculum('NATIONAL', '2026', ['status' => 'active']);
        $this->curriculum('PRI-ENG', '1', [
            'status' => 'active',
            'english_programme_id' => $this->programme('Primary English Programme')->id,
        ]);

        $this->assertSame(2, Curriculum::active()->count());
    }

    public function test_an_effective_to_date_before_effective_from_is_rejected(): void
    {
        $this->seedReferenceData();

        $this->expectException(\InvalidArgumentException::class);
        $this->curriculum('NATIONAL', '2026', [
            'effective_from' => '2026-07-01', 'effective_to' => '2026-01-01',
        ]);
    }

    public function test_the_create_screen_rejects_an_invalid_date_range(): void
    {
        $this->seedReferenceData();

        Livewire::actingAs($this->userWithRole('admin_staff'))
            ->test(CurriculumCreate::class)
            ->set('name', 'National Curriculum')
            ->set('code', 'NATIONAL')
            ->set('version', '2026')
            ->set('effective_from', '2026-07-01')
            ->set('effective_to', '2026-01-01')
            ->call('save')
            ->assertHasErrors('effective_to');
    }

    public function test_a_curriculum_may_bind_to_either_english_programme(): void
    {
        $this->seedReferenceData();

        $primary = $this->curriculum('PRI-ENG', '1', [
            'english_programme_id' => $this->programme('Primary English Programme')->id,
        ]);
        $junior = $this->curriculum('JHS-ENG', '1', [
            'english_programme_id' => $this->programme('Junior High English Programme')->id,
        ]);

        $this->assertTrue($primary->isEnglishProgrammeBound());
        $this->assertSame('Primary English Programme', $primary->englishProgramme->name);
        $this->assertSame('Junior High English Programme', $junior->englishProgramme->name);
    }

    public function test_a_national_curriculum_has_no_english_programme(): void
    {
        $this->seedReferenceData();

        $national = $this->curriculum('NATIONAL', '2026');

        $this->assertNull($national->english_programme_id);
        $this->assertNull($national->englishProgramme);
    }

    public function test_an_english_programme_referenced_by_a_curriculum_cannot_be_deleted(): void
    {
        $this->seedReferenceData();
        $programme = $this->programme('Primary English Programme');
        $this->curriculum('PRI-ENG', '1', ['english_programme_id' => $programme->id]);

        $this->expectException(QueryException::class);
        $programme->delete();
    }

    public function test_archiving_a_curriculum_preserves_the_row(): void
    {
        $this->seedReferenceData();
        $curriculum = $this->curriculum('NATIONAL', '2026', ['status' => 'active']);

        Livewire::actingAs($this->userWithRole('admin_staff'))
            ->test(CurriculumShow::class, ['curriculum' => $curriculum])
            ->call('archive');

        $this->assertDatabaseHas('curricula', ['id' => $curriculum->id, 'status' => 'archived']);
    }

    // ------------------------------------------------------ version lifecycle

    public function test_a_draft_version_may_still_have_its_identity_corrected(): void
    {
        $this->seedReferenceData();
        $draft = $this->curriculum('NATOINAL', '2026');   // typo, never used

        $draft->update(['code' => 'NATIONAL']);

        $this->assertSame('NATIONAL', $draft->fresh()->code);
    }

    public function test_identity_cannot_be_changed_once_a_version_has_left_draft(): void
    {
        $this->seedReferenceData();
        $curriculum = $this->curriculum('NATIONAL', '2026', ['status' => 'active']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('identity cannot be changed');

        $curriculum->update(['version' => '2027']);
    }

    public function test_the_english_programme_binding_is_also_locked_after_draft(): void
    {
        $this->seedReferenceData();
        $curriculum = $this->curriculum('PRI-ENG', '1', [
            'status' => 'active',
            'english_programme_id' => $this->programme('Primary English Programme')->id,
        ]);

        $this->expectException(\LogicException::class);
        $curriculum->update(['english_programme_id' => $this->programme('Junior High English Programme')->id]);
    }

    public function test_permitted_metadata_stays_editable_after_activation(): void
    {
        $this->seedReferenceData();
        $curriculum = $this->curriculum('NATIONAL', '2026', ['status' => 'active']);

        $curriculum->update([
            'name' => 'National Curriculum (revised title)',
            'source_reference' => 'Regulation reference',
            'effective_to' => '2027-06-30',
        ]);

        $this->assertSame('National Curriculum (revised title)', $curriculum->fresh()->name);
        $this->assertSame('2027-06-30', $curriculum->fresh()->effective_to->toDateString());
    }

    /**
     * Superseding is archive-and-create, never overwrite: activating a new
     * version closes the old one, which stays readable.
     */
    public function test_activating_a_new_version_archives_the_previous_one(): void
    {
        $this->seedReferenceData();
        $old = $this->curriculum('NATIONAL', '2025', ['status' => 'active', 'effective_from' => '2025-07-01']);
        $new = $this->curriculum('NATIONAL', '2026', ['effective_from' => '2026-07-01']);

        Livewire::actingAs($this->userWithRole('admin_staff'))
            ->test(CurriculumShow::class, ['curriculum' => $new])
            ->call('activate')
            ->assertHasNoErrors();

        $this->assertTrue($old->fresh()->isArchived());
        $this->assertSame('2026-06-30', $old->fresh()->effective_to->toDateString(), 'closed the day before the successor starts');
        $this->assertTrue($new->fresh()->isActive());
        $this->assertSame(2, Curriculum::where('code', 'NATIONAL')->count(), 'the old version is kept, not replaced');
    }

    public function test_the_edit_screen_locks_identity_fields_after_draft(): void
    {
        $this->seedReferenceData();
        $active = $this->curriculum('NATIONAL', '2026', ['status' => 'active']);
        $draft = $this->curriculum('PRI-ENG', '1');

        $this->assertFalse(
            Livewire::actingAs($this->userWithRole('admin_staff'))
                ->test(CurriculumEdit::class, ['curriculum' => $active])
                ->instance()->identityEditable()
        );
        $this->assertTrue(
            Livewire::actingAs($this->userWithRole('admin_staff'))
                ->test(CurriculumEdit::class, ['curriculum' => $draft])
                ->instance()->identityEditable()
        );
    }

    // --------------------------------------------------------- authorization

    public function test_admin_and_principal_can_manage_curricula(): void
    {
        $this->seedReferenceData();
        $curriculum = $this->curriculum('NATIONAL', '2026');

        foreach (['admin_staff', 'principal'] as $role) {
            $user = $this->userWithRole($role);
            $this->assertTrue($user->can('create', Curriculum::class), "{$role} should create");
            $this->assertTrue($user->can('update', $curriculum), "{$role} should update");
        }
    }

    public function test_a_teacher_may_read_curriculum_reference_data_but_not_change_it(): void
    {
        $this->seedReferenceData();
        $curriculum = $this->curriculum('NATIONAL', '2026');
        $teacher = $this->userWithRole('teacher');

        $this->assertTrue($teacher->can('viewAny', Curriculum::class));
        $this->assertTrue($teacher->can('view', $curriculum));
        $this->assertFalse($teacher->can('create', Curriculum::class));
        $this->assertFalse($teacher->can('update', $curriculum));

        Livewire::actingAs($teacher)->test(CurriculumCreate::class)->assertForbidden();
        Livewire::actingAs($teacher)->test(CurriculumEdit::class, ['curriculum' => $curriculum])->assertForbidden();
    }

    public function test_admin_can_edit_phase_metadata_but_a_teacher_cannot(): void
    {
        $this->seedReferenceData();
        $phase = LearningPhase::where('code', 'C')->firstOrFail();

        Livewire::actingAs($this->userWithRole('admin_staff'))
            ->test(LearningPhaseIndex::class)
            ->call('startEditing', $phase->id)
            ->set('description', 'Year 5 and Year 6 share one set of outcomes.')
            ->set('status', 'active')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Year 5 and Year 6 share one set of outcomes.', $phase->fresh()->description);

        $teacher = $this->userWithRole('teacher');
        $this->assertTrue($teacher->can('viewAny', LearningPhase::class));
        $this->assertFalse($teacher->can('update', $phase));

        Livewire::actingAs($teacher)
            ->test(LearningPhaseIndex::class)
            ->call('startEditing', $phase->id)
            ->assertForbidden();
    }

    public function test_a_teacher_cannot_change_phase_grade_mappings(): void
    {
        $this->seedReferenceData();
        $teacher = $this->userWithRole('teacher');

        // There is no mapping write path in the UI at all, and the policy that
        // would gate one refuses this user.
        $this->assertFalse($teacher->can('update', LearningPhase::where('code', 'C')->firstOrFail()));
        $this->assertFalse(method_exists(LearningPhaseIndex::class, 'linkGrade'));
        $this->assertFalse(method_exists(LearningPhaseIndex::class, 'unlinkGrade'));
    }

    // ----------------------------------------------------------------- audit

    public function test_curriculum_create_update_and_archive_are_audited(): void
    {
        $this->seedReferenceData();

        $created = $this->auditCount(Curriculum::class, 'created');
        $curriculum = $this->curriculum('NATIONAL', '2026');
        $this->assertSame($created + 1, $this->auditCount(Curriculum::class, 'created'));

        $updated = $this->auditCount(Curriculum::class, 'updated');
        $curriculum->update(['name' => 'Renamed']);
        $this->assertSame($updated + 1, $this->auditCount(Curriculum::class, 'updated'));

        $curriculum->update(['status' => 'archived']);
        $this->assertSame($updated + 2, $this->auditCount(Curriculum::class, 'updated'));
    }

    public function test_learning_phase_changes_are_audited(): void
    {
        $this->seedReferenceData();
        $phase = LearningPhase::where('code', 'C')->firstOrFail();

        $before = $this->auditCount(LearningPhase::class, 'updated');
        $phase->update(['description' => 'Covers Year 5 and Year 6.']);

        $this->assertSame($before + 1, $this->auditCount(LearningPhase::class, 'updated'));
    }

    /**
     * The Step 2a-i lesson, re-verified for this mapping: attach()/detach()
     * fire no model events, so writes go through the model.
     */
    public function test_grade_mapping_writes_are_audited_but_attach_would_not_be(): void
    {
        $this->seedReferenceData();
        $phase = LearningPhase::where('code', 'E')->firstOrFail();   // Year 10 only
        $year11 = Grade::where('name', 'Year 11')->firstOrFail();

        LearningPhaseGrade::where('grade_id', $year11->id)->get()->each->delete();

        $createdBefore = $this->auditCount(LearningPhaseGrade::class, 'created');

        $phase->grades()->attach($year11->id);
        $this->assertSame($createdBefore, $this->auditCount(LearningPhaseGrade::class, 'created'), 'attach() records nothing');

        LearningPhaseGrade::where('grade_id', $year11->id)->get()->each->delete();

        LearningPhaseGrade::create(['learning_phase_id' => $phase->id, 'grade_id' => $year11->id]);
        $this->assertSame($createdBefore + 1, $this->auditCount(LearningPhaseGrade::class, 'created'), 'model writes audit');
    }

    // --------------------------------------------------------------- helpers

    private function seedReferenceData(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(GradeSeeder::class);
        $this->seed(EnglishProgrammeSeeder::class);
        $this->seed(LearningPhaseSeeder::class);
    }

    private function programme(string $name): EnglishProgramme
    {
        return EnglishProgramme::where('name', $name)->firstOrFail();
    }

    private function curriculum(string $code, string $version, array $overrides = []): Curriculum
    {
        return Curriculum::create(array_merge([
            'name' => $code.' curriculum',
            'code' => $code,
            'version' => $version,
            'effective_from' => '2026-07-01',
            'status' => 'draft',
        ], $overrides));
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
