<?php

namespace App\Livewire\TeachingGroups;

use App\Models\EnglishProgramme;
use App\Models\TeachingGroup;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public TeachingGroup $teachingGroup;

    public string $name = '';

    public string $english_level_id = '';

    public string $status = '';

    public function mount(TeachingGroup $teachingGroup): void
    {
        $this->authorize('update', $teachingGroup);
        $this->teachingGroup = $teachingGroup;
        $this->name = $teachingGroup->name;
        $this->english_level_id = (string) ($teachingGroup->english_level_id ?? '');
        $this->status = $teachingGroup->status;
    }

    /**
     * The English level may only be changed while the group has never had a
     * member. Once anyone has been taught here -- currently or historically --
     * changing the level would rewrite what the group was, and retroactively
     * change who was eligible to be in it.
     */
    public function levelIsEditable(): bool
    {
        return ! $this->teachingGroup->memberships()->exists();
    }

    public function save()
    {
        $this->authorize('update', $this->teachingGroup);

        $rules = [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('teaching_groups', 'name')
                    ->where('academic_year_id', $this->teachingGroup->academic_year_id)
                    ->ignore($this->teachingGroup->id),
            ],
            'status' => ['required', Rule::in(['active', 'archived'])],
        ];

        $data = ['name' => $this->name, 'status' => $this->status];

        if ($this->levelIsEditable()) {
            $rules['english_level_id'] = ['nullable', 'exists:english_levels,id'];
            $data['english_level_id'] = $this->english_level_id !== '' ? $this->english_level_id : null;
        }

        $validated = validator($data, $rules, [
            'name.unique' => 'A group with that name already exists in this academic year.',
        ])->validate();

        $this->teachingGroup->update($validated);

        session()->flash('status', "{$this->teachingGroup->name} was updated.");

        return $this->redirect(route('teaching-groups.show', $this->teachingGroup), navigate: true);
    }

    public function render()
    {
        return view('livewire.teaching-groups.edit', [
            'programmes' => EnglishProgramme::with('levels')->orderBy('name')->get(),
            'levelEditable' => $this->levelIsEditable(),
        ]);
    }
}
