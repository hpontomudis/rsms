<?php

namespace App\Livewire\Curricula;

use App\Models\Curriculum;
use App\Models\EnglishProgramme;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $name = '';

    public string $code = '';

    public string $version = '';

    public string $description = '';

    public string $source_reference = '';

    public string $effective_from = '';

    public string $effective_to = '';

    public string $english_programme_id = '';

    public function mount(): void
    {
        $this->authorize('create', Curriculum::class);
        $this->effective_from = now()->toDateString();
    }

    public function save()
    {
        $this->authorize('create', Curriculum::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50'],
            'version' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            'source_reference' => ['nullable', 'string', 'max:255'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'english_programme_id' => ['nullable', 'exists:english_programmes,id'],
        ], [], ['effective_to' => 'effective to date']);

        // code + version is the version's identity, and the database enforces
        // it; checking here turns a 500 into a field error.
        $exists = Curriculum::where('code', $validated['code'])
            ->where('version', $validated['version'])->exists();

        if ($exists) {
            $this->addError('version', "Version {$validated['version']} of {$validated['code']} already exists.");

            return null;
        }

        // Created as a draft: a version becomes active deliberately, on its
        // own screen, because activating supersedes whatever came before.
        $curriculum = Curriculum::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'version' => $validated['version'],
            'description' => $validated['description'] !== '' ? $validated['description'] : null,
            'source_reference' => $validated['source_reference'] !== '' ? $validated['source_reference'] : null,
            'effective_from' => $validated['effective_from'],
            'effective_to' => $validated['effective_to'] !== '' ? $validated['effective_to'] : null,
            'english_programme_id' => $validated['english_programme_id'] !== '' ? $validated['english_programme_id'] : null,
            'status' => 'draft',
        ]);

        session()->flash('status', "{$curriculum->name} was created as a draft.");

        return $this->redirect(route('curricula.show', $curriculum), navigate: true);
    }

    public function render()
    {
        return view('livewire.curricula.create', [
            'programmes' => EnglishProgramme::active()->orderBy('name')->get(),
        ]);
    }
}
