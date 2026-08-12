<?php

namespace App\Livewire\Planning;

use App\Models\AcademicYear;
use App\Models\AnnualProgramme;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Every annual plan for one academic year.
 *
 * Anyone who may see academics may read the plans -- a plan is a public
 * statement of what a class will be taught, not private teacher work.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    #[Url]
    public string $academic_year_id = '';

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('academics.view'), 403);

        if ($this->academic_year_id === '') {
            $this->academic_year_id = (string) (AcademicYear::current()?->id ?? '');
        }
    }

    public function render()
    {
        $programmes = AnnualProgramme::query()
            ->when($this->academic_year_id !== '', fn ($q) => $q->where('academic_year_id', $this->academic_year_id))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->with(['schoolClass', 'teachingGroup', 'subject', 'learningPathway', 'curriculumScope.curriculum'])
            ->withCount('items')
            ->get()
            ->sortBy([fn ($a, $b) => strcmp($a->rosterName(), $b->rosterName()), fn ($a, $b) => strcmp($a->subject->name, $b->subject->name)])
            ->values();

        return view('livewire.planning.index', [
            'programmes' => $programmes,
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(),
        ]);
    }
}
