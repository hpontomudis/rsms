<?php

namespace App\Livewire\Invoices;

use App\Models\AcademicYear;
use App\Models\Invoice;
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
    public string $status = 'all';

    #[Url]
    public string $academic_year_id = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Invoice::class);

        if ($this->academic_year_id === '') {
            $this->academic_year_id = (string) (AcademicYear::current()?->id ?? '');
        }
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
        $query = Invoice::query()->with(['student', 'items', 'discounts', 'payments']);

        if ($this->academic_year_id !== '') {
            $query->where('academic_year_id', $this->academic_year_id);
        }

        if ($this->status !== 'all') {
            $query->where('status', $this->status);
        }

        if ($this->search !== '') {
            $query->whereHas('student', function ($q) {
                $q->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name', 'like', "%{$this->search}%")
                    ->orWhere('student_number', 'like', "%{$this->search}%");
            });
        }

        $invoices = $query->orderByDesc('issue_date')->paginate(15);

        return view('livewire.invoices.index', [
            'invoices' => $invoices,
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(),
        ]);
    }
}
