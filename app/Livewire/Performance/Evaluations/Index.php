<?php

namespace App\Livewire\Performance\Evaluations;

use App\Models\PerformanceEvaluation;
use App\Models\StaffCategory;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $staff_category_id = '';

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', PerformanceEvaluation::class);
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingStaffCategoryId(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = PerformanceEvaluation::query()->with(['staff', 'framework', 'staffCategory']);

        if ($this->status !== 'all') {
            $query->where('status', $this->status);
        }

        if ($this->staff_category_id !== '') {
            $query->where('staff_category_id', $this->staff_category_id);
        }

        if ($this->search !== '') {
            $query->whereHas('staff', function ($q) {
                $q->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name', 'like', "%{$this->search}%")
                    ->orWhere('staff_number', 'like', "%{$this->search}%");
            });
        }

        $evaluations = $query->orderByDesc('period_start')->paginate(15);

        return view('livewire.performance.evaluations.index', [
            'evaluations' => $evaluations,
            'categories' => StaffCategory::orderBy('name')->get(),
            'canCreate' => Auth::user()->can('create', PerformanceEvaluation::class),
        ]);
    }
}
