<?php

namespace App\Livewire\Students;

use App\Models\Student;
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
    public string $search = '';

    #[Url]
    public string $status = 'active';

    public function mount(): void
    {
        $this->authorize('viewAny', Student::class);
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
        $user = Auth::user();

        $query = Student::query()->with('user');

        if ($user->hasRole('teacher')) {
            $query->taughtBy($user->staff?->id ?? 0);
        }

        if ($this->status !== 'all') {
            $query->where('status', $this->status);
        }

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name', 'like', "%{$this->search}%")
                    ->orWhere('student_number', 'like', "%{$this->search}%");
            });
        }

        $students = $query->orderBy('last_name')->orderBy('first_name')->paginate(15);

        return view('livewire.students.index', ['students' => $students]);
    }
}
