<?php

namespace App\Communications;

use App\Models\CommunicationAudienceRule;
use App\Models\Guardian;
use App\Models\Staff;
use App\Models\Student;
use App\Models\TeachingGroupStudent;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * One resolver method per rule_type, explicit and closed -- the same shape
 * EvidenceService uses for its 8 evidence keys, rather than one generic
 * query built from stored filter data. Every method returns
 * Collection<AudienceCandidate>; deduplication across an entire
 * Communication's rules happens once, in CommunicationService, after every
 * rule has been resolved.
 *
 * Only ACTIVE domain rows are ever candidates: Staff with status=active,
 * Students with status=active. A withdrawn student or a terminated staff
 * member is never targeted by a broad rule, even though their historical
 * recipient rows (if any, from before they left) are never touched.
 */
class AudienceResolver
{
    /** @return Collection<int, AudienceCandidate> */
    public function resolve(CommunicationAudienceRule $rule): Collection
    {
        return match ($rule->rule_type) {
            'everyone' => $this->everyone(),
            'all_staff' => $this->allStaff(),
            'staff_category' => $this->staffCategory($rule),
            'role' => $this->role($rule),
            'school_class_students' => $this->schoolClassStudents($rule),
            'school_class_guardians' => $this->schoolClassGuardians($rule),
            'teaching_group_students' => $this->teachingGroupStudents($rule),
            'teaching_group_guardians' => $this->teachingGroupGuardians($rule),
            'selected_staff' => $this->selectedStaff($rule),
            'selected_guardians' => $this->selectedGuardians($rule),
            'selected_students' => $this->selectedStudents($rule),
            'selected_users' => $this->selectedUsers($rule),
        };
    }

    /**
     * The broadest option: every active Staff member, every active Student,
     * and every Guardian of an active Student. Not every User account --
     * "everyone" means the school community, not the login table.
     */
    private function everyone(): Collection
    {
        $studentIds = $this->activeStudents()->pluck('id');

        return $this->allStaff()
            ->concat($studentIds->map(fn ($id) => new AudienceCandidate('student', $id)))
            ->concat($this->guardiansOfStudentIds($studentIds));
    }

    private function allStaff(): Collection
    {
        return Staff::where('status', 'active')->pluck('id')
            ->map(fn ($id) => new AudienceCandidate('staff', $id));
    }

    private function staffCategory(CommunicationAudienceRule $rule): Collection
    {
        return Staff::where('status', 'active')
            ->where('staff_category_id', $rule->staff_category_id)
            ->pluck('id')
            ->map(fn ($id) => new AudienceCandidate('staff', $id));
    }

    /**
     * Resolved to Users holding the role at THIS moment (roles change; the
     * result is only ever used immediately, either for a live preview or
     * inside the publish transaction -- never stored as "the role's members").
     * Each User is mapped down to whichever domain entity they unambiguously
     * are, so "all principals" targets the Staff member, not a bare login --
     * see resolveEntityForUser().
     */
    private function role(CommunicationAudienceRule $rule): Collection
    {
        return User::role($rule->role_name)->get()
            ->map(fn (User $user) => $this->resolveEntityForUser($user));
    }

    private function schoolClassStudents(CommunicationAudienceRule $rule): Collection
    {
        $class = $rule->schoolClass;

        if (! $class) {
            return collect();
        }

        return $class->students()->wherePivot('status', 'active')->pluck('students.id')
            ->map(fn ($id) => new AudienceCandidate('student', $id));
    }

    private function schoolClassGuardians(CommunicationAudienceRule $rule): Collection
    {
        $class = $rule->schoolClass;

        if (! $class) {
            return collect();
        }

        $studentIds = $class->students()->wherePivot('status', 'active')->pluck('students.id');

        return $this->guardiansOfStudentIds($studentIds);
    }

    private function teachingGroupStudents(CommunicationAudienceRule $rule): Collection
    {
        $studentIds = $this->currentGroupMemberIds($rule);

        return $studentIds->map(fn ($id) => new AudienceCandidate('student', $id));
    }

    private function teachingGroupGuardians(CommunicationAudienceRule $rule): Collection
    {
        return $this->guardiansOfStudentIds($this->currentGroupMemberIds($rule));
    }

    /** Current membership only -- teaching_group_student is effective-dated for exactly this. */
    private function currentGroupMemberIds(CommunicationAudienceRule $rule): Collection
    {
        if (! $rule->teaching_group_id) {
            return collect();
        }

        return TeachingGroupStudent::where('teaching_group_id', $rule->teaching_group_id)
            ->whereNull('ended_on')
            ->pluck('student_id');
    }

    private function selectedStaff(CommunicationAudienceRule $rule): Collection
    {
        return $rule->selectedStaff()->pluck('staff_id')
            ->map(fn ($id) => new AudienceCandidate('staff', $id));
    }

    private function selectedGuardians(CommunicationAudienceRule $rule): Collection
    {
        return $rule->selectedGuardians()->pluck('guardian_id')
            ->map(fn ($id) => new AudienceCandidate('guardian', $id));
    }

    private function selectedStudents(CommunicationAudienceRule $rule): Collection
    {
        return $rule->selectedStudents()->pluck('student_id')
            ->map(fn ($id) => new AudienceCandidate('student', $id));
    }

    private function selectedUsers(CommunicationAudienceRule $rule): Collection
    {
        return $rule->selectedUsers()->pluck('user_id')
            ->map(fn ($id) => new AudienceCandidate('user', $id));
    }

    /**
     * Every distinct Guardian of any of the given (already-active) Students.
     * A Guardian with two matched children still appears once -- the dedup
     * this codebase's Guardian-targeting review demanded happens for free
     * here because the result is keyed by Guardian id, not by (guardian,
     * student) pair.
     */
    private function guardiansOfStudentIds(Collection $studentIds): Collection
    {
        if ($studentIds->isEmpty()) {
            return collect();
        }

        return Guardian::whereHas('students', fn ($q) => $q->whereIn('students.id', $studentIds))
            ->pluck('id')
            ->map(fn ($id) => new AudienceCandidate('guardian', $id));
    }

    private function activeStudents(): Collection
    {
        return Student::where('status', 'active')->get(['id']);
    }

    /**
     * Given a User, which domain entity are they -- tried in order Staff,
     * Guardian, Student, falling back to the User itself (a pure system/admin
     * account with none of the three). Uses the same never-guess discipline
     * as ResolvesUnambiguousUser, walked in the opposite direction: if this
     * login maps to more than one row of a given type, that type is skipped
     * rather than picking one arbitrarily.
     */
    private function resolveEntityForUser(User $user): AudienceCandidate
    {
        $staffIds = Staff::where('user_id', $user->id)->pluck('id');
        if ($staffIds->count() === 1) {
            return new AudienceCandidate('staff', $staffIds->first());
        }

        $guardianIds = Guardian::where('user_id', $user->id)->pluck('id');
        if ($guardianIds->count() === 1) {
            return new AudienceCandidate('guardian', $guardianIds->first());
        }

        $studentIds = Student::where('user_id', $user->id)->pluck('id');
        if ($studentIds->count() === 1) {
            return new AudienceCandidate('student', $studentIds->first());
        }

        return new AudienceCandidate('user', $user->id);
    }
}
