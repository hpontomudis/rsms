<?php

namespace App\Livewire\Curricula;

use App\Models\Curriculum;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', Curriculum::class);
    }

    public function render()
    {
        return view('livewire.curricula.index', [
            // Grouped by family so successive versions of one curriculum read
            // as a history rather than as unrelated rows.
            'families' => Curriculum::with('englishProgramme')
                ->orderBy('code')->orderByDesc('effective_from')
                ->get()
                ->groupBy('code'),
        ]);
    }
}
