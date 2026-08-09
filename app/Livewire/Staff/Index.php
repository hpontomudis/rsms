<?php

namespace App\Livewire\Staff;

use App\Models\Staff;
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

    #[Url]
    public string $status = 'active';

    public function mount(): void
    {
        $this->authorize('viewAny', Staff::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Staff::query()->with('position');

        if ($this->status !== 'all') {
            $query->where('status', $this->status);
        }

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name', 'like', "%{$this->search}%")
                    ->orWhere('staff_number', 'like', "%{$this->search}%");
            });
        }

        $staff = $query->orderBy('last_name')->orderBy('first_name')->paginate(15);

        return view('livewire.staff.index', ['staff' => $staff]);
    }
}
