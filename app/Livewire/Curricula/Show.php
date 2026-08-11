<?php

namespace App\Livewire\Curricula;

use App\Models\Curriculum;
use App\Models\EnglishLevel;
use App\Models\LearningPhase;
use App\Services\CurriculumScopeService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Curriculum $curriculum;

    public function mount(Curriculum $curriculum): void
    {
        $this->authorize('view', $curriculum);
        $this->curriculum = $curriculum;
    }

    /**
     * Put a draft into force. Any version of the same family that is currently
     * active is archived first -- superseding, not overwriting, so the old
     * version stays readable as the standards that were actually in force.
     * The partial unique index is the backstop if this is ever bypassed.
     */
    public function activate(): void
    {
        $this->authorize('update', $this->curriculum);

        if (! $this->curriculum->isDraft()) {
            throw ValidationException::withMessages(['status' => 'Only a draft version can be activated.']);
        }

        \DB::transaction(function () {
            $superseded = Curriculum::where('code', $this->curriculum->code)
                ->whereKeyNot($this->curriculum->id)
                ->active()
                ->get();

            foreach ($superseded as $old) {
                $old->update([
                    'status' => 'archived',
                    // Close the outgoing version the day before this one starts,
                    // unless it already carries an end date.
                    'effective_to' => $old->effective_to
                        ?? $this->curriculum->effective_from->copy()->subDay(),
                ]);
            }

            $this->curriculum->update(['status' => 'active']);
        });

        $this->curriculum->refresh();
    }

    public function archive(): void
    {
        $this->authorize('update', $this->curriculum);

        if ($this->curriculum->isArchived()) {
            return;
        }

        $this->curriculum->update(['status' => 'archived']);
        $this->curriculum->refresh();
    }

    // ------------------------------------------------------------- scopes

    public bool $showAddScope = false;

    public string $scope_basis_id = '';

    /**
     * Add a phase or a level, whichever this curriculum is scoped by. The
     * service decides which and enforces every rule; this only forwards.
     */
    public function addScope(CurriculumScopeService $scopes): void
    {
        $this->authorize('update', $this->curriculum);

        $validated = $this->validate(['scope_basis_id' => ['required', 'integer']]);

        if ($this->curriculum->isEnglishProgrammeBound()) {
            $scopes->addEnglishLevel($this->curriculum, EnglishLevel::findOrFail($validated['scope_basis_id']));
        } else {
            $scopes->addPhase($this->curriculum, LearningPhase::findOrFail($validated['scope_basis_id']));
        }

        $this->reset(['scope_basis_id', 'showAddScope']);
        $this->curriculum->refresh();
    }

    public function removeScope(int $scopeId, CurriculumScopeService $scopes): void
    {
        $scope = $this->curriculum->scopes()->findOrFail($scopeId);
        $this->authorize('delete', $scope);

        $scopes->remove($scope);

        $this->curriculum->refresh();
    }

    public function render(CurriculumScopeService $scopes)
    {
        return view('livewire.curricula.show', [
            'otherVersions' => $this->curriculum->siblingVersions()->get(),
            'scopes' => $this->curriculum->scopes()
                ->with(['learningPhase', 'englishLevel'])
                ->withCount('learningOutcomes')
                ->get()
                ->sortBy(fn ($scope) => $scope->learningPhase?->sequence ?? $scope->englishLevel?->sequence)
                ->values(),
            'availableBases' => $this->showAddScope ? $scopes->availableBases($this->curriculum) : collect(),
            'vocabulary' => $this->curriculum->vocabulary(),
        ]);
    }
}
