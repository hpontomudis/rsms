<?php

namespace App\Livewire\EnglishProgrammes;

use App\Models\EnglishProgramme;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $name = '';

    public string $code = '';

    public string $description = '';

    public function mount(): void
    {
        $this->authorize('create', EnglishProgramme::class);
    }

    public function save()
    {
        $this->authorize('create', EnglishProgramme::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('english_programmes', 'name')],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $programme = EnglishProgramme::create([
            'name' => $validated['name'],
            'code' => $validated['code'] !== '' ? $validated['code'] : null,
            'description' => $validated['description'] !== '' ? $validated['description'] : null,
            'status' => 'active',
        ]);

        session()->flash('status', "{$programme->name} was created. Add its proficiency levels below.");

        return $this->redirect(route('english-programmes.show', $programme), navigate: true);
    }

    public function render()
    {
        return view('livewire.english-programmes.create');
    }
}
