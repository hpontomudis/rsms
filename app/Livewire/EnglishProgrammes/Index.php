<?php

namespace App\Livewire\EnglishProgrammes;

use App\Models\EnglishProgramme;
use App\Models\Grade;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', EnglishProgramme::class);
    }

    public function render()
    {
        return view('livewire.english-programmes.index', [
            'programmes' => EnglishProgramme::withCount(['levels', 'gradeLinks'])->orderBy('name')->get(),
            'unmappedGrades' => Grade::whereDoesntHave('englishProgrammeLink')->orderBy('level_order')->get(),
        ]);
    }
}
