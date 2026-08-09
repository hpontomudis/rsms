<?php

namespace App\Livewire\FeeStructures;

use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\Grade;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $name = '';

    public string $grade_id = '';

    public string $academic_year_id = '';

    public function mount(): void
    {
        $this->authorize('create', FeeStructure::class);
        $this->academic_year_id = (string) (AcademicYear::current()?->id ?? '');
    }

    public function save()
    {
        $this->authorize('create', FeeStructure::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'grade_id' => ['required', 'exists:grades,id'],
            'academic_year_id' => [
                'required',
                'exists:academic_years,id',
                Rule::unique('fee_structures')->where('grade_id', $this->grade_id)->where('name', $this->name),
            ],
        ]);

        $structure = FeeStructure::create($validated);

        session()->flash('status', "{$structure->name} was created. Add fee items below.");

        return $this->redirect(route('fee-structures.show', $structure), navigate: true);
    }

    public function render()
    {
        return view('livewire.fee-structures.create', [
            'grades' => Grade::orderBy('level_order')->get(),
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(),
        ]);
    }
}
