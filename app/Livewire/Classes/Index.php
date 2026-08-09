<?php

namespace App\Livewire\Classes;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
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

    public function mount(): void
    {
        $this->authorize('viewAny', SchoolClass::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $user = Auth::user();

        $query = SchoolClass::query()->with(['grade', 'academicYear']);

        if ($user->hasRole('teacher')) {
            $query->taughtBy($user->staff?->id ?? 0);
        }

        if ($this->search !== '') {
            $query->where('name', 'like', "%{$this->search}%");
        }

        $classes = $query
            ->orderByDesc('academic_year_id')
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.classes.index', [
            'classes' => $classes,
            'currentAcademicYear' => AcademicYear::current(),
        ]);
    }
}
