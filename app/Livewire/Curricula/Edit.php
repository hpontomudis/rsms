<?php

namespace App\Livewire\Curricula;

use App\Models\Curriculum;
use App\Models\EnglishProgramme;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public Curriculum $curriculum;

    public string $name = '';

    public string $code = '';

    public string $version = '';

    public string $description = '';

    public string $source_reference = '';

    public string $effective_from = '';

    public string $effective_to = '';

    public string $english_programme_id = '';

    public function mount(Curriculum $curriculum): void
    {
        $this->authorize('update', $curriculum);

        $this->curriculum = $curriculum;
        $this->name = $curriculum->name;
        $this->code = $curriculum->code;
        $this->version = $curriculum->version;
        $this->description = $curriculum->description ?? '';
        $this->source_reference = $curriculum->source_reference ?? '';
        $this->effective_from = $curriculum->effective_from->toDateString();
        $this->effective_to = $curriculum->effective_to?->toDateString() ?? '';
        $this->english_programme_id = (string) ($curriculum->english_programme_id ?? '');
    }

    /** Identity is only editable while the version has never been used. */
    public function identityEditable(): bool
    {
        return $this->curriculum->isDraft();
    }

    public function save()
    {
        $this->authorize('update', $this->curriculum);

        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'source_reference' => ['nullable', 'string', 'max:255'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];

        if ($this->identityEditable()) {
            $rules['code'] = ['required', 'string', 'max:50'];
            $rules['version'] = ['required', 'string', 'max:50'];
            $rules['english_programme_id'] = ['nullable', 'exists:english_programmes,id'];
        }

        $validated = $this->validate($rules, [], ['effective_to' => 'effective to date']);

        $changes = [
            'name' => $validated['name'],
            'description' => $validated['description'] !== '' ? $validated['description'] : null,
            'source_reference' => $validated['source_reference'] !== '' ? $validated['source_reference'] : null,
            'effective_from' => $validated['effective_from'],
            'effective_to' => $validated['effective_to'] !== '' ? $validated['effective_to'] : null,
        ];

        if ($this->identityEditable()) {
            $duplicate = Curriculum::where('code', $validated['code'])
                ->where('version', $validated['version'])
                ->whereKeyNot($this->curriculum->id)->exists();

            if ($duplicate) {
                $this->addError('version', "Version {$validated['version']} of {$validated['code']} already exists.");

                return null;
            }

            $changes['code'] = $validated['code'];
            $changes['version'] = $validated['version'];
            $changes['english_programme_id'] = $validated['english_programme_id'] !== ''
                ? $validated['english_programme_id'] : null;
        }

        $this->curriculum->update($changes);

        session()->flash('status', "{$this->curriculum->name} was updated.");

        return $this->redirect(route('curricula.show', $this->curriculum), navigate: true);
    }

    public function render()
    {
        return view('livewire.curricula.edit', [
            'programmes' => EnglishProgramme::active()->orderBy('name')->get(),
        ]);
    }
}
