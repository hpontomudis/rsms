<?php

namespace App\Livewire\Subjects;

use App\Models\Grade;
use App\Models\Subject;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $name = '';

    public string $grade_id = '';

    public function mount(): void
    {
        $this->authorize('create', Subject::class);
    }

    public function save()
    {
        $this->authorize('create', Subject::class);

        // Validated as a plain array (not $this->validate()) so an empty
        // grade selection can be normalized to null before the 'exists'
        // rule runs -- 'nullable' alone does not exempt '' from it.
        $validated = validator([
            'name' => $this->name,
            'grade_id' => $this->grade_id !== '' ? $this->grade_id : null,
        ], [
            'name' => ['required', 'string', 'max:100'],
            'grade_id' => ['nullable', 'exists:grades,id'],
        ])->validate();

        Subject::create($validated);

        session()->flash('status', "{$validated['name']} was created.");

        return $this->redirect(route('subjects.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.subjects.create', ['grades' => Grade::orderBy('level_order')->get()]);
    }
}
