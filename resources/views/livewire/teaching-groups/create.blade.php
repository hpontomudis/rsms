<div class="mx-auto max-w-lg space-y-4">
    <a href="{{ route('teaching-groups.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Teaching Groups</a>

    <form wire:submit="save" class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h1 class="font-serif text-xl font-bold text-brand-navy">New Teaching Group</h1>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Academic Year</label>
            <select wire:model="academic_year_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                <option value="">Select a year&hellip;</option>
                @foreach ($academicYears as $year)
                    <option value="{{ $year->id }}">{{ $year->name }}</option>
                @endforeach
            </select>
            @error('academic_year_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Group Name</label>
            <input type="text" wire:model="name" placeholder="e.g. Green A" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
            @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">English Proficiency Level (optional)</label>
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
            <p class="mt-1 text-xs text-slate-500">
                Choosing a level makes this an English proficiency group: only students whose grade is covered by that programme may join.
            </p>
            @error('english_level_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex gap-2">
            <button type="submit" class="rounded-md bg-brand-navy px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-navy-light">Create Group</button>
            <a href="{{ route('teaching-groups.index') }}" class="rounded-md border border-slate-300 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50">Cancel</a>
        </div>
    </form>
</div>
