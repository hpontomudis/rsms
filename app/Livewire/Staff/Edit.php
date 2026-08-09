<?php

namespace App\Livewire\Staff;

use App\Models\Position;
use App\Models\Staff;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public Staff $staff;

    public string $staff_number = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $position_id = '';

    public string $phone = '';

    public string $email = '';

    public string $hire_date = '';

    public string $status = '';

    public function mount(Staff $staff): void
    {
        $this->authorize('update', $staff);
        $this->staff = $staff;
        $this->staff_number = $staff->staff_number;
        $this->first_name = $staff->first_name;
        $this->last_name = $staff->last_name;
        $this->position_id = (string) $staff->position_id;
        $this->phone = $staff->phone;
        $this->email = $staff->email ?? '';
        $this->hire_date = $staff->hire_date->toDateString();
        $this->status = $staff->status;
    }

    public function save()
    {
        $this->authorize('update', $this->staff);

        $validated = $this->validate([
            'staff_number' => ['required', 'string', 'max:50', Rule::unique('staff', 'staff_number')->ignore($this->staff->id)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'position_id' => ['required', 'exists:positions,id'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'hire_date' => ['required', 'date'],
            'status' => ['required', Rule::in(['active', 'on_leave', 'terminated'])],
        ]);

        $this->staff->update($validated);

        session()->flash('status', "{$this->staff->fullName()} was updated.");

        return $this->redirect(route('staff.show', $this->staff), navigate: true);
    }

    public function render()
    {
        return view('livewire.staff.edit', ['positions' => Position::orderBy('title')->get()]);
    }
}
