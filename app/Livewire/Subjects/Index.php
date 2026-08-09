<?php

namespace App\Livewire\Subjects;

use App\Models\Subject;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('viewAny', Subject::class);
    }

    public function render()
    {
        $subjects = Subject::with('grade')->orderBy('name')->paginate(20);

        return view('livewire.subjects.index', ['subjects' => $subjects]);
    }
}
