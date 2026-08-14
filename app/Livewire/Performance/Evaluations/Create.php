<?php

namespace App\Livewire\Performance\Evaluations;

use App\Models\AcademicYear;
use App\Models\PerformanceEvaluation;
use App\Models\PerformanceFramework;
use App\Models\Staff;
use App\Services\PerformanceEvaluationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** The creating user (performance.manage, i.e. the principal) is always the evaluator -- there is no separate picker. */
#[Layout('layouts.app')]
class Create extends Component
{
    public string $staff_id = '';

    public string $performance_framework_id = '';

    public string $period_start = '';

    public string $period_end = '';

    public string $academic_year_id = '';

    public function mount(): void
    {
        $this->authorize('create', PerformanceEvaluation::class);
    }

    public function updatedStaffId(): void
    {
        $this->performance_framework_id = '';
    }

    public function save(PerformanceEvaluationService $evaluations)
    {
        $this->authorize('create', PerformanceEvaluation::class);

        $validated = $this->validate([
            'staff_id' => ['required', 'exists:staff,id'],
            'performance_framework_id' => ['required', 'exists:performance_frameworks,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'academic_year_id' => ['nullable', 'exists:academic_years,id'],
        ]);

        $evaluation = $evaluations->create(
            Staff::findOrFail($validated['staff_id']),
            PerformanceFramework::findOrFail($validated['performance_framework_id']),
            Auth::user(),
            $validated['period_start'],
            $validated['period_end'],
            $validated['academic_year_id'] !== '' ? AcademicYear::findOrFail($validated['academic_year_id']) : null,
        );

        session()->flash('status', 'Evaluation created as a draft.');

        return $this->redirect(route('performance.evaluations.show', $evaluation), navigate: true);
    }

    public function render()
    {
        $staff = Staff::whereNotNull('staff_category_id')->orderBy('first_name')->orderBy('last_name')->get();

        $frameworks = collect();
        if ($this->staff_id !== '') {
            $selected = $staff->firstWhere('id', (int) $this->staff_id);
            if ($selected) {
                $frameworks = PerformanceFramework::where('staff_category_id', $selected->staff_category_id)
                    ->where('status', 'active')
                    ->orderByDesc('effective_from')
                    ->get();
            }
        }

        return view('livewire.performance.evaluations.create', [
            'staffOptions' => $staff,
            'frameworks' => $frameworks,
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(),
        ]);
    }
}
