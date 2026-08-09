<?php

namespace App\Livewire\Guardians;

use App\Models\Guardian;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Guardian::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Guardian::query()->withCount('students');

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%");
            });
        }

        $guardians = $query->orderBy('last_name')->orderBy('first_name')->paginate(15);

        return view('livewire.guardians.index', ['guardians' => $guardians]);
    }
}
