<?php

namespace Tests\Feature;

use App\Livewire\EnglishProgrammes\Show as ProgrammeShow;
use App\Models\AuditLog;
use App\Models\EnglishLevel;
use App\Models\EnglishProgramme;
use App\Models\EnglishProgrammeGrade;
use App\Models\Grade;
use App\Models\User;
use Database\Seeders\EnglishProgrammeSeeder;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step 2a-i: English programmes, levels, and grade applicability.
 *
 * Rahai runs more than one proficiency framework, so nothing here may assume a
 * single global level ladder -- uniqueness is scoped per programme, and which
 * grades a programme covers is data rather than a branch in code.
 */
class EnglishProgrammeTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------- structure

    public function test_programme_names_are_unique(): void
    {
        EnglishProgramme::create(['name' => 'Primary English Programme']);

        $this->expectException(QueryException::class);
        EnglishProgramme::create(['name' => 'Primary English Programme']);
    }

    public function test_level_names_are_unique_within_a_programme(): void
    {
        $programme = EnglishProgramme::create(['name' => 'Primary']);
        EnglishLevel::create(['english_programme_id' => $programme->id, 'name' => 'Green', 'sequence' => 1]);

        $this->expectException(QueryException::class);
        EnglishLevel::create(['english_programme_id' => $programme->id, 'name' => 'Green', 'sequence' => 2]);
    }

    public function test_level_sequences_are_unique_within_a_programme(): void
    {
        $programme = EnglishProgramme::create(['name' => 'Primary']);
        EnglishLevel::create(['english_programme_id' => $programme->id, 'name' => 'Green', 'sequence' => 1]);

        $this->expectException(QueryException::class);
        EnglishLevel::create(['english_programme_id' => $programme->id, 'name' => 'Blue', 'sequence' => 1]);
    }

    public function test_the_same_level_name_is_permitted_in_different_programmes(): void
    {
        $primary = EnglishProgramme::create(['name' => 'Primary']);
        $junior = EnglishProgramme::create(['name' => 'Junior High']);

        EnglishLevel::create(['english_programme_id' => $primary->id, 'name' => 'Level A', 'sequence' => 1]);
        EnglishLevel::create(['english_programme_id' => $junior->id, 'name' => 'Level A', 'sequence' => 1]);

        $this->assertSame(2, EnglishLevel::where('name', 'Level A')->count());
    }

    public function test_a_grade_cannot_belong_to_two_programmes(): void
    {
        $this->seed(GradeSeeder::class);
        $primary = EnglishProgramme::create(['name' => 'Primary']);
        $junior = EnglishProgramme::create(['name' => 'Junior High']);
        $year5 = Grade::where('name', 'Year 5')->firstOrFail();

        EnglishProgrammeGrade::create(['english_programme_id' => $primary->id, 'grade_id' => $year5->id]);

        // UNIQUE(grade_id) -- enforced by the database, not just the UI.
        $this->expectException(QueryException::class);
        EnglishProgrammeGrade::create(['english_programme_id' => $junior->id, 'grade_id' => $year5->id]);
    }

    public function test_a_grade_may_belong_to_no_programme(): void
    {
        $this->seedReferenceData();

        $unmapped = Grade::whereDoesntHave('englishProgrammeLink')->pluck('name');

        $this->assertNotEmpty($unmapped);
        $this->assertNull(Grade::where('name', 'Year 12')->first()->englishProgramme());
    }

    // ------------------------------------------------------------- seed data

    public function test_exactly_two_programmes_are_seeded(): void
    {
        $this->seedReferenceData();
        $this->assertSame(2, EnglishProgramme::count());
    }

    public function test_exactly_nine_levels_are_seeded(): void
    {
        $this->seedReferenceData();
        $this->assertSame(9, EnglishLevel::count());
    }

    public function test_exactly_nine_grade_mappings_are_seeded(): void
    {
        $this->seedReferenceData();
        $this->assertSame(9, EnglishProgrammeGrade::count());
    }

    public function test_primary_levels_are_ordered_purple_through_red(): void
    {
        $this->seedReferenceData();

        $this->assertSame(
            ['Purple', 'Pink', 'Gold', 'Green', 'Blue', 'Red'],
            $this->programme('Primary English Programme')->levels->pluck('name')->all()
        );
    }

    public function test_junior_high_levels_are_ordered_a_through_c(): void
    {
        $this->seedReferenceData();

        $this->assertSame(
            ['Level A', 'Level B', 'Level C'],
            $this->programme('Junior High English Programme')->levels->pluck('name')->all()
        );
    }

    public function test_red_exists_as_a_valid_level_with_no_students(): void
    {
        $this->seedReferenceData();

        $red = EnglishLevel::where('name', 'Red')->first();

        $this->assertNotNull($red, 'Red must exist even though no student occupies it');
        $this->assertSame(6, $red->sequence);
        $this->assertTrue($red->isActive());
    }

    public function test_kindergarten_grades_have_no_programme(): void
    {
        $this->seedReferenceData();

        foreach (['Kindergarten 1', 'Kindergarten 2'] as $name) {
            $this->assertNull(Grade::where('name', $name)->first()->englishProgramme(), "{$name} must not map to a programme");
        }
    }

    public function test_senior_high_grades_have_no_programme(): void
    {
        $this->seedReferenceData();

        foreach (['Year 10', 'Year 11', 'Year 12'] as $name) {
            $this->assertNull(Grade::where('name', $name)->first()->englishProgramme(), "{$name} must not map to a programme");
        }
    }

    public function test_the_seeder_is_idempotent(): void
    {
        $this->seedReferenceData();
        $this->seed(EnglishProgrammeSeeder::class);

        $this->assertSame(2, EnglishProgramme::count());
        $this->assertSame(9, EnglishLevel::count());
        $this->assertSame(9, EnglishProgrammeGrade::count());
    }

    // ---------------------------------------------------------- delete safety

    public function test_a_programme_with_levels_cannot_be_deleted(): void
    {
        $this->seedReferenceData();

        $this->expectException(QueryException::class);
        $this->programme('Primary English Programme')->delete();
    }

    public function test_a_grade_referenced_by_a_programme_cannot_be_deleted(): void
    {
        $this->seedReferenceData();

        $this->expectException(QueryException::class);
        Grade::where('name', 'Year 5')->first()->delete();
    }

    // ----------------------------------------------------------- authorization

    public function test_admin_staff_and_principal_can_manage_but_teacher_cannot(): void
    {
        $this->seedReferenceData();
        $programme = $this->programme('Primary English Programme');

        foreach (['admin_staff', 'principal'] as $role) {
            $user = $this->userWithRole($role);
            $this->assertTrue($user->can('create', EnglishProgramme::class), "{$role} should create");
            $this->assertTrue($user->can('update', $programme), "{$role} should update");
        }

        $teacher = $this->userWithRole('teacher');
        $this->assertFalse($teacher->can('create', EnglishProgramme::class));
        $this->assertFalse($teacher->can('update', $programme));
    }

    public function test_teacher_and_management_can_view(): void
    {
        $this->seedReferenceData();
        $programme = $this->programme('Primary English Programme');

        foreach (['teacher', 'management'] as $role) {
            $this->assertTrue($this->userWithRole($role)->can('view', $programme), "{$role} should view");
        }
    }

    // ------------------------------------------------------------------ audit

    public function test_programme_and_level_changes_are_audited(): void
    {
        $programme = EnglishProgramme::create(['name' => 'Primary']);
        $this->assertSame(1, $this->auditsFor(EnglishProgramme::class, 'created'));

        $programme->update(['status' => 'archived']);
        $this->assertSame(1, $this->auditsFor(EnglishProgramme::class, 'updated'));

        $level = EnglishLevel::create(['english_programme_id' => $programme->id, 'name' => 'Green', 'sequence' => 1]);
        $this->assertSame(1, $this->auditsFor(EnglishLevel::class, 'created'));

        $level->update(['sequence' => 2]);   // reorder
        $this->assertSame(1, $this->auditsFor(EnglishLevel::class, 'updated'));
    }

    public function test_linking_a_grade_to_a_programme_is_audited(): void
    {
        $this->seedReferenceData();
        $admin = $this->userWithRole('admin_staff');
        $programme = $this->programme('Primary English Programme');
        $kg = Grade::where('name', 'Kindergarten 1')->firstOrFail();

        $before = $this->auditsFor(EnglishProgrammeGrade::class, 'created');

        Livewire::actingAs($admin)
            ->test(ProgrammeShow::class, ['englishProgramme' => $programme])
            ->set('grade_id', (string) $kg->id)
            ->call('linkGrade');

        $this->assertDatabaseHas('english_programme_grade', [
            'english_programme_id' => $programme->id, 'grade_id' => $kg->id,
        ]);
        $this->assertSame($before + 1, $this->auditsFor(EnglishProgrammeGrade::class, 'created'));
    }

    public function test_unlinking_a_grade_from_a_programme_is_audited(): void
    {
        $this->seedReferenceData();
        $admin = $this->userWithRole('admin_staff');
        $programme = $this->programme('Primary English Programme');
        $year6 = Grade::where('name', 'Year 6')->firstOrFail();

        $before = $this->auditsFor(EnglishProgrammeGrade::class, 'deleted');

        Livewire::actingAs($admin)
            ->test(ProgrammeShow::class, ['englishProgramme' => $programme])
            ->call('unlinkGrade', $year6->id);

        $this->assertDatabaseMissing('english_programme_grade', ['grade_id' => $year6->id]);
        $this->assertSame($before + 1, $this->auditsFor(EnglishProgrammeGrade::class, 'deleted'));
    }

    /**
     * Guards the reason EnglishProgrammeGrade is a full model rather than a
     * Pivot: attach()/detach() go through the query builder and fire no
     * Eloquent events, so an Auditable pivot used that way records nothing.
     */
    public function test_attach_bypasses_auditing_which_is_why_writes_use_the_model(): void
    {
        $this->seedReferenceData();
        $programme = $this->programme('Primary English Programme');
        $kg = Grade::where('name', 'Kindergarten 1')->firstOrFail();

        $createdBefore = $this->auditsFor(EnglishProgrammeGrade::class, 'created');
        $deletedBefore = $this->auditsFor(EnglishProgrammeGrade::class, 'deleted');

        $programme->grades()->attach($kg->id);
        $this->assertSame($createdBefore, $this->auditsFor(EnglishProgrammeGrade::class, 'created'), 'attach() records nothing -- hence model writes');

        $programme->grades()->detach($kg->id);
        $this->assertSame($deletedBefore, $this->auditsFor(EnglishProgrammeGrade::class, 'deleted'), 'detach() records nothing -- hence model writes');

        EnglishProgrammeGrade::create(['english_programme_id' => $programme->id, 'grade_id' => $kg->id]);
        $this->assertSame($createdBefore + 1, $this->auditsFor(EnglishProgrammeGrade::class, 'created'), 'model writes must audit');
    }

    // -------------------------------------------------------------- behaviour

    public function test_a_grade_already_claimed_is_not_offered_to_another_programme(): void
    {
        $this->seedReferenceData();
        $admin = $this->userWithRole('admin_staff');
        $junior = $this->programme('Junior High English Programme');

        $available = Livewire::actingAs($admin)
            ->test(ProgrammeShow::class, ['englishProgramme' => $junior])
            ->viewData('availableGrades')
            ->pluck('name');

        $this->assertNotContains('Year 5', $available, 'Year 5 belongs to Primary and must not be offered');
        $this->assertContains('Year 10', $available, 'unclaimed grades remain offerable');
    }

    public function test_reordering_levels_swaps_their_sequence(): void
    {
        $this->seedReferenceData();
        $admin = $this->userWithRole('admin_staff');
        $programme = $this->programme('Primary English Programme');
        $gold = $programme->levels->firstWhere('name', 'Gold');

        Livewire::actingAs($admin)
            ->test(ProgrammeShow::class, ['englishProgramme' => $programme])
            ->call('moveLevel', $gold->id, 'up');

        $this->assertSame(
            ['Purple', 'Gold', 'Pink', 'Green', 'Blue', 'Red'],
            $programme->fresh()->levels->pluck('name')->all()
        );
    }

    // ---------------------------------------------------------------- helpers

    private function seedReferenceData(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(GradeSeeder::class);
        $this->seed(EnglishProgrammeSeeder::class);
    }

    private function programme(string $name): EnglishProgramme
    {
        return EnglishProgramme::where('name', $name)->firstOrFail();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function auditsFor(string $type, string $action): int
    {
        return AuditLog::where('auditable_type', $type)->where('action', $action)->count();
    }
}
