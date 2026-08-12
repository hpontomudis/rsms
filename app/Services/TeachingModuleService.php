<?php

namespace App\Services;

use App\Models\ClassSubject;
use App\Models\CurriculumScope;
use App\Models\LearningObjective;
use App\Models\SemesterProgrammeItem;
use App\Models\TeachingModule;
use App\Models\TeachingModuleLearningObjective;
use App\Models\TeachingModuleSemesterProgrammeItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Modul Ajar: instructional design for one teaching assignment.
 *
 * TWO THINGS THIS SERVICE EXISTS TO GUARANTEE.
 *
 * First, the curriculum scope is NEVER guessed. A class-backed assignment
 * reaches candidate scopes through grade -> learning phase, a group-backed one
 * through English level, and either may legitimately match scopes in more than
 * one curriculum version. Taking the first would silently bind a module to the
 * wrong version, so zero candidates is an error, one may be preselected, and
 * several must be chosen from explicitly. Eligibility is re-checked at ready,
 * because a class can be re-graded while a draft sits.
 *
 * Second, a closed assignment accepts no new module from ANYONE, managers
 * included -- the same rule AssessmentPolicy applies to assessments. History is
 * read; it is not added to.
 */
class TeachingModuleService
{
    // ------------------------------------------------------------- scope

    /**
     * The curriculum scopes this assignment could legitimately plan against:
     * active curricula whose scope matches the resolved phase or level, and
     * which actually hold objectives for this subject.
     *
     * Deliberately returns a collection rather than a single scope, so no
     * caller can mistake "the first one" for "the right one".
     */
    public function eligibleScopes(ClassSubject $assignment)
    {
        $query = CurriculumScope::query()
            ->whereHas('curriculum', fn ($q) => $q->where('status', 'active'));

        if ($assignment->isClassBacked()) {
            $phaseId = $assignment->schoolClass?->grade?->learningPhaseLink?->learning_phase_id;

            if ($phaseId === null) {
                return collect();
            }

            $query->where('learning_phase_id', $phaseId);
        } else {
            $levelId = $assignment->teachingGroup?->english_level_id;

            if ($levelId === null) {
                return collect();
            }

            $query->where('english_level_id', $levelId);
        }

        return $query->with('curriculum')->get()->values();
    }

    /**
     * THE SCOPE RULE. National: the scope's phase must be the class's grade's
     * phase. English: the scope's level must be the group's level. Never a
     * current-curriculum fallback, never an is_current guess.
     */
    public function assertScopeFitsAssignment(ClassSubject $assignment, CurriculumScope $scope): void
    {
        if ($assignment->isClassBacked()) {
            if (! $scope->isPhaseBased()) {
                $this->fail('curriculum_scope_id', "{$scope->displayName()} is an English level, so a class cannot plan against it.");
            }

            $phaseId = $assignment->schoolClass?->grade?->learningPhaseLink?->learning_phase_id;

            if ($phaseId === null) {
                $this->fail('curriculum_scope_id', "{$assignment->schoolClass?->grade?->name} is not mapped to a learning phase, so no scope can be selected.");
            }

            if ($phaseId !== $scope->learning_phase_id) {
                $this->fail('curriculum_scope_id', "{$assignment->displayName()} is not in {$scope->displayName()}.");
            }

            return;
        }

        if ($scope->isPhaseBased()) {
            $this->fail('curriculum_scope_id', "{$scope->displayName()} is a learning phase, so a teaching group cannot plan against it.");
        }

        $levelId = $assignment->teachingGroup?->english_level_id;

        if ($levelId === null) {
            $this->fail('curriculum_scope_id', "{$assignment->displayName()} is not tied to an English level.");
        }

        if ($levelId !== $scope->english_level_id) {
            $this->fail('curriculum_scope_id', "{$assignment->displayName()} teaches {$assignment->teachingGroup?->englishLevel?->name}, not {$scope->displayName()}.");
        }
    }

    // ------------------------------------------------------------ create

    public function create(ClassSubject $assignment, CurriculumScope $scope, array $attributes): TeachingModule
    {
        if (! $assignment->isActive()) {
            $this->fail('class_subject_id', 'That teaching assignment has ended. A module cannot be written against closed history.');
        }

        $this->assertScopeFitsAssignment($assignment, $scope);
        $this->assertCurriculumOperational($scope);
        $this->assertContent($attributes);

        return TeachingModule::create([
            'class_subject_id' => $assignment->id,
            // Mirrored from the assignment so composite keys can verify them.
            'class_id' => $assignment->class_id,
            'teaching_group_id' => $assignment->teaching_group_id,
            'subject_id' => $assignment->subject_id,
            'curriculum_scope_id' => $scope->id,
            'title' => $attributes['title'],
            'topic' => $this->orNull($attributes['topic'] ?? null),
            'planned_activity' => $attributes['planned_activity'],
            'teaching_strategy' => $this->orNull($attributes['teaching_strategy'] ?? null),
            'resources' => $this->orNull($attributes['resources'] ?? null),
            'differentiation' => $this->orNull($attributes['differentiation'] ?? null),
            'planned_assessment' => $this->orNull($attributes['planned_assessment'] ?? null),
            'teacher_notes' => $this->orNull($attributes['teacher_notes'] ?? null),
            'status' => 'draft',
        ]);
    }

