<?php

namespace App\Livewire\Classes;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\SchoolClass;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public SchoolClass $schoolClass;

    public string $name = '';

    public string $grade_id = '';

    public string $academic_year_id = '';

    public string $capacity = '';

    public function mount(SchoolClass $schoolClass): void
    {
        $this->authorize('update', $schoolClass);
        $this->schoolClass = $schoolClass;
        $this->name = $schoolClass->name;
        $this->grade_id = (string) $schoolClass->grade_id;
        $this->academic_year_id = (string) $schoolClass->academic_year_id;
        $this->capacity = $schoolClass->capacity !== null ? (string) $schoolClass->capacity : '';
    }

    public function save()
    {
        $this->authorize('update', $this->schoolClass);

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

        $this->schoolClass->update($validated);

        session()->flash('status', "{$this->schoolClass->name} was updated.");

        return $this->redirect(route('classes.show', $this->schoolClass), navigate: true);
    }

    public function render()
    {
        return view('livewire.classes.edit', [
            'grades' => Grade::orderBy('level_order')->get(),
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(),
        ]);
    }
}
