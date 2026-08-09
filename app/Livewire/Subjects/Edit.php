<?php

namespace App\Livewire\Subjects;

use App\Models\Grade;
use App\Models\Subject;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public Subject $subject;

    public string $name = '';

    public string $grade_id = '';

    public function mount(Subject $subject): void
    {
        $this->authorize('update', $subject);
        $this->subject = $subject;
        $this->name = $subject->name;
        $this->grade_id = $subject->grade_id !== null ? (string) $subject->grade_id : '';
    }

    public function save()
    {
        $this->authorize('update', $this->subject);

        $validated = validator([
            'name' => $this->name,
            'grade_id' => $this->grade_id !== '' ? $this->grade_id : null,
        ], [
            'name' => ['required', 'string', 'max:100'],
            'grade_id' => ['nullable', 'exists:grades,id'],
        ])->validate();

        $this->subject->update($validated);

        session()->flash('status', "{$this->subject->name} was updated.");

        return $this->redirect(route('subjects.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.subjects.edit', ['grades' => Grade::orderBy('level_order')->get()]);
    }
}