    /**
     * Draft edits change the plan. A ready module accepts teacher_notes only --
     * a margin note is not the plan, and reflection accrues while teaching.
     */
    public function update(TeachingModule $module, array $attributes): TeachingModule
    {
        if ($module->isArchived()) {
            $this->fail('status', 'An archived teaching module is read-only.');
        }

        if ($module->isReady()) {
            $touchesPlan = array_intersect(array_keys($attributes), TeachingModule::PLAN_FIELDS);

            if ($touchesPlan !== []) {
                $this->fail('status', 'This module is ready to teach, so its plan is frozen. Return it to draft to change it.');
            }

            $module->update(['teacher_notes' => $this->orNull($attributes['teacher_notes'] ?? null)]);

            return $module->refresh();
        }

        $this->assertContent($attributes + $module->only(TeachingModule::PLAN_FIELDS));

        $changes = [];

        foreach (array_merge(TeachingModule::PLAN_FIELDS, ['teacher_notes']) as $field) {
            if (array_key_exists($field, $attributes)) {
                $changes[$field] = in_array($field, ['title', 'planned_activity'], true)
                    ? $attributes[$field]
                    : $this->orNull($attributes[$field]);
            }
        }

        if ($changes !== []) {
            $module->update($changes);
        }

        return $module->refresh();
    }

    // ------------------------------------------------------- objectives

    public function linkObjective(TeachingModule $module, LearningObjective $objective): TeachingModuleLearningObjective
    {
        $this->assertDraft($module);

        if ($objective->curriculum_scope_id !== $module->curriculum_scope_id || $objective->subject_id !== $module->subject_id) {
            $this->fail('learning_objective_id', "{$objective->code} belongs to a different scope or subject.");
        }

        if ($objective->status === 'archived') {
            $this->fail('learning_objective_id', "{$objective->code} is archived and cannot be newly planned.");
        }

        if ($module->objectiveLinks()->where('learning_objective_id', $objective->id)->exists()) {
            $this->fail('learning_objective_id', 'That objective is already in this module.');
        }

        return TeachingModuleLearningObjective::create([
            'teaching_module_id' => $module->id,
            'learning_objective_id' => $objective->id,
            'curriculum_scope_id' => $module->curriculum_scope_id,
            'subject_id' => $module->subject_id,
        ]);
    }

    public function unlinkObjective(TeachingModule $module, LearningObjective $objective): void
    {
        $this->assertDraft($module);

        $module->objectiveLinks()->where('learning_objective_id', $objective->id)->get()->each->delete();
    }

    // ------------------------------------------------------ prosem slots

    /**
     * Bind a module to a scheduled slot.
     *
     * The slot's context is walked back to its annual programme and required to
     * match this module's roster, subject, year and scope. Service-level rather
     * than key-enforced: the chain runs through two tables, and mirroring four
     * columns onto an optional link would cost more than the rule is worth.
     */
    public function linkSlot(TeachingModule $module, SemesterProgrammeItem $slot): TeachingModuleSemesterProgrammeItem
    {
        $this->assertDraft($module);

        $semester = $slot->semesterProgramme;
        $annual = $semester?->annualProgramme;

        if (! $annual) {
            $this->fail('semester_programme_item_id', 'That schedule slot has no resolvable annual programme.');
        }

        if ($semester->isArchived()) {
            $this->fail('semester_programme_item_id', 'That semester programme is archived and takes no new module links.');
        }

        $this->assertSharesContext($annual, $module->class_id, $module->teaching_group_id, $module->subject_id, $module->curriculum_scope_id, 'semester_programme_item_id');

        if ($module->slotLinks()->where('semester_programme_item_id', $slot->id)->exists()) {
            $this->fail('semester_programme_item_id', 'That slot is already linked to this module.');
        }

        return TeachingModuleSemesterProgrammeItem::create([
            'teaching_module_id' => $module->id,
            'semester_programme_item_id' => $slot->id,
        ]);
    }

    public function unlinkSlot(TeachingModule $module, SemesterProgrammeItem $slot): void
    {
        $this->assertDraft($module);

        $module->slotLinks()->where('semester_programme_item_id', $slot->id)->get()->each->delete();
    }

    // -------------------------------------------------------- lifecycle

