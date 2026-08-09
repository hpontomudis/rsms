<?php

namespace App\Livewire\Guardians;

use App\Models\Guardian;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public Guardian $guardian;

    public string $first_name = '';

    public string $last_name = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $occupation = '';

    public function mount(Guardian $guardian): void
    {
        $this->authorize('update', $guardian);
        $this->guardian = $guardian;
        $this->first_name = $guardian->first_name;
        $this->last_name = $guardian->last_name;
        $this->phone = $guardian->phone;
        $this->email = $guardian->email ?? '';
        $this->address = $guardian->address ?? '';
        $this->occupation = $guardian->occupation ?? '';
    }

    public function save()
    {
        $this->authorize('update', $this->guardian);

        $validated = $this->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'occupation' => ['nullable', 'string', 'max:100'],
        ]);

        $this->guardian->update($validated);

        session()->flash('status', "{$this->guardian->fullName()} was updated.");

        return $this->redirect(route('guardians.show', $this->guardian), navigate: true);
    }

    public function render()
    {
        return view('livewire.guardians.edit');
    }
}
