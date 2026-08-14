<?php

namespace App\Livewire\Performance\Evaluations;

use App\Models\PerformanceEvaluation;
use App\Models\Staff;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A staff member's own finalized evaluations. Uses the exact same exclusive-
 * login rule as PerformanceEvaluationPolicy's self-view carve-out, in the
 * other direction: given the logged-in User, is there exactly one Staff row
 * that is unambiguously theirs? If their login is shared, this shows an
 * empty state rather than guessing which Staff row to list.
 */
#[Layout('layouts.app')]
class MyEvaluations extends Component
{
    public function render()
    {
        $user = Auth::user();
        $matches = Staff::where('user_id', $user->id)->get();
        $me = $matches->count() === 1 ? $matches->first() : null;

        return view('livewire.performance.evaluations.my-evaluations', [
            'me' => $me,
            'ambiguous' => $matches->count() > 1,
            'evaluations' => $me
                ? PerformanceEvaluation::where('staff_id', $me->id)->where('status', 'finalized')
                    ->with('framework')->orderByDesc('period_start')->get()
                : collect(),
        ]);
    }
}
