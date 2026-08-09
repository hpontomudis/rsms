<?php

namespace App\Livewire\FeeStructures;

use App\Models\AcademicYear;
use App\Models\FeeStructure;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $academic_year_id = '';

    public function mount(): void
    {
        $this->authorize('viewAny', FeeStructure::class);

        if ($this->academic_year_id === '') {
            $this->academic_year_id = (string) (AcademicYear::current()?->id ?? '');
        }
    }

    public function render()
    {
        $query = FeeStructure::query()->with(['grade', 'academicYear', 'items']);

        if ($this->academic_year_id !== '') {
            $query->where('academic_year_id', $this->academic_year_id);
        }

        $structures = $query->orderBy('grade_id')->paginate(15);

        return view('livewire.fee-structures.index', [
            'structures' => $structures,
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(),
        ]);
    }
}
