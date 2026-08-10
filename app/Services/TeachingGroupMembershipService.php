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

            $this->assertNoOpenMembership($group, $student);
            $this->assertNoCompetingEnglishGroup($group, $student);

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
    public function eligibleStudents(TeachingGroup $group): \Illuminate\Support\Collection
    {
        // Students already committed to a group in this programme, resolved in
        // one query rather than per-student. Covers "already in this group" too.
        $spokenFor = $this->studentsWithOpenMembershipInProgramme($group) ?? $group->activeMemberships()->pluck('student_id');

        return Student::whereNotIn('id', $spokenFor->all())
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get()
            ->filter(fn (Student $student) => $this->ineligibilityReason($group, $student) === null)
            ->values();
    }

    /**
     * Null for a generic group, which has no programme and so no exclusivity.
     */
    private function studentsWithOpenMembershipInProgramme(TeachingGroup $group): ?\Illuminate\Support\Collection
    {
        $programme = $group->englishProgramme();

        if (! $programme) {
            return null;
        }

        return TeachingGroupStudent::query()
            ->whereNull('ended_on')
            ->whereHas('teachingGroup.englishLevel', fn ($q) => $q->where('english_programme_id', $programme->id))
            ->pluck('student_id');
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

    private function assertNoOpenMembership(TeachingGroup $group, Student $student): void
    {
        $exists = TeachingGroupStudent::where('teaching_group_id', $group->id)
            ->where('student_id', $student->id)
            ->whereNull('ended_on')
            ->exists();

        if ($exists) {
            $this->fail('student_id', "{$student->fullName()} is already an active member of this group.");
        }
    }

    /**
     * A student attends one English proficiency group per programme at a time.
     * Generic groups are unconstrained and may overlap freely with anything.
     */
    private function assertNoCompetingEnglishGroup(TeachingGroup $group, Student $student): void
    {
        $programme = $group->englishProgramme();

        if (! $programme) {
            return;
        }

        $competing = TeachingGroupStudent::query()
            ->where('student_id', $student->id)
            ->whereNull('ended_on')
            ->whereHas('teachingGroup.englishLevel', fn ($q) => $q->where('english_programme_id', $programme->id))
            ->with('teachingGroup')
            ->first();

        if ($competing) {
            $this->fail(
                'student_id',
                "{$student->fullName()} is already in {$competing->teachingGroup->name} for the {$programme->name}. End that membership first."
            );
        }
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
