<?php

namespace App\Livewire\Attendance;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Report extends Component
{
    #[Url]
    public string $class_id = '';

    #[Url]
    public string $start_date = '';

    #[Url]
    public string $end_date = '';

    public function mount(): void
    {
        $this->authorize('attendance.view');

        $this->end_date = now()->toDateString();
        $this->start_date = now()->subDays(29)->toDateString();

        $classes = $this->availableClasses();
        if ($this->class_id === '' && $classes->count() === 1) {
            $this->class_id = (string) $classes->first()->id;
        }
    }

    protected function availableClasses()
    {
        $user = Auth::user();
        $currentYear = AcademicYear::current();

        if (! $currentYear) {
            return collect();
        }

        $query = SchoolClass::where('academic_year_id', $currentYear->id)->with('grade');

        if ($user->hasRole('teacher')) {
            $query->taughtBy($user->staff?->id ?? 0);
        }

        return $query->orderBy('name')->get();
    }

    public function render()
    {
        $rows = collect();
        $schoolClass = null;
        $classAverage = null;

        if ($this->class_id !== '') {
            $schoolClass = SchoolClass::with('grade')->find($this->class_id);
        }

        if ($schoolClass) {
            $this->authorize('viewFor', [Attendance::class, $schoolClass]);

            $students = $schoolClass->students()->wherePivot('status', 'active')->orderBy('first_name')->get();

            $sessions = Attendance::where('class_id', $schoolClass->id)
                ->whereBetween('date', [$this->start_date, $this->end_date])
                ->with('records')
                ->get();

            $totalSessions = $sessions->count();

            $rows = $students->map(function ($student) use ($sessions, $totalSessions) {
                $counts = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];

                foreach ($sessions as $session) {
                    $record = $session->records->firstWhere('student_id', $student->id);
                    if ($record) {
                        $counts[$record->status]++;
                    }
                }

                $marked = array_sum($counts);
                $rate = $marked > 0 ? round(($counts['present'] + $counts['late']) / $marked * 100) : null;

                return (object) [
                    'student' => $student,
                    'counts' => $counts,
                    'rate' => $rate,
                    'sessionsMissed' => $totalSessions - $marked,
                ];
            });

            $ratesWithData = $rows->pluck('rate')->filter(fn ($r) => $r !== null);
            $classAverage = $ratesWithData->isNotEmpty() ? round($ratesWithData->avg()) : null;
        }

        return view('livewire.attendance.report', [
            'classes' => $this->availableClasses(),
            'schoolClass' => $schoolClass,
            'rows' => $rows,
            'classAverage' => $classAverage,
        ]);
    }
}
