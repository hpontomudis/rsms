<?php

namespace App\Livewire\FeeStructures;

use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\Grade;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public FeeStructure $feeStructure;

    public string $name = '';

    public string $grade_id = '';

    public string $academic_year_id = '';

    public function mount(FeeStructure $feeStructure): void
    {
        $this->authorize('update', $feeStructure);
        $this->feeStructure = $feeStructure;
        $this->name = $feeStructure->name;
        $this->grade_id = (string) $feeStructure->grade_id;
        $this->academic_year_id = (string) $feeStructure->academic_year_id;
    }

    public function save()
    {
        $this->authorize('update', $this->feeStructure);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'grade_id' => ['required', 'exists:grades,id'],
            'academic_year_id' => [
                'required',
                'exists:academic_years,id',
                Rule::unique('fee_structures')
                    ->where('grade_id', $this->grade_id)
                    ->where('name', $this->name)
                    ->ignore($this->feeStructure->id),
            ],
        ]);

        $this->feeStructure->update($validated);

        session()->flash('status', "{$this->feeStructure->name} was updated.");

        return $this->redirect(route('fee-structures.show', $this->feeStructure), navigate: true);
    }

    public function render()
    {
        return view('livewire.fee-structures.edit', [
            'grades' => Grade::orderBy('level_order')->get(),
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(),
        ]);
    }
}
