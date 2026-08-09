<?php

namespace App\Livewire\Assessments;

use App\Models\Assessment;
use App\Models\ClassSubject;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    #[Url]
    public string $class_subject_id = '';

    public ClassSubject $classSubject;

    public string $name = '';

    public string $term = 'Term 1';

    public string $max_score = '100';

    public string $assessment_date = '';

    public function mount(): void
    {
        $this->classSubject = ClassSubject::with('subject', 'schoolClass')->findOrFail($this->class_subject_id);
        $this->authorize('createFor', [Assessment::class, $this->classSubject]);
        $this->assessment_date = now()->toDateString();
    }

    public function save()
    {
        $this->authorize('createFor', [Assessment::class, $this->classSubject]);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'term' => ['required', 'string', 'max:50'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:1000'],
            'assessment_date' => ['required', 'date'],
        ]);

        $assessment = $this->classSubject->assessments()->create($validated);

        return $this->redirect(route('assessments.show', $assessment), navigate: true);
    }

    public function render()
    {
        return view('livewire.assessments.create');
    }
}
