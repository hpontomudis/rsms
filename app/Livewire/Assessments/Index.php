<?php

namespace App\Livewire\Assessments;

use App\Models\Assessment;
use App\Models\ClassSubject;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    #[Url]
    public string $class_subject_id = '';

    public ClassSubject $classSubject;

    public function mount(): void
    {
        $this->classSubject = ClassSubject::with('subject', 'teacher', 'schoolClass', 'teachingGroup')
            ->findOrFail($this->class_subject_id);
        $this->authorize('viewFor', [Assessment::class, $this->classSubject]);
    }

    public function render()
    {
        return view('livewire.assessments.index', [
            'assessments' => $this->classSubject->assessments()->withCount('results')->orderByDesc('assessment_date')->get(),
        ]);
    }
}
