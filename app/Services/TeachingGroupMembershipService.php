<?php

namespace App\Services;

use App\Models\Student;
use App\Models\TeachingGroup;
use App\Models\TeachingGroupStudent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The rules for putting a student into a teaching group, and taking them out
 * again. Kept out of the Livewire components so the UI and the tests exercise
 * the same code, and so a future import path cannot bypass them.
 */
class TeachingGroupMembershipService
{
    public function __construct(private StudentGradeResolver $grades) {}

    /**
     * Open a membership. Throws ValidationException with a message the UI can
     * show directly when any rule is broken.
     */
    public function add(TeachingGroup $group, Student $student, Carbon $startedOn, ?string $notes = null): TeachingGroupStudent
    {
        if (! $group->isActive()) {
            $this->fail('student_id', "{$group->name} is archived and cannot take new students.");
        }

        $this->assertWithinAcademicYear($group, $startedOn, 'started_on');
        $this->assertEligible($group, $student, $startedOn);

        // The one-open-English-group-per-programme rule cannot be expressed as
        // a unique index without denormalising the programme onto this table,
        // so it is checked here instead. Locking the student row first means
        // two administrators adding the same student at the same moment
        // serialise rather than both passing the check. (No-op on SQLite,
        // which serialises writers anyway.)
        return DB::transaction(function () use ($group, $student, $startedOn, $notes) {
            Student::whereKey($student->id)->lockForUpdate()->first();

            $this->assertNoOverlappingMembership($group, $student, $startedOn, null);

            return TeachingGroupStudent::create([
                'teaching_group_id' => $group->id,
                'student_id' => $student->id,
                'started_on' => $startedOn,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Close a membership. History is closed, never deleted.
     */
    public function end(TeachingGroupStudent $membership, Carbon $endedOn): TeachingGroupStudent
    {
        if (! $membership->isOpen()) {
            $this->fail('ended_on', 'That membership has already ended.');
        }

        if ($endedOn->lt($membership->started_on)) {
            $this->fail('ended_on', 'The end date cannot be before the start date.');
        }

        $this->assertWithinAcademicYear($membership->teachingGroup, $endedOn, 'ended_on');

        $membership->update(['ended_on' => $endedOn]);

        return $membership;
    }

    /**
     * Students who may join this group: eligible by grade, and not already in
     * it. Used to narrow the picker -- never as the only check, since the UI
     * is not a security or integrity boundary.
     */
    public function eligibleStudents(TeachingGroup $group, ?Carbon $from = null): \Illuminate\Support\Collection
    {
        $from ??= Carbon::today();

        // Students whose existing membership would overlap a new open-ended
        // one starting on $from, resolved in one query rather than per student.
        $spokenFor = $this->studentsWithMembershipCovering($group, $from);

        return Student::whereNotIn('id', $spokenFor->all())
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get()
            ->filter(fn (Student $student) => $this->ineligibilityReason($group, $student) === null)
            ->values();
    }

    /**
     * Students with a membership still in force on or after $from -- in this
     * group (always) or anywhere in its programme (English groups only).
     */
    private function studentsWithMembershipCovering(TeachingGroup $group, Carbon $from): \Illuminate\Support\Collection
    {
        $stillRunning = fn ($q) => $q->whereNull('ended_on')->orWhere('ended_on', '>=', $from);

        $programme = $group->englishProgramme();

        $query = TeachingGroupStudent::query()->where($stillRunning);

        if ($programme) {
            $query->where(function ($q) use ($group, $programme) {
                $q->where('teaching_group_id', $group->id)
                    ->orWhereHas('teachingGroup.englishLevel', fn ($l) => $l->where('english_programme_id', $programme->id));
            });
        } else {
            $query->where('teaching_group_id', $group->id);
        }

        return $query->pluck('student_id');
    }

    /**
     * Why this student may not join, or null if they may. Public so the UI can
     * explain a rejection rather than silently omitting a name.
     */
    public function ineligibilityReason(TeachingGroup $group, Student $student): ?string
    {
        // A generic group carries no programme, so nothing constrains who may
        // join it. Rules arrive when group types with rules do.
        if (! $group->isEnglishGroup()) {
            return null;
        }

        $programme = $group->englishProgramme();

        if (! $programme) {
            return 'This group references an English level with no programme.';
        }

        $grade = $this->grades->gradeForYear($student, $group->academic_year_id, $reason);

        if (! $grade) {
            return $reason === StudentGradeResolver::AMBIGUOUS
                ? "{$student->fullName()} is in classes from more than one grade for this academic year, so their grade cannot be determined."
                : "{$student->fullName()} has no active class in this group's academic year, so their grade cannot be determined.";
        }

        // Group membership resolves its year from the group, never from a
        // date, so the year-resolution reasons cannot arise here.

        $studentProgramme = $grade->englishProgramme();

        if (! $studentProgramme || $studentProgramme->id !== $programme->id) {
            return "{$grade->name} is not covered by the {$programme->name}.";
        }

        return null;
    }

    private function assertEligible(TeachingGroup $group, Student $student, Carbon $startedOn): void
    {
        if ($reason = $this->ineligibilityReason($group, $student)) {
            $this->fail('student_id', $reason);
        }
    }

    /**
     * Two rules, both expressed as effective-date overlap rather than "is
     * there an open row", so historical corrections cannot quietly produce a
     * student who was in two places at once:
     *
     *   a) within one group, a student's membership periods must not overlap
     *      -- true of generic groups as much as English ones;
     *   b) within one English programme, membership periods across different
     *      groups must not overlap.
     *
     * Rule (b) does not apply to generic groups, which may overlap anything.
     * Neither can be a database constraint: (a) would need an exclusion
     * constraint over a date range (PostgreSQL-only; SQLite runs the tests),
     * and (b) additionally spans a join through english_levels that an index
     * cannot see without copying english_programme_id onto every membership
     * row -- which is exactly the denormalisation this design rejects.
     */
    private function assertNoOverlappingMembership(TeachingGroup $group, Student $student, Carbon $startedOn, ?Carbon $endedOn, ?int $ignoreId = null): void
    {
        $sameGroup = $this->overlappingMembership(
            TeachingGroupStudent::where('teaching_group_id', $group->id),
            $student, $startedOn, $endedOn, $ignoreId
        );

        if ($sameGroup) {
            $this->fail('student_id', $sameGroup->isOpen()
                ? "{$student->fullName()} is already an active member of this group."
                : "{$student->fullName()} was already in this group from {$sameGroup->started_on->toDateString()} to {$sameGroup->ended_on->toDateString()}. Membership periods in one group cannot overlap.");
        }

        $programme = $group->englishProgramme();

        if (! $programme) {
            return;
        }

        $sameProgramme = $this->overlappingMembership(
            TeachingGroupStudent::whereHas('teachingGroup.englishLevel', fn ($q) => $q->where('english_programme_id', $programme->id)),
            $student, $startedOn, $endedOn, $ignoreId
        );

        if ($sameProgramme) {
            $name = $sameProgramme->teachingGroup->name;

            $this->fail('student_id', $sameProgramme->isOpen()
                ? "{$student->fullName()} is already in {$name} for the {$programme->name}. End that membership first."
                : "{$student->fullName()} was in {$name} for the {$programme->name} from {$sameProgramme->started_on->toDateString()} to {$sameProgramme->ended_on->toDateString()}. Memberships within one programme cannot overlap.");
        }
    }

    /**
     * The overlap predicate itself, against whatever set the caller scopes to.
     * Public so the rule can be tested directly, including closed-versus-closed
     * cases that no UI path can produce.
     */
    public function overlappingMembership(
        \Illuminate\Database\Eloquent\Builder $scope,
        Student $student,
        Carbon $startedOn,
        ?Carbon $endedOn = null,
        ?int $ignoreId = null,
    ): ?TeachingGroupStudent {
        $scope->where('student_id', $student->id)
            // existing.ended_on >= new.started_on (an open row never ends)
            ->where(fn ($q) => $q->whereNull('ended_on')->orWhere('ended_on', '>=', $startedOn));

        // existing.started_on <= new.ended_on; an open new row has no ceiling
        if ($endedOn) {
            $scope->where('started_on', '<=', $endedOn);
        }

        if ($ignoreId) {
            $scope->whereKeyNot($ignoreId);
        }

        return $scope->with('teachingGroup')->first();
    }

    /**
     * Membership dates must sit inside the group's academic year.
     * academic_years.start_date and end_date are both NOT NULL, so this is a
     * real boundary rather than an invented one.
     */
    private function assertWithinAcademicYear(TeachingGroup $group, Carbon $date, string $field): void
    {
        $year = $group->academicYear;

        if (! $year) {
            return;
        }

        if ($date->lt($year->start_date) || $date->gt($year->end_date)) {
            $this->fail($field, "The date must fall within {$year->name} ({$year->start_date->toDateString()} to {$year->end_date->toDateString()}).");
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
