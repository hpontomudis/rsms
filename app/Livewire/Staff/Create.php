<?php

namespace App\Livewire\Staff;

use App\Models\Position;
use App\Models\Staff;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $staff_number = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $position_title = '';

    public string $phone = '';

    public string $email = '';

    public string $hire_date = '';

    public function mount(): void
    {
        $this->authorize('create', Staff::class);
        $this->hire_date = now()->toDateString();
    }

    public function save()
    {
        $this->authorize('create', Staff::class);

        $validated = $this->validate([
            'staff_number' => ['required', 'string', 'max:50', Rule::unique('staff', 'staff_number')],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'position_title' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'hire_date' => ['required', 'date'],
        ]);

        // Picking an existing position from the datalist re-uses its row;
        // typing a title that doesn't exist yet creates it on the fly.
        $position = Position::firstOrCreate(['title' => trim($validated['position_title'])]);

        unset($validated['position_title']);
        $validated['position_id'] = $position->id;

        $staff = Staff::create($validated);

        session()->flash('status', "{$staff->fullName()} was added.");

        return $this->redirect(route('staff.show', $staff), navigate: true);
    }

    public function render()
    {
        return view('livewire.staff.create', ['positions' => Position::orderBy('title')->get()]);
    }
}
