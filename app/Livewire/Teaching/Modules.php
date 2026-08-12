<?php

namespace App\Livewire\Teaching;

use App\Models\ClassSubject;
use App\Models\CurriculumScope;
use App\Models\TeachingModule;
use App\Services\TeachingModuleService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The modules written for ONE teaching assignment.
 *
 * Scoped to the assignment rather than the roster, because that is where a
 * module belongs: a successor sees their predecessor's list through the
 * predecessor's own historical card, not merged into their own.
 */
#[Layout('layouts.app')]
class Modules extends Component
{
    public ClassSubject $classSubject;

    public bool $showCreate = false;

    public string $curriculum_scope_id = '';

    public string $title = '';

    public string $topic = '';

    public string $planned_activity = '';

    public function mount(ClassSubject $classSubject): void
    {
        abort_unless(Auth::user()->can('academics.view'), 403);
        $this->classSubject = $classSubject;
    }

    public function create(TeachingModuleService $modules)
    {
        $this->authorize('createFor', [TeachingModule::class, $this->classSubject]);

        $validated = $this->validate([
            'curriculum_scope_id' => ['required', 'exists:curriculum_scopes,id'],
            'title' => ['required', 'string', 'max:200'],
            'topic' => ['nullable', 'string', 'max:200'],
            'planned_activity' => ['required', 'string'],
        ]);

        $module = $modules->create(
            $this->classSubject,
            CurriculumScope::findOrFail($validated['curriculum_scope_id']),
            $validated,
        );

        return $this->redirect(route('teaching.modules.show', $module), navigate: true);
    }

    public function render(TeachingModuleService $modules)
    {
        $scopes = $modules->eligibleScopes($this->classSubject);

        // Exactly one candidate may be preselected; several must be chosen
        // from, and zero is a configuration problem the screen names.
        if ($this->curriculum_scope_id === '' && $scopes->count() === 1) {
            $this->curriculum_scope_id = (string) $scopes->first()->id;
        }

        $vocabulary = $scopes->first()?->curriculum?->vocabulary()
            ?? ['module' => 'Teaching Module', 'modules' => 'Teaching Modules'];

        return view('livewire.teaching.modules', [
            'vocabulary' => $vocabulary,
            'scopes' => $scopes,
            'modules' => TeachingModule::where('class_subject_id', $this->classSubject->id)
                ->withCount('objectiveLinks')
                ->orderByDesc('id')->get(),
            'canCreate' => Auth::user()->can('createFor', [TeachingModule::class, $this->classSubject]),
        ]);
    }
}
