<?php

namespace App\Livewire\Teaching;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\Staff;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * A teacher's own teaching assignments -- classes and teaching groups in one
 * list, because they are the same thing wearing different rosters.
 *
 * This closes the gap found in Step 2c: an assigned group teacher could reach
 * their assessments only by typing the URL, because the only link lived on the
 * teaching-group screen, which teachers cannot see.
 *
 * Deliberately assignment-centred, not student-centred. It grants no new
 * access to student profiles, guardians, finance or attendance, and it carries
 * no management controls -- reassigning a teacher stays on the management
 * screens where it is authorised.
 */
#[Layout('layouts.app')]
class MyAssignments extends Component
{
    #[Url]
    public string $academic_year_id = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('academics.view'), 403);

        if ($this->academic_year_id === '') {
            $this->academic_year_id = (string) (AcademicYear::current()?->id ?? '');
        }
    }

    /**
     * Resolve the signed-in user to exactly one staff record.
     *
     * `User::staff()` is a HasOne, and `staff.user_id` carries no unique
     * index -- so a misconfigured pair of staff rows sharing one login would
     * make it silently return whichever came first, and this page would show
     * one teacher another teacher's work. Zero and many are both reported
     * instead of guessed.
     *
     * @return array{staff: ?Staff, problem: ?string}
     */
    private function resolveStaff(): array
    {
        $candidates = Staff::where('user_id', Auth::id())->get();

        if ($candidates->isEmpty()) {
            return ['staff' => null, 'problem' => 'no_staff'];
        }

        if ($candidates->count() > 1) {
            return ['staff' => null, 'problem' => 'ambiguous_staff'];
        }

        return ['staff' => $candidates->first(), 'problem' => null];
    }

    public function render()
    {
        ['staff' => $staff, 'problem' => $problem] = $this->resolveStaff();

        $active = collect();
        $historical = collect();

        if ($staff && $this->academic_year_id !== '') {
            $assignments = $this->assignmentsFor($staff, (int) $this->academic_year_id);

            $active = $assignments->filter(fn (ClassSubject $a) => $a->isActive())->values();
            $historical = $assignments->reject(fn (ClassSubject $a) => $a->isActive())
                ->sortByDesc('ended_on')->values();
        }

        return view('livewire.teaching.my-assignments', [
            'problem' => $problem,
            'active' => $active,
            'historical' => $historical,
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(),
            'canAssess' => fn (ClassSubject $assignment) => Auth::user()->can('viewFor', [Assessment::class, $assignment]),
            'rosterCount' => fn (ClassSubject $assignment) => $assignment
                ->rosterOn($assignment->ended_on ?? now())
                ->count(),
        ]);
    }

    /**
     * This teacher's assignments for one academic year.
     *
     * The year is never taken from the assignment itself -- there is no such
     * column -- but from whichever roster backs it, matching academicYear().
     * Closed assignments are included: they are this teacher's history.
     */
    private function assignmentsFor(Staff $staff, int $academicYearId)
    {
        return ClassSubject::where('staff_id', $staff->id)
            ->where(function ($query) use ($academicYearId) {
                $query->whereHas('schoolClass', fn ($q) => $q->where('academic_year_id', $academicYearId))
                    ->orWhereHas('teachingGroup', fn ($q) => $q->where('academic_year_id', $academicYearId));
            })
            ->with([
                'subject',
                'schoolClass',
                'teachingGroup.englishLevel.programme',
            ])
            ->orderBy('started_on')
            ->get();
    }
}
