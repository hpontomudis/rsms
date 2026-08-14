<?php

namespace App\Livewire\Performance\Frameworks;

use App\Models\PerformanceFramework;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', PerformanceFramework::class);
    }

    public function render()
    {
        return view('livewire.performance.frameworks.index', [
            // Grouped by category so a category's framework versions read as
            // one history, the same way Curricula groups by code.
            'byCategory' => PerformanceFramework::with('staffCategory')
                ->withCount(['evaluations'])
                ->orderByDesc('effective_from')
                ->get()
                ->groupBy(fn ($framework) => $framework->staffCategory->name),
            'canManage' => auth()->user()->can('create', PerformanceFramework::class),
        ]);
    }
}
