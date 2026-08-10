<?php

namespace App\Livewire\TeachingGroups;

use App\Models\AcademicYear;
use App\Models\TeachingGroup;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    #[Url]
    public string $academic_year_id = '';

    public function mount(): void
    {
        $this->authorize('viewAny', TeachingGroup::class);

        if ($this->academic_year_id === '') {
            $this->academic_year_id = (string) (AcademicYear::current()?->id ?? AcademicYear::orderByDesc('start_date')->first()?->id ?? '');
        }
    }

    public function render()
    {
        $groups = $this->academic_year_id === ''
            ? collect()
            : TeachingGroup::where('academic_year_id', $this->academic_year_id)
                ->with('englishLevel.programme')
                ->withCount(['activeMemberships'])
                ->orderBy('name')
                ->get();

        return view('livewire.teaching-groups.index', [
            'groups' => $groups,
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(),
        ]);
    }
}
