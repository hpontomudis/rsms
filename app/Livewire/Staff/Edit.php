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

    public string $position_title = '';

    public string $phone = '';

    public string $email = '';

    public string $nik = '';

    public string $hire_date = '';

    public string $status = '';

    public function mount(Staff $staff): void
    {
        $this->authorize('update', $staff);
        $this->staff = $staff;
        $this->staff_number = $staff->staff_number;
        $this->first_name = $staff->first_name;
        $this->last_name = $staff->last_name;
        $this->position_title = $staff->position->title;
        $this->phone = $staff->phone;
        $this->email = $staff->email ?? '';
        $this->nik = $staff->nik ?? '';
        $this->hire_date = $staff->hire_date->toDateString();
        $this->status = $staff->status;
    }

    public function save()
    {
        $this->authorize('update', $this->staff);

        $this->nik = trim($this->nik);

        $validated = $this->validate([
            'staff_number' => ['required', 'string', 'max:50', Rule::unique('staff', 'staff_number')->ignore($this->staff->id)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'position_title' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'nik' => ['nullable', 'digits:16', Rule::unique('staff', 'nik')->ignore($this->staff->id)],
            'hire_date' => ['required', 'date'],
            'status' => ['required', Rule::in(['active', 'on_leave', 'terminated'])],
        ]);

        $validated['nik'] = $validated['nik'] !== '' ? $validated['nik'] : null;

        $position = Position::firstOrCreate(['title' => trim($validated['position_title'])]);

        unset($validated['position_title']);
        $validated['position_id'] = $position->id;

        $this->staff->update($validated);

        session()->flash('status', "{$this->staff->fullName()} was updated.");

        return $this->redirect(route('staff.show', $this->staff), navigate: true);
    }

    public function render()
    {
        return view('livewire.staff.edit', ['positions' => Position::orderBy('title')->get()]);
    }
}
