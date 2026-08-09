<?php

namespace App\Livewire\Guardians;

use App\Models\Guardian;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $first_name = '';

    public string $last_name = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $occupation = '';

    public function mount(): void
    {
        $this->authorize('create', Guardian::class);
    }

    public function save()
    {
        $this->authorize('create', Guardian::class);

        $validated = $this->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'occupation' => ['nullable', 'string', 'max:100'],
        ]);

        $guardian = Guardian::create($validated);

        session()->flash('status', "{$guardian->fullName()} was added.");

        return $this->redirect(route('guardians.show', $guardian), navigate: true);
    }

    public function render()
    {
        return view('livewire.guardians.create');
    }
}
