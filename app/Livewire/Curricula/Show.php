<?php

namespace App\Livewire\Curricula;

use App\Models\Curriculum;
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

    public function render()
    {
        return view('livewire.curricula.show', [
            'otherVersions' => $this->curriculum->siblingVersions()->get(),
        ]);
    }
}
