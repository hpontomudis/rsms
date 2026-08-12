<?php

namespace App\Livewire\Planning;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\LearningPathway;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeachingGroup;
use App\Services\AnnualProgrammeService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Start an annual plan for a roster and subject.
 *
 * The roster is chosen directly -- a class OR a teaching group -- never a
 * teaching assignment, because the plan outlives the assignment. An assignment
 * may be passed in the URL to prefill, and that is all it does.
 */
#[Layout('layouts.app')]
class Create extends Component
{
    #[Url]
    public string $class_subject_id = '';

    public string $academic_year_id = '';

    public string $roster_type = 'class';

    public string $class_id = '';

    public string $teaching_group_id = '';

    public string $subject_id = '';

    public string $learning_pathway_id = '';

    public string $title = '';

    public string $notes = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('academics.plan') || Auth::user()->can('academics.manage'), 403);

        $this->academic_year_id = (string) (AcademicYear::current()?->id ?? '');

        if ($this->class_subject_id !== '') {
            $assignment = ClassSubject::find($this->class_subject_id);

            if ($assignment) {
                $this->roster_type = $assignment->class_id ? 'class' : 'group';
                $this->class_id = (string) ($assignment->class_id ?? '');
                $this->teaching_group_id = (string) ($assignment->teaching_group_id ?? '');
                $this->subject_id = (string) $assignment->subject_id;
                $this->academic_year_id = (string) $assignment->academicYear()->id;
            }
        }
    }

    public function updated(string $property): void
    {
        // Any change to the anchor invalidates the pathway shortlist.
        if (in_array($property, ['roster_type', 'class_id', 'teaching_group_id', 'subject_id', 'academic_year_id'], true)) {
            $this->learning_pathway_id = '';
        }

        if ($property === 'roster_type') {
            $this->reset(['class_id', 'teaching_group_id']);
        }
    }

    private function roster(): array
    {
        $class = $this->roster_type === 'class' && $this->class_id !== ''
            ? SchoolClass::with('grade.learningPhaseLink')->find($this->class_id) : null;
        $group = $this->roster_type === 'group' && $this->teaching_group_id !== ''
            ? TeachingGroup::find($this->teaching_group_id) : null;
        $subject = $this->subject_id !== '' ? Subject::find($this->subject_id) : null;

        return [$class, $group, $subject];
    }

    public function save(AnnualProgrammeService $programmes)
    {
        $this->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'class_id' => [$this->roster_type === 'class' ? 'required' : 'nullable', 'exists:classes,id'],
            'teaching_group_id' => [$this->roster_type === 'group' ? 'required' : 'nullable', 'exists:teaching_groups,id'],
            'learning_pathway_id' => ['required', 'exists:learning_pathways,id'],
            'title' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string'],
        ]);

        [$class, $group, $subject] = $this->roster();

        $this->authorize('createFor', [
            \App\Models\AnnualProgramme::class,
            $class?->id,
            $group?->id,
            $subject->id,
        ]);

        $pathway = LearningPathway::findOrFail($this->learning_pathway_id);
        $attributes = [
            'title' => $this->title !== '' ? $this->title : null,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ];

        $programme = $class
            ? $programmes->createForClass($class, $subject, $pathway, $attributes)
            : $programmes->createForGroup($group, $subject, $pathway, $attributes);

        return $this->redirect(route('planning.annual.show', $programme), navigate: true);
    }

    public function render(AnnualProgrammeService $programmes)
    {
        [$class, $group, $subject] = $this->roster();

        return view('livewire.planning.create', [
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(),
            'classes' => $this->academic_year_id !== ''
                ? SchoolClass::where('academic_year_id', $this->academic_year_id)->with('grade')->orderBy('name')->get()
                : collect(),
            'groups' => $this->academic_year_id !== ''
                ? TeachingGroup::where('academic_year_id', $this->academic_year_id)->with('englishLevel')->orderBy('name')->get()
                : collect(),
            'subjects' => Subject::orderBy('name')->get(),
            // Only pathways this exact roster could legitimately follow.
            'pathways' => $subject && ($class || $group)
                ? $programmes->eligiblePathways($subject, $class, $group)
                : collect(),
        ]);
    }
}
