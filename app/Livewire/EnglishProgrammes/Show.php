<?php

namespace App\Livewire\EnglishProgrammes;

use App\Models\EnglishLevel;
use App\Models\EnglishProgramme;
use App\Models\EnglishProgrammeGrade;
use App\Models\Grade;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public EnglishProgramme $englishProgramme;

    public bool $showAddLevel = false;

    public string $level_name = '';

    public string $level_code = '';

    public bool $showLinkGrade = false;

    public string $grade_id = '';

    public function mount(EnglishProgramme $englishProgramme): void
    {
        $this->authorize('view', $englishProgramme);
        $this->englishProgramme = $englishProgramme;
    }

    public function addLevel(): void
    {
        $this->authorize('update', $this->englishProgramme);

        $validated = $this->validate([
            'level_name' => [
                'required', 'string', 'max:100',
                Rule::unique('english_levels', 'name')
                    ->where('english_programme_id', $this->englishProgramme->id),
            ],
            'level_code' => ['nullable', 'string', 'max:50'],
        ]);

        EnglishLevel::create([
            'english_programme_id' => $this->englishProgramme->id,
            'name' => $validated['level_name'],
            'code' => $validated['level_code'] !== '' ? $validated['level_code'] : null,
            'sequence' => ((int) $this->englishProgramme->levels()->max('sequence')) + 1,
            'status' => 'active',
        ]);

        $this->reset(['level_name', 'level_code', 'showAddLevel']);
        $this->englishProgramme->refresh();
    }

    public function toggleLevelStatus(int $levelId): void
    {
        $this->authorize('update', $this->englishProgramme);

        $level = $this->englishProgramme->levels()->findOrFail($levelId);
        $level->update(['status' => $level->isActive() ? 'archived' : 'active']);

        $this->englishProgramme->refresh();
    }

    /**
     * Swap this level with its neighbour. Uses a temporary sentinel because
     * unique(english_programme_id, sequence) would otherwise be violated
     * halfway through the swap.
     */
    public function moveLevel(int $levelId, string $direction): void
    {
        $this->authorize('update', $this->englishProgramme);

        $levels = $this->englishProgramme->levels()->get();
        $current = $levels->firstWhere('id', $levelId);

        if (! $current) {
            return;
        }

        $neighbour = $direction === 'up'
            ? $levels->where('sequence', '<', $current->sequence)->last()
            : $levels->where('sequence', '>', $current->sequence)->first();

        if (! $neighbour) {
            return;
        }

        // Capture BOTH sequences up front: update() re-syncs a model's
        // original attributes, so reading the neighbour's "old" value after
        // writing to it returns the new one and the swap collides.
        $currentSequence = $current->sequence;
        $neighbourSequence = $neighbour->sequence;

        DB::transaction(function () use ($current, $neighbour, $currentSequence, $neighbourSequence) {
            $current->update(['sequence' => 0]);          // sentinel; sequences start at 1
            $neighbour->update(['sequence' => $currentSequence]);
            $current->update(['sequence' => $neighbourSequence]);
        });

        $this->englishProgramme->refresh();
    }

    public function linkGrade(): void
    {
        $this->authorize('update', $this->englishProgramme);

        $validated = $this->validate([
            // unique on the pivot: a grade belongs to at most one programme
            'grade_id' => ['required', 'exists:grades,id', Rule::unique('english_programme_grade', 'grade_id')],
        ]);

        // Written through the model rather than attach() so the change is
        // audited -- attach() bypasses Eloquent events entirely.
        EnglishProgrammeGrade::create([
            'english_programme_id' => $this->englishProgramme->id,
            'grade_id' => $validated['grade_id'],
        ]);

        $this->reset(['grade_id', 'showLinkGrade']);
        $this->englishProgramme->refresh();
    }

    public function unlinkGrade(int $gradeId): void
    {
        $this->authorize('update', $this->englishProgramme);

        // delete() on the model, not detach(), for the same audit reason.
        $this->englishProgramme->gradeLinks()->where('grade_id', $gradeId)->first()?->delete();

        $this->englishProgramme->refresh();
    }

    public function render()
    {
        return view('livewire.english-programmes.show', [
            'levels' => $this->englishProgramme->levels()->get(),
            'linkedGrades' => $this->englishProgramme->gradeLinks()->with('grade')->get()
                ->sortBy(fn ($link) => $link->grade->level_order)->values(),
            // Only grades not already claimed by any programme are offerable.
            'availableGrades' => Grade::whereDoesntHave('englishProgrammeLink')->orderBy('level_order')->get(),
        ]);
    }
}
