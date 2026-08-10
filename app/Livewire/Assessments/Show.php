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
        $this->assessment = $assessment->load(
            'classSubject.subject', 'classSubject.schoolClass', 'classSubject.teachingGroup', 'classSubject.teacher'
        );

        // Roster as at the assessment date, plus anyone already scored --
        // see Assessment::scoreSheetStudents().
        $students = $this->assessment->scoreSheetStudents();

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

        // The form is not the authority on who may be scored. A tampered
        // payload could carry any student id, so writes are checked against
        // the assessment's own allowlist -- roster on the day, plus students
        // already holding a result. Sharing a grade with the group is not
        // sufficient, and never has been for classes either.
        $allowed = $this->assessment->scorableStudentIds()->all();

        $rules = [];
        foreach ($this->scores as $studentId => $score) {
            if (! in_array((int) $studentId, $allowed, true)) {
                continue;
            }

            $rules["scores.{$studentId}"] = ['nullable', 'numeric', 'min:0', 'max:'.$this->assessment->max_score];
        }
        $this->validate($rules);

        foreach ($this->scores as $studentId => $score) {
            if ($score === '' || $score === null || ! in_array((int) $studentId, $allowed, true)) {
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
        return view('livewire.assessments.show', [
            'students' => $this->assessment->scoreSheetStudents(),
            // Used only to flag students kept on the sheet by an existing
            // score rather than by current roster membership.
            'currentRosterIds' => $this->assessment->classSubject
                ->rosterStudentIdsOn($this->assessment->assessment_date)->all(),
            'canRecord' => Auth::user()->can('recordScores', $this->assessment),
        ]);
    }
}
