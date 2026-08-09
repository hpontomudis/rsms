<?php

namespace App\Livewire\Classes;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\SchoolClass;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $name = '';

    public string $grade_id = '';

    public string $academic_year_id = '';

    public string $capacity = '';

    public function mount(): void
    {
        $this->authorize('create', SchoolClass::class);
        $this->academic_year_id = (string) (AcademicYear::current()?->id ?? '');
    }

    public function save()
    {
        $this->authorize('create', SchoolClass::class);

        // Validated as a plain array (not $this->validate()) so an empty
        // capacity input can be normalized to null before the 'integer'
        // rule runs -- 'nullable' alone does not exempt '' from it.
        $validated = validator([
            'name' => $this->name,
            'grade_id' => $this->grade_id,
            'academic_year_id' => $this->academic_year_id,
            'capacity' => trim($this->capacity) === '' ? null : $this->capacity,
        ], [
            'name' => ['required', 'string', 'max:100'],
            'grade_id' => ['required', 'exists:grades,id'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
        ])->validate();

        $class = SchoolClass::create($validated);

        session()->flash('status', "{$class->name} was created.");

        return $this->redirect(route('classes.show', $class), navigate: true);
    }

    public function render()
    {
        return view('livewire.classes.create', [
            'grades' => Grade::orderBy('level_order')->get(),
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(),
        ]);
    }
}
