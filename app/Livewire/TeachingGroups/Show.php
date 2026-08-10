<?php

namespace App\Livewire\TeachingGroups;

use App\Models\ClassSubject;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingGroup;
use App\Models\TeachingGroupStudent;
use App\Services\TeachingAssignmentService;
use App\Services\TeachingGroupMembershipService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public TeachingGroup $teachingGroup;

    public bool $showAddStudent = false;

    public string $student_id = '';

    public string $started_on = '';

    public string $member_notes = '';

    public ?int $endingMembershipId = null;

    public string $ended_on = '';

    public bool $showAssignSubject = false;

    public string $subject_id = '';

    public string $assignment_staff_id = '';

    public string $assignment_started_on = '';

    public ?int $endingAssignmentId = null;

    public string $assignment_ended_on = '';

    public function mount(TeachingGroup $teachingGroup): void
    {
        $this->authorize('view', $teachingGroup);
        $this->teachingGroup = $teachingGroup;
        $this->started_on = $this->defaultDate()->toDateString();
        $this->assignment_started_on = $this->started_on;
    }

    /**
     * Today if it falls inside the group's academic year, otherwise the year's
     * start date -- so the form opens on a date the rules will accept.
     */
    private function defaultDate(): Carbon
    {
        $today = Carbon::today();
        $year = $this->teachingGroup->academicYear;

        if (! $year) {
            return $today;
        }

        return $today->between($year->start_date, $year->end_date) ? $today : $year->start_date->copy();
    }

    private function parsedStartDate(): Carbon
    {
        try {
            return Carbon::parse($this->started_on);
        } catch (\Throwable) {
            return $this->defaultDate();
        }
    }

    public function addStudent(TeachingGroupMembershipService $memberships): void
    {
        $this->authorize('update', $this->teachingGroup);

        $validated = $this->validate([
            'student_id' => ['required', 'exists:students,id'],
            'started_on' => ['required', 'date'],
            'member_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Every rule lives in the service: the form filters the picker, but
        // filtering is a convenience, never the check.
        $memberships->add(
            $this->teachingGroup,
            Student::findOrFail($validated['student_id']),
            Carbon::parse($validated['started_on']),
            $validated['member_notes'] !== '' ? $validated['member_notes'] : null,
        );

        $this->reset(['student_id', 'member_notes', 'showAddStudent']);
        $this->started_on = $this->defaultDate()->toDateString();
        $this->teachingGroup->refresh();
    }

    public function startEnding(int $membershipId): void
    {
        $this->authorize('update', $this->teachingGroup);
        $this->endingMembershipId = $membershipId;
        $this->ended_on = $this->defaultDate()->toDateString();
        $this->resetErrorBag();
    }

    public function cancelEnding(): void
    {
        $this->reset(['endingMembershipId', 'ended_on']);
    }

    public function endMembership(TeachingGroupMembershipService $memberships): void
    {
        $this->authorize('update', $this->teachingGroup);

        $validated = $this->validate(['ended_on' => ['required', 'date']]);

        $membership = $this->teachingGroup->memberships()->findOrFail($this->endingMembershipId);

        $memberships->end($membership, Carbon::parse($validated['ended_on']));

        $this->reset(['endingMembershipId', 'ended_on']);
        $this->teachingGroup->refresh();
    }

    // ------------------------------------------------- teaching assignments

    /**
     * Assign a subject and teacher, or hand the subject over to a different
     * teacher. Which of the two it is is the service's decision, not the UI's.
     */
    public function assignSubject(TeachingAssignmentService $assignments): void
    {
        $this->authorize('update', $this->teachingGroup);

        $validated = $this->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'assignment_staff_id' => ['required', 'exists:staff,id'],
            'assignment_started_on' => ['required', 'date'],
        ]);

        $assignments->assignToGroup(
            $this->teachingGroup,
            Subject::findOrFail($validated['subject_id']),
            Staff::findOrFail($validated['assignment_staff_id']),
            Carbon::parse($validated['assignment_started_on']),
        );

        $this->reset(['subject_id', 'assignment_staff_id', 'showAssignSubject']);
        $this->assignment_started_on = $this->defaultDate()->toDateString();
        $this->teachingGroup->refresh();
    }

    public function startEndingAssignment(int $assignmentId): void
    {
        $this->authorize('update', $this->teachingGroup);
        $this->endingAssignmentId = $assignmentId;
        $this->assignment_ended_on = $this->defaultDate()->toDateString();
        $this->resetErrorBag();
    }

    public function cancelEndingAssignment(): void
    {
        $this->reset(['endingAssignmentId', 'assignment_ended_on']);
    }

    public function endAssignment(TeachingAssignmentService $assignments): void
    {
        $this->authorize('update', $this->teachingGroup);

        $validated = $this->validate(['assignment_ended_on' => ['required', 'date']]);

        $assignment = $this->teachingGroup->teachingAssignments()->findOrFail($this->endingAssignmentId);

        $assignments->endAssignment($assignment, Carbon::parse($validated['assignment_ended_on']));

        $this->reset(['endingAssignmentId', 'assignment_ended_on']);
        $this->teachingGroup->refresh();
    }

    public function render(TeachingGroupMembershipService $memberships)
    {
        return view('livewire.teaching-groups.show', [
            'activeMembers' => $this->teachingGroup->memberships()->open()
                ->with('student')->get()
                ->sortBy(fn ($m) => $m->student->fullName())->values(),
            'pastMembers' => $this->teachingGroup->memberships()->closed()
                ->with('student')->orderByDesc('ended_on')->get(),
            // Filtered against the date actually in the form, so a backdated
            // start narrows the list the same way the service would reject it.
            'eligibleStudents' => $this->showAddStudent
                ? $memberships->eligibleStudents($this->teachingGroup, $this->parsedStartDate())
                : collect(),
            'activeAssignments' => $this->teachingGroup->teachingAssignments()
                ->active()->with('subject', 'teacher')->get()
                ->sortBy(fn (ClassSubject $a) => $a->subject->name)->values(),
            'pastAssignments' => $this->teachingGroup->teachingAssignments()
                ->closed()->with('subject', 'teacher')->orderByDesc('ended_on')->get(),
            'availableSubjects' => Subject::orderBy('name')->get(),
            'availableStaff' => Staff::where('status', 'active')->orderBy('first_name')->get(),
        ]);
    }
}
