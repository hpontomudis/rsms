<?php

namespace App\Livewire\Assessments;

use App\Models\Assessment;
use App\Models\ClassSubject;
use Illuminate\Support\Facades\Auth;
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
        // The roster page is the natural place to go back to, but a teacher
        // cannot open a teaching-group screen -- so for them the workspace is.
        $roster = $this->classSubject->isClassBacked()
            ? $this->classSubject->schoolClass
            : $this->classSubject->teachingGroup;

        $canSeeRoster = $roster && Auth::user()->can('view', $roster);

        return view('livewire.assessments.index', [
            'backUrl' => $canSeeRoster ? $this->classSubject->rosterUrl() : route('my-teaching'),
            'backLabel' => $canSeeRoster ? $this->classSubject->displayName() : 'My Teaching Assignments',
            'assessments' => $this->classSubject->assessments()->withCount('results')->orderByDesc('assessment_date')->get(),
        ]);
    }
}
