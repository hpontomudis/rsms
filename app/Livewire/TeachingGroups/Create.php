<?php

namespace App\Livewire\TeachingGroups;

use App\Models\AcademicYear;
use App\Models\EnglishProgramme;
use App\Models\TeachingGroup;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $academic_year_id = '';

    public string $name = '';

    public string $english_level_id = '';

    public function mount(): void
    {
        $this->authorize('create', TeachingGroup::class);
        $this->academic_year_id = (string) (AcademicYear::current()?->id ?? '');
    }

    public function save()
    {
        $this->authorize('create', TeachingGroup::class);

        // Empty strings from unselected <select>s are not "absent" as far as
        // the integer/exists rules are concerned, so normalise first.
        $levelId = $this->english_level_id !== '' ? $this->english_level_id : null;

        $validated = validator([
            'academic_year_id' => $this->academic_year_id !== '' ? $this->academic_year_id : null,
            'name' => $this->name,
            'english_level_id' => $levelId,
        ], [
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('teaching_groups', 'name')->where('academic_year_id', $this->academic_year_id),
            ],
            'english_level_id' => ['nullable', 'exists:english_levels,id'],
        ], [
            'name.unique' => 'A group with that name already exists in this academic year.',
        ])->validate();

        $group = TeachingGroup::create([
            'academic_year_id' => $validated['academic_year_id'],
            'name' => $validated['name'],
            'english_level_id' => $validated['english_level_id'],
            'status' => 'active',
        ]);

        session()->flash('status', "{$group->name} was created. Add students to it below.");

        return $this->redirect(route('teaching-groups.show', $group), navigate: true);
    }

    public function render()
    {
        return view('livewire.teaching-groups.create', [
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(),
            'programmes' => EnglishProgramme::active()->with('levels')->orderBy('name')->get(),
        ]);
    }
}
