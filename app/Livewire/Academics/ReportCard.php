<?php

namespace App\Livewire\Academics;

use App\Models\AcademicYear;
use App\Models\AssessmentResult;
use App\Models\ClassSubject;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class ReportCard extends Component
{
    public Student $student;

    #[Url]
    public string $academic_year_id = '';

    public function mount(Student $student): void
    {
        $this->authorize('view', $student);
        abort_unless(Auth::user()->can('academics.view'), 403);

        $this->student = $student;

        if ($this->academic_year_id === '') {
            $this->academic_year_id = (string) (AcademicYear::current()?->id ?? '');
        }
    }

    public function render()
    {
        $periods = collect();
        $rows = collect();
        $overallAverage = null;

        if ($this->academic_year_id !== '') {
            // Columns come from the database, ordered by sequence. However many
            // periods a year defines -- two, three, more -- is a data question,
            // never a code one.
            $periods = AcademicYear::find($this->academic_year_id)?->periods ?? collect();

            $classIds = $this->student->classes()
                ->where('academic_year_id', $this->academic_year_id)
                ->pluck('classes.id');

            // Grouped by subject, NOT by assignment: a subject whose teacher
            // changed mid-year has several class_subject rows, and the report
            // card must show it once with every assessment merged in --
            // regardless of which teacher recorded which.
            $rows = ClassSubject::whereIn('class_id', $classIds)
                ->with('subject')
                ->get()
                ->groupBy('subject_id')
                ->map(function ($assignments) use ($periods) {
                    $subject = $assignments->first()->subject;
                    $assignmentIds = $assignments->pluck('id');

                    $results = AssessmentResult::where('student_id', $this->student->id)
                        ->whereHas('assessment', fn ($q) => $q->whereIn('class_subject_id', $assignmentIds))
                        ->with('assessment')
                        ->get();

                    $percentages = fn ($group) => $group->map(
                        fn (AssessmentResult $r) => (float) $r->score / (float) $r->assessment->max_score * 100
                    );

                    $periodAverages = $periods->mapWithKeys(function ($period) use ($results, $percentages) {
                        $inPeriod = $results->filter(
                            fn (AssessmentResult $r) => $r->assessment->academic_period_id === $period->id
                        );

                        return [$period->id => $inPeriod->isNotEmpty() ? round($percentages($inPeriod)->avg()) : null];
                    });

                    $overall = $results->isNotEmpty() ? round($percentages($results)->avg()) : null;

                    return (object) [
                        'subject' => $subject,
                        'periodAverages' => $periodAverages,
                        'overall' => $overall,
                    ];
                })
                ->values();

            $withOverall = $rows->pluck('overall')->filter(fn ($v) => $v !== null);
            $overallAverage = $withOverall->isNotEmpty() ? round($withOverall->avg()) : null;
        }

        return view('livewire.academics.report-card', [
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(),
            'periods' => $periods,
            'rows' => $rows,
            'overallAverage' => $overallAverage,
        ]);
    }
}
