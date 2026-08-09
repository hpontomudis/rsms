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
        $terms = ['Term 1', 'Term 2', 'Term 3'];
        $rows = collect();
        $overallAverage = null;

        if ($this->academic_year_id !== '') {
            $classIds = $this->student->classes()
                ->where('academic_year_id', $this->academic_year_id)
                ->pluck('classes.id');

            $classSubjects = ClassSubject::whereIn('class_id', $classIds)
                ->with('subject')
                ->get();

            $rows = $classSubjects->map(function (ClassSubject $classSubject) use ($terms) {
                $results = AssessmentResult::where('student_id', $this->student->id)
                    ->whereHas('assessment', fn ($q) => $q->where('class_subject_id', $classSubject->id))
                    ->with('assessment')
                    ->get();

                $percentages = fn ($group) => $group->map(
                    fn (AssessmentResult $r) => (float) $r->score / (float) $r->assessment->max_score * 100
                );

                $termAverages = collect($terms)->mapWithKeys(function ($term) use ($results, $percentages) {
                    $inTerm = $results->filter(fn (AssessmentResult $r) => $r->assessment->term === $term);

                    return [$term => $inTerm->isNotEmpty() ? round($percentages($inTerm)->avg()) : null];
                });

                $overall = $results->isNotEmpty() ? round($percentages($results)->avg()) : null;

                return (object) [
                    'subject' => $classSubject->subject,
                    'termAverages' => $termAverages,
                    'overall' => $overall,
                ];
            });

            $withOverall = $rows->pluck('overall')->filter(fn ($v) => $v !== null);
            $overallAverage = $withOverall->isNotEmpty() ? round($withOverall->avg()) : null;
        }

        return view('livewire.academics.report-card', [
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(),
            'terms' => $terms,
            'rows' => $rows,
            'overallAverage' => $overallAverage,
        ]);
    }
}
