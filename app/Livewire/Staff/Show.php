<?php

namespace App\Livewire\Staff;

use App\Models\Staff;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Staff $staff;

    public function mount(Staff $staff): void
    {
        $this->authorize('view', $staff);
        $this->staff = $staff;
    }

    public function archive()
    {
        $this->authorize('delete', $this->staff);
        $this->staff->delete();

        session()->flash('status', "{$this->staff->fullName()} was archived.");

        return $this->redirect(route('staff.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.staff.show', [
            'classes' => $this->staff->classes()->with('grade', 'academicYear')->get(),
        ]);
    }
}
