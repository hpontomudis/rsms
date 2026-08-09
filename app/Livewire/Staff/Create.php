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

    public string $position_id = '';

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
            'position_id' => ['required', 'exists:positions,id'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'hire_date' => ['required', 'date'],
        ]);

        $staff = Staff::create($validated);

        session()->flash('status', "{$staff->fullName()} was added.");

        return $this->redirect(route('staff.show', $staff), navigate: true);
    }

    public function render()
    {
        return view('livewire.staff.create', ['positions' => Position::orderBy('title')->get()]);
    }
}
