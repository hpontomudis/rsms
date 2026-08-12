<?php

namespace App\Policies;

use App\Models\ClassSubject;
use App\Models\CurriculumScope;
use App\Models\LearningPathway;
use App\Models\User;

/**
 * Pathways are the first curriculum artefact teachers may author, because they
 * are planning rather than published standards. That authorship is deliberately
 * narrow:
 *
 *   - `academics.view`   read
 *   - `academics.plan`   draft and edit -- teachers included, but only for a
 *                        scope + subject they actually teach
 *   - `academics.manage` everything, plus activation and archiving
 *
 * Activation stays with management: putting a school-wide sequence into force
 * is the approval step, which is why no separate approval workflow exists.
 *
 * There is NO creator ownership. A Phase C pathway is collaborative across the
 * grades that make up Phase C -- a Year 5 and a Year 6 teacher work on the same
 * record by design.
 */
class LearningPathwayPolicy
{
    public function view(User $user, LearningPathway $pathway): bool
    {
        return $user->can('academics.view');
    }

    /**
     * Creating needs the scope and subject in hand to check a teacher's
     * assignments, so the caller passes them.
     */
    public function createFor(User $user, CurriculumScope $scope, int $subjectId): bool
    {
        if ($user->can('academics.manage')) {
            return true;
        }

        return $user->can('academics.plan') && $this->teaches($user, $scope, $subjectId);
    }

    /**
     * Editing a draft: managers always, teachers only where they teach it.
     * Active and archived pathways are frozen for everyone -- the model
     * enforces that independently, because it is versioning, not permission.
     */
    public function update(User $user, LearningPathway $pathway): bool
    {
        if (! $pathway->isDraft()) {
            return false;
        }

        if ($user->can('academics.manage')) {
            return true;
        }

        return $user->can('academics.plan')
            && $this->teaches($user, $pathway->curriculumScope, $pathway->subject_id);
    }

    public function delete(User $user, LearningPathway $pathway): bool
    {
        return $this->update($user, $pathway);
    }

    /**
     * Activation and archiving are management decisions. A teacher may build
     * the sequence; putting it into force, or retiring one, is not theirs.
     */
    public function transition(User $user, LearningPathway $pathway): bool
    {
        return $user->can('academics.manage') && ! $pathway->isArchived();
    }

    /**
     * Does this user hold an ACTIVE teaching assignment for the same subject
     * whose roster resolves to this pathway's scope?
     *
     *   national  : assignment -> class -> grade -> learning phase
     *   English   : assignment -> teaching group -> english level
     *
     * A Year 5 and a Year 6 Mathematics teacher both resolve to Phase C, which
     * is exactly the cross-grade collaboration this is meant to allow. A closed
     * assignment authorises nothing -- planning is for teaching you currently
     * hold, and Step 0's read/write split says the same thing about assessments.
     */
    private function teaches(User $user, ?CurriculumScope $scope, int $subjectId): bool
    {
        $staffId = $user->staff?->id;

        if (! $staffId || ! $scope) {
            return false;
        }

        $assignments = ClassSubject::where('staff_id', $staffId)
            ->where('subject_id', $subjectId)
            ->active()
            ->with(['schoolClass.grade.learningPhaseLink', 'teachingGroup'])
            ->get();

        foreach ($assignments as $assignment) {
            if ($scope->isPhaseBased()) {
                if (! $assignment->isClassBacked()) {
                    continue;
                }

                $phaseId = $assignment->schoolClass?->grade?->learningPhaseLink?->learning_phase_id;

                if ($phaseId !== null && $phaseId === $scope->learning_phase_id) {
                    return true;
                }

                continue;
            }

            if (! $assignment->isTeachingGroupBacked()) {
                continue;
            }

            $levelId = $assignment->teachingGroup?->english_level_id;

            if ($levelId !== null && $levelId === $scope->english_level_id) {
                return true;
            }
        }

        return false;
    }
}
