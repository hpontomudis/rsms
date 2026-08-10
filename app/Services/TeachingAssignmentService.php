<?php

namespace App\Services;

use App\Models\ClassSubject;
use App\Models\Staff;
use App\Models\Subject;
use App\Models\TeachingGroup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creating and handing over teaching assignments backed by a TEACHING GROUP.
 *
 * Class-backed assignments keep their existing path in Classes\Show, untouched
 * by this step. The rules here mirror Step 0's close-and-create exactly; what
 * is new is the group's academic year acting as the date boundary, and the
 * requirement that the group still be active.
 */
class TeachingAssignmentService
{
    /**
     * Assign a subject and teacher to a group, or hand the subject over to a
     * different teacher. Re-selecting the teacher already in place is a no-op,
     * not a handover -- an accidental double submit must not manufacture a
     * fake succession in the history.
     */
    public function assignToGroup(
        TeachingGroup $group,
        Subject $subject,
        Staff $teacher,
        Carbon $startedOn,
    ): ClassSubject {
        if (! $group->isActive()) {
            $this->fail('teaching_group_id', "{$group->name} is archived and cannot take new teaching assignments.");
        }

        $this->assertWithinAcademicYear($group, $startedOn, 'started_on');

        return DB::transaction(function () use ($group, $subject, $teacher, $startedOn) {
            $current = ClassSubject::where('teaching_group_id', $group->id)
                ->where('subject_id', $subject->id)
                ->active()
                ->lockForUpdate()
                ->first();

            if ($current && $current->staff_id === $teacher->id) {
                return $current;
            }

            // Checked ignoring the row we are about to close, which will end
            // the day before this one starts. Anything else still covering the
            // date is a genuine clash.
            $this->assertNoOverlap($group, $subject, $startedOn, $current?->id);

            if ($current) {
                if ($startedOn->lte($current->started_on)) {
                    $this->fail('started_on', "The new assignment must start after the current one began ({$current->started_on->toDateString()}).");
                }

                // Close-and-create, never mutate: assessments and later
                // planning records pointing at $current must keep resolving to
                // the teacher who actually held it.
                $current->update(['ended_on' => $startedOn->copy()->subDay()]);
            }

            return ClassSubject::create([
                'teaching_group_id' => $group->id,
                'class_id' => null,
                'subject_id' => $subject->id,
                'staff_id' => $teacher->id,
                'started_on' => $startedOn,
            ]);
        });
    }

    /**
     * End an active assignment. History is closed, never deleted -- the FK's
     * RESTRICT would block a hard delete once assessments exist anyway.
     */
    public function endAssignment(ClassSubject $assignment, Carbon $endedOn): ClassSubject
    {
        if (! $assignment->isActive()) {
            $this->fail('ended_on', 'That assignment has already ended.');
        }

        if ($endedOn->lt($assignment->started_on)) {
            $this->fail('ended_on', 'The end date cannot be before the start date.');
        }

        if ($assignment->isTeachingGroupBacked()) {
            $this->assertWithinAcademicYear($assignment->teachingGroup, $endedOn, 'ended_on');
        }

        $assignment->update(['ended_on' => $endedOn]);

        return $assignment;
    }

    /**
     * Assignment periods for one group+subject may not overlap. The partial
     * unique index only stops two OPEN rows; overlapping closed history would
     * slip past it, leaving two teachers apparently in force at once.
     */
    public function overlappingAssignment(
        TeachingGroup $group,
        Subject $subject,
        Carbon $startedOn,
        ?Carbon $endedOn = null,
        ?int $ignoreId = null,
    ): ?ClassSubject {
        $query = ClassSubject::where('teaching_group_id', $group->id)
            ->where('subject_id', $subject->id)
            ->where(fn ($q) => $q->whereNull('ended_on')->orWhere('ended_on', '>=', $startedOn));

        if ($endedOn) {
            $query->where('started_on', '<=', $endedOn);
        }

        if ($ignoreId) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->with('teacher')->first();
    }

    private function assertNoOverlap(TeachingGroup $group, Subject $subject, Carbon $startedOn, ?int $ignoreId): void
    {
        $clash = $this->overlappingAssignment($group, $subject, $startedOn, null, $ignoreId);

        if (! $clash) {
            return;
        }

        $until = $clash->ended_on?->toDateString() ?? 'now';

        $this->fail('started_on', "{$clash->teacher->fullName()} held this subject for {$group->name} from {$clash->started_on->toDateString()} to {$until}. Assignment periods cannot overlap.");
    }

    /**
     * A group belongs to exactly one academic year, so that -- not the year
     * flagged current -- is the boundary. academic_years.start_date/end_date
     * are both NOT NULL, so this is a real boundary rather than an invented one.
     */
    private function assertWithinAcademicYear(TeachingGroup $group, Carbon $date, string $field): void
    {
        $year = $group->academicYear;

        if (! $year) {
            $this->fail($field, "{$group->name} has no academic year, so assignment dates cannot be validated.");
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
