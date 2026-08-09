<?php

namespace App\Livewire\Assessments;

use App\Models\Assessment;
use App\Models\AssessmentResult;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Assessment $assessment;

    /** @var array<int, string> student_id => score */
    public array $scores = [];

    public bool $saved = false;

    public function mount(Assessment $assessment): void
    {
        $this->authorize('view', $assessment);
        $this->assessment = $assessment->load('classSubject.subject', 'classSubject.schoolClass', 'classSubject.teacher');

        $students = $this->assessment->classSubject->schoolClass
            ->students()->wherePivot('status', 'active')->get();

        $existing = $this->assessment->results()->get()->keyBy('student_id');

        foreach ($students as $student) {
            $this->scores[$student->id] = $existing->has($student->id)
                ? (string) $existing[$student->id]->score
                : '';
        }
    }

    public function save(): void
    {
        $this->authorize('recordScores', $this->assessment);

        $rules = [];
        foreach ($this->scores as $studentId => $score) {
            $rules["scores.{$studentId}"] = ['nullable', 'numeric', 'min:0', 'max:'.$this->assessment->max_score];
        }
        $this->validate($rules);

        foreach ($this->scores as $studentId => $score) {
            if ($score === '' || $score === null) {
                continue;
            }

            AssessmentResult::updateOrCreate(
                ['assessment_id' => $this->assessment->id, 'student_id' => $studentId],
                ['score' => $score]
            );
        }

        $this->saved = true;
    }

    public function render()
    {
        $students = $this->assessment->classSubject->schoolClass
            ->students()->wherePivot('status', 'active')->orderBy('first_name')->get();

        return view('livewire.assessments.show', [
            'students' => $students,
            'canRecord' => Auth::user()->can('recordScores', $this->assessment),
        ]);
    }
}