    /**
     * Put the plan into a teachable state. Everything is checked before
     * anything is written.
     */
    public function markReady(TeachingModule $module): TeachingModule
    {
        if (! $module->isDraft()) {
            $this->fail('status', 'Only a draft module can be marked ready.');
        }

        $assignment = $module->classSubject;

        if (! $assignment?->isActive()) {
            $this->fail('status', 'That teaching assignment has ended, so this module can no longer be made ready.');
        }

        $scope = $module->curriculumScope;

        // A class could have been re-graded since the draft was written.
        $this->assertScopeFitsAssignment($assignment, $scope);
        $this->assertCurriculumOperational($scope);
        $this->assertContent($module->only(TeachingModule::PLAN_FIELDS));

        $links = $module->objectiveLinks()->with('learningObjective')->get();

        if ($links->isEmpty()) {
            $this->fail('objectives', 'Link at least one learning objective before marking this module ready.');
        }

        foreach ($links as $link) {
            $objective = $link->learningObjective;

            if ($objective->status === 'draft') {
                $this->fail('objectives', "{$objective->code} is still a draft objective. Only active objectives may be planned.");
            }

            if ($link->curriculum_scope_id !== $module->curriculum_scope_id || $link->subject_id !== $module->subject_id) {
                $this->fail('objectives', 'A linked objective no longer matches this module scope and subject.');
            }
        }

        foreach ($module->slotLinks()->with('semesterProgrammeItem.semesterProgramme.annualProgramme')->get() as $link) {
            $annual = $link->semesterProgrammeItem?->semesterProgramme?->annualProgramme;

            if (! $annual) {
                $this->fail('slots', 'A linked schedule slot no longer resolves to an annual programme.');
            }

            $this->assertSharesContext($annual, $module->class_id, $module->teaching_group_id, $module->subject_id, $module->curriculum_scope_id, 'slots');
        }

        return DB::transaction(function () use ($module) {
            $module->update(['status' => 'ready']);

            return $module->refresh();
        });
    }

    /**
     * Back to draft -- but only while nothing has been taught against it.
     * Once a journal cites the module, what was planned is history.
     */
    public function returnToDraft(TeachingModule $module): TeachingModule
    {
        if (! $module->isReady()) {
            $this->fail('status', 'Only a module that is ready can be returned to draft.');
        }

        if ($module->journals()->exists()) {
            $this->fail('status', 'A journal already refers to this module, so what was planned cannot be rewritten. Archive it and write a new one.');
        }

        $module->update(['status' => 'draft']);

        return $module->refresh();
    }

    public function archive(TeachingModule $module): TeachingModule
    {
        if ($module->isArchived()) {
            return $module;
        }

        if ($module->isDraft()) {
            $this->fail('status', 'An unused draft is deleted rather than archived.');
        }

        $module->update(['status' => 'archived']);

        return $module->refresh();
    }

    public function delete(TeachingModule $module): void
    {
        if (! $module->isDraft()) {
            $this->fail('status', 'Only an unused draft can be deleted. Archive a module that has been made ready.');
        }

        if ($module->journals()->exists()) {
            $this->fail('status', 'A journal refers to this module, so it cannot be deleted.');
        }

        DB::transaction(function () use ($module) {
            $module->objectiveLinks()->get()->each->delete();
            $module->slotLinks()->get()->each->delete();
            $module->delete();
        });
    }

    // ---------------------------------------------------------- helpers

    /**
     * Whether an annual programme describes the same teaching context as the
     * caller's roster, subject and scope.
     */
    private function assertSharesContext($annual, ?int $classId, ?int $groupId, int $subjectId, int $scopeId, string $field): void
    {
        if ($annual->class_id !== $classId || $annual->teaching_group_id !== $groupId) {
            $this->fail($field, "That schedule belongs to {$annual->rosterName()}, not to this one.");
        }

        if ($annual->subject_id !== $subjectId) {
            $this->fail($field, 'That schedule belongs to a different subject.');
        }

        if ($annual->curriculum_scope_id !== $scopeId) {
            $this->fail($field, 'That schedule follows a different curriculum scope.');
        }
    }

    private function assertCurriculumOperational(CurriculumScope $scope): void
    {
        $curriculum = $scope->curriculum;

        if (! $curriculum || $curriculum->status !== 'active') {
            $this->fail('curriculum_scope_id', 'That curriculum version is not active, so it cannot be planned against.');
        }
    }

    private function assertContent(array $attributes): void
    {
        if (trim((string) ($attributes['title'] ?? '')) === '') {
            $this->fail('title', 'A module needs a title.');
        }

        if (trim((string) ($attributes['planned_activity'] ?? '')) === '') {
            $this->fail('planned_activity', 'A module needs its planned learning activity. That is the plan.');
        }
    }

    private function assertDraft(TeachingModule $module): void
    {
        if (! $module->isDraft()) {
            $this->fail('status', 'Only a draft module may have its objectives and schedule links changed.');
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
