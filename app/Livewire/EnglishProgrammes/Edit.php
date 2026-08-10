<?php

namespace App\Livewire\EnglishProgrammes;

use App\Models\EnglishProgramme;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public EnglishProgramme $englishProgramme;

    public string $name = '';

    public string $code = '';

    public string $description = '';

    public string $status = '';

    public function mount(EnglishProgramme $englishProgramme): void
    {
        $this->authorize('update', $englishProgramme);
        $this->englishProgramme = $englishProgramme;
        $this->name = $englishProgramme->name;
        $this->code = $englishProgramme->code ?? '';
        $this->description = $englishProgramme->description ?? '';
        $this->status = $englishProgramme->status;
    }

    public function save()
    {
        $this->authorize('update', $this->englishProgramme);

        $validated = $this->validate([
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('english_programmes', 'name')->ignore($this->englishProgramme->id),
            ],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'archived'])],
        ]);

        $this->englishProgramme->update([
            'name' => $validated['name'],
            'code' => $validated['code'] !== '' ? $validated['code'] : null,
            'description' => $validated['description'] !== '' ? $validated['description'] : null,
            'status' => $validated['status'],
        ]);

        session()->flash('status', "{$this->englishProgramme->name} was updated.");

        return $this->redirect(route('english-programmes.show', $this->englishProgramme), navigate: true);
    }

    public function render()
    {
        return view('livewire.english-programmes.edit');
    }
}
