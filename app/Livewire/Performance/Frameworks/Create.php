<?php

namespace App\Livewire\Performance\Frameworks;

use App\Models\PerformanceFramework;
use App\Models\StaffCategory;
use App\Services\PerformanceFrameworkService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $staff_category_id = '';

    public string $name = '';

    public string $code = '';

    public string $version = '';

    public string $effective_from = '';

    public string $effective_to = '';

    public function mount(): void
    {
        $this->authorize('create', PerformanceFramework::class);
        $this->effective_from = now()->toDateString();
    }

    public function save(PerformanceFrameworkService $frameworks)
    {
        $this->authorize('create', PerformanceFramework::class);

        $validated = $this->validate([
            'staff_category_id' => ['required', 'exists:staff_categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50'],
            'version' => ['required', 'string', 'max:50'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ], [], ['effective_to' => 'effective to date']);

        if (PerformanceFramework::where('code', $validated['code'])->where('version', $validated['version'])->exists()) {
            $this->addError('version', "Version {$validated['version']} of {$validated['code']} already exists.");

            return null;
        }

        $framework = $frameworks->create(StaffCategory::findOrFail($validated['staff_category_id']), [
            'name' => $validated['name'],
            'code' => $validated['code'],
            'version' => $validated['version'],
            'effective_from' => $validated['effective_from'] !== '' ? $validated['effective_from'] : null,
            'effective_to' => $validated['effective_to'] !== '' ? $validated['effective_to'] : null,
        ]);

        session()->flash('status', "{$framework->name} was created as a draft.");

        return $this->redirect(route('performance.frameworks.show', $framework), navigate: true);
    }

    public function render()
    {
        return view('livewire.performance.frameworks.create', [
            'categories' => StaffCategory::orderBy('name')->get(),
        ]);
    }
}
