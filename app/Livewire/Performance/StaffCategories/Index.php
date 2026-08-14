<?php

namespace App\Livewire\Performance\StaffCategories;

use App\Models\StaffCategory;
use App\Services\StaffCategoryService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The staff-category lookup table -- what a Performance Framework applies to.
 * Gated on the performance.* permissions directly rather than a dedicated
 * policy, the same way Position (another small foundation lookup table) is.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $description = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('performance.view') || Auth::user()->can('performance.manage'), 403);
    }

    public function startCreating(): void
    {
        abort_unless(Auth::user()->can('performance.manage'), 403);

        $this->reset(['editingId', 'code', 'name', 'description']);
        $this->showForm = true;
    }

    public function startEditing(int $id): void
    {
        abort_unless(Auth::user()->can('performance.manage'), 403);

        $category = StaffCategory::findOrFail($id);
        $this->editingId = $category->id;
        $this->code = $category->code;
        $this->name = $category->name;
        $this->description = $category->description ?? '';
        $this->showForm = true;
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->reset(['showForm', 'editingId', 'code', 'name', 'description']);
        $this->resetErrorBag();
    }

    public function save(StaffCategoryService $categories): void
    {
        abort_unless(Auth::user()->can('performance.manage'), 403);

        $validated = $this->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['description'] = $validated['description'] !== '' ? $validated['description'] : null;

        if ($this->editingId) {
            $categories->update(StaffCategory::findOrFail($this->editingId), $validated);
        } else {
            if (StaffCategory::where('code', $validated['code'])->exists()) {
                $this->addError('code', "\"{$validated['code']}\" is already in use.");

                return;
            }

            $categories->create($validated);
        }

        $this->reset(['showForm', 'editingId', 'code', 'name', 'description']);
    }

    public function delete(int $id, StaffCategoryService $categories): void
    {
        abort_unless(Auth::user()->can('performance.manage'), 403);

        $categories->delete(StaffCategory::findOrFail($id));
    }

    public function render()
    {
        return view('livewire.performance.staff-categories.index', [
            'categories' => StaffCategory::withCount(['staff', 'frameworks'])->orderBy('name')->get(),
            'canManage' => Auth::user()->can('performance.manage'),
        ]);
    }
}
