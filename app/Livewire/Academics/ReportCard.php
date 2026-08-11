<?php

namespace App\Livewire\Academics;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Services\ReportCardBuilder;
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

    public function render(ReportCardBuilder $builder)
    {
        $year = $this->academic_year_id !== ''
            ? AcademicYear::find($this->academic_year_id)
            : null;

        // Discovery lives in the builder: a subject reaches this card through
        // recorded results OR through participation, for classes and teaching
        // groups alike, and that is too much to read inside a render method.
        $card = $year
            ? $builder->build($this->student, $year)
            : ['periods' => collect(), 'rows' => collect(), 'overallAverage' => null];

        return view('livewire.academics.report-card', [
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(),
            'periods' => $card['periods'],
            'rows' => $card['rows'],
            'overallAverage' => $card['overallAverage'],
        ]);
    }
}
