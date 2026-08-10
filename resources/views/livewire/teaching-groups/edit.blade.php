<div class="mx-auto max-w-lg space-y-4">
    <a href="{{ route('teaching-groups.show', $teachingGroup) }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; {{ $teachingGroup->name }}</a>

    <form wire:submit="save" class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div>
            <h1 class="font-serif text-xl font-bold text-brand-navy">Edit Teaching Group</h1>
            <p class="text-sm text-slate-500">{{ $teachingGroup->academicYear->name }}</p>
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Group Name</label>
            <input type="text" wire:model="name" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
            @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">English Proficiency Level</label>
            @if ($levelEditable)
                <select wire:model="english_level_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    <option value="">No level &mdash; general group</option>
                    @foreach ($programmes as $programme)
                        <optgroup label="{{ $programme->name }}">
                            @foreach ($programme->levels as $level)
                                <option value="{{ $level->id }}">{{ $level->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('english_level_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            @else
                <p class="rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-600">
                    {{ $teachingGroup->englishLevel?->name ?? 'No level — general group' }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    The level is locked once the group has had members: changing it would rewrite what this group was and who was eligible to be in it. Archive this group and create a new one instead.
                </p>
            @endif
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Status</label>
            <select wire:model="status" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                <option value="active">Active</option>
                <option value="archived">Archived</option>
            </select>
            <p class="mt-1 text-xs text-slate-500">Archived groups keep their history but cannot take new students.</p>
            @error('status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex gap-2">
            <button type="submit" class="rounded-md bg-brand-navy px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-navy-light">Save</button>
            <a href="{{ route('teaching-groups.show', $teachingGroup) }}" class="rounded-md border border-slate-300 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50">Cancel</a>
        </div>
    </form>
</div>
