<?php

namespace Tests\Feature\Concerns;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\AnnualProgramme;
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
use App\Models\SemesterProgramme;
use App\Models\Staff;
use App\Models\Subject;
use App\Models\TeachingGroup;
use App\Models\User;
use App\Services\AnnualProgrammeService;
use App\Services\CurriculumScopeService;
use App\Services\SemesterProgrammeService;
use Database\Seeders\EnglishProgrammeSeeder;
use Database\Seeders\GradeSeeder;
use Database\Seeders\LearningPhaseSeeder;
use Database\Seeders\PositionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * The curriculum -> pathway -> roster graph both planning suites need.
 *
 * Shared as a trait rather than by inheritance, so neither suite re-runs the
 * other's tests.
 */
trait BuildsPlanningFixtures
{
    protected AcademicYear $year;



    protected function seedReferenceData(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(GradeSeeder::class);
        $this->seed(PositionSeeder::class);
        $this->seed(EnglishProgrammeSeeder::class);
        $this->seed(LearningPhaseSeeder::class);

        Subject::firstOrCreate(['name' => 'Maths']);
        Subject::firstOrCreate(['name' => 'Eng']);

        $this->year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_current' => true,
        ]);

        AcademicPeriod::create(['academic_year_id' => $this->year->id, 'name' => 'Semester 1', 'sequence' => 1, 'start_date' => '2026-07-01', 'end_date' => '2026-12-31']);
        AcademicPeriod::create(['academic_year_id' => $this->year->id, 'name' => 'Semester 2', 'sequence' => 2, 'start_date' => '2027-01-01', 'end_date' => '2027-06-30']);
    }

    protected function curriculum(): Curriculum
    {
        return Curriculum::firstOrCreate(
            ['code' => 'NAT', 'version' => '1'],
            ['name' => 'National', 'effective_from' => '2026-07-01', 'status' => 'active'],
        );
    }

    protected function englishCurriculum(): Curriculum
    {
        return Curriculum::firstOrCreate(
            ['code' => 'ENG', 'version' => '1'],
            [
                'name' => 'Primary English', 'effective_from' => '2026-07-01', 'status' => 'active',
                'english_programme_id' => \App\Models\EnglishProgramme::where('name', 'Primary English Programme')->firstOrFail()->id,
            ],
        );
    }

    protected function scopeFor(string $phaseCode): CurriculumScope
    {
        $phase = LearningPhase::where('code', $phaseCode)->firstOrFail();
        $curriculum = $this->curriculum();

        return $curriculum->scopes()->where('learning_phase_id', $phase->id)->first()
            ?? app(CurriculumScopeService::class)->addPhase($this->asDraft($curriculum), $phase);
    }

    protected function englishScope(string $levelName): CurriculumScope
    {
        $level = EnglishLevel::where('name', $levelName)->firstOrFail();
        $curriculum = $this->englishCurriculum();

        return $curriculum->scopes()->where('english_level_id', $level->id)->first()
            ?? app(CurriculumScopeService::class)->addEnglishLevel($this->asDraft($curriculum), $level);
    }

    /** Scopes may only be added while a curriculum is draft; flip and restore. */
    protected function asDraft(Curriculum $curriculum): Curriculum
    {
        $original = $curriculum->status;
        $curriculum->update(['status' => 'draft']);

        return tap($curriculum->fresh(), fn () => null) ?: $curriculum;
    }

    protected function restoreActive(Curriculum $curriculum): void
    {
        if (! $curriculum->fresh()->isActive()) {
            $curriculum->update(['status' => 'active']);
        }
    }

    protected function pathway(): LearningPathway
    {
        return $this->pathwayFor('C', 'Maths', 'Phase C route');
    }

    protected function pathwayFor(string $phaseCode, string $subject, string $title): LearningPathway
    {
        $scope = $this->scopeFor($phaseCode);
        $this->restoreActive($this->curriculum());

        return LearningPathway::firstOrCreate(
            ['curriculum_scope_id' => $scope->id, 'subject_id' => $this->subject($subject)->id, 'title' => $title],
            ['status' => 'active'],
        );
    }

    protected function englishPathway(string $levelName): LearningPathway
    {
        $scope = $this->englishScope($levelName);
        $this->restoreActive($this->englishCurriculum());

        return LearningPathway::firstOrCreate(
            ['curriculum_scope_id' => $scope->id, 'subject_id' => $this->subject('Eng')->id, 'title' => $levelName.' route'],
            ['status' => 'active'],
        );
    }

    protected function objectiveIn(CurriculumScope $scope, string $subject, string $text, int $order): LearningObjective
    {
        return LearningObjective::firstOrCreate(
            ['curriculum_scope_id' => $scope->id, 'subject_id' => $this->subject($subject)->id, 'objective_text' => $text],
            ['reference_order' => $order, 'status' => 'active', 'code' => 'TP-'.$order],
        );
    }

    protected function itemOn(LearningPathway $pathway, LearningObjective $objective, int $position): LearningPathwayItem
    {
        return LearningPathwayItem::firstOrCreate(
            ['learning_pathway_id' => $pathway->id, 'learning_objective_id' => $objective->id],
            ['curriculum_scope_id' => $pathway->curriculum_scope_id, 'subject_id' => $pathway->subject_id, 'position' => $position],
        );
    }

    protected function pathwayItem(int $position): LearningPathwayItem
    {
        $pathway = $this->pathway();

        return $pathway->items()->where('position', $position)->first()
            ?? $this->itemOn($pathway, $this->objectiveIn($this->scopeFor('C'), 'Maths', "TP {$position}", $position), $position);
    }

    protected function classProgramme(): AnnualProgramme
    {
        return $this->programmes()->createForClass($this->class('Year 5', 'Year 5A'), $this->subject('Maths'), $this->pathway());
    }

    protected function groupProgramme(): AnnualProgramme
    {
        return $this->programmes()->createForGroup($this->group('Green'), $this->subject('Eng'), $this->englishPathway('Green'));
    }

    protected function activatedClassProgramme(): AnnualProgramme
    {
        $programme = $this->classProgramme();
        $this->programmes()->addItem($programme, $this->pathwayItem(1), $this->period('Semester 1'), 8);

        return $this->programmes()->activate($programme->fresh());
    }

    /** @return array{0: AnnualProgramme, 1: SemesterProgramme} */
    protected function scheduledProgramme(): array
    {
        $annual = $this->activatedClassProgramme();
        $semester = app(SemesterProgrammeService::class)->create($annual, $this->period('Semester 1'));
        app(SemesterProgrammeService::class)->addSlot($semester, $annual->items()->first(), ['week_label' => 'Week 1', 'planned_lesson_periods' => 8]);

        return [$annual->fresh(), $semester->fresh()];
    }

    protected function class(string $gradeName, string $name): SchoolClass
    {
        return SchoolClass::firstOrCreate([
            'name' => $name,
            'grade_id' => Grade::where('name', $gradeName)->firstOrFail()->id,
            'academic_year_id' => $this->year->id,
        ]);
    }

    protected function group(string $levelName): TeachingGroup
    {
        return TeachingGroup::firstOrCreate(
            ['academic_year_id' => $this->year->id, 'name' => $levelName.' A'],
            ['english_level_id' => EnglishLevel::where('name', $levelName)->firstOrFail()->id, 'status' => 'active'],
        );
    }

    protected function period(string $name): AcademicPeriod
    {
        return AcademicPeriod::where('academic_year_id', $this->year->id)->where('name', $name)->firstOrFail();
    }

    protected function subject(string $name): Subject
    {
        return Subject::where('name', $name)->firstOrFail();
    }

    protected function programmes(): AnnualProgrammeService
    {
        return app(AnnualProgrammeService::class);
    }

    protected function teacherFor(string $className, string $subject, string $key, string $from = '2026-07-01'): User
    {
        $staff = $this->staff($key);
        $class = SchoolClass::where('name', $className)->firstOrFail();

        ClassSubject::create([
            'class_id' => $class->id, 'subject_id' => $this->subject($subject)->id,
            'staff_id' => $staff->id, 'started_on' => $from,
        ]);

        return $staff->user->fresh();
    }

    protected function groupTeacherFor(string $levelName, string $subject, string $key, string $from = '2026-07-01'): User
    {
        $staff = $this->staff($key);

        ClassSubject::create([
            'teaching_group_id' => $this->group($levelName)->id, 'subject_id' => $this->subject($subject)->id,
            'staff_id' => $staff->id, 'started_on' => $from,
        ]);

        return $staff->user->fresh();
    }

    protected function staff(string $key): Staff
    {
        $user = User::firstOrCreate(
            ['email' => strtolower($key).'@rahai.test'],
            ['name' => $key, 'password' => bcrypt('password'), 'status' => 'active'],
        );

        if (! $user->hasRole('teacher')) {
            $user->assignRole('teacher');
        }

        return Staff::firstOrCreate(
            ['staff_number' => 'S-'.strtoupper($key)],
            [
                'first_name' => $key, 'last_name' => 'Teacher',
                'position_id' => Position::firstOrFail()->id, 'phone' => '08',
                'hire_date' => '2020-01-01', 'status' => 'active', 'user_id' => $user->id,
            ],
        );
    }

    protected function userWithRole(string $role): User
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

    protected function auditCount(string $model, string $action): int
    {
        return AuditLog::where('auditable_type', $model)->where('action', $action)->count();
    }
}
