<?php

namespace App\Livewire\Classes;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Services\AcademicYearService;
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

    public string $switch_academic_year_id = '';

    public function mount(): void
    {
        $this->authorize('viewAny', SchoolClass::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * The minimal admin action for Foundation F1: change which Academic
     * Year is current. Gated on academic-years.manage -- the existing
     * Foundation permission already scoped to school-administration roles,
     * not reused from classes.view (which every teacher also holds).
     * Delegates entirely to AcademicYearService::setCurrent(), the one
     * canonical write path for is_current.
     */
    public function switchCurrentAcademicYear(AcademicYearService $service): void
    {
        abort_unless(Auth::user()?->can('academic-years.manage'), 403);

        $validated = $this->validate([
            'switch_academic_year_id' => ['required', 'exists:academic_years,id'],
        ]);

        $year = AcademicYear::findOrFail($validated['switch_academic_year_id']);
        $service->setCurrent($year);

        $this->switch_academic_year_id = '';
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
            'canManageAcademicYears' => (bool) $user?->can('academic-years.manage'),
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(),
        ]);
    }
}
