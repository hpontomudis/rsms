<?php

namespace App\Livewire\TeachingGroups;

use App\Models\Student;
use App\Models\TeachingGroup;
use App\Models\TeachingGroupStudent;
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

    public function mount(TeachingGroup $teachingGroup): void
    {
        $this->authorize('view', $teachingGroup);
        $this->teachingGroup = $teachingGroup;
        $this->started_on = $this->defaultDate()->toDateString();
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

    public function render(TeachingGroupMembershipService $memberships)
    {
        return view('livewire.teaching-groups.show', [
            'activeMembers' => $this->teachingGroup->memberships()->open()
                ->with('student')->get()
                ->sortBy(fn ($m) => $m->student->fullName())->values(),
            'pastMembers' => $this->teachingGroup->memberships()->closed()
                ->with('student')->orderByDesc('ended_on')->get(),
            'eligibleStudents' => $this->showAddStudent
                ? $memberships->eligibleStudents($this->teachingGroup)
                : collect(),
        ]);
    }
}
