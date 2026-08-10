<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('english-programmes.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; English Programmes</a>

    <div class="flex flex-col gap-3 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="font-serif text-xl font-bold text-brand-navy">{{ $englishProgramme->name }}</h1>
            @if ($englishProgramme->code)
                <p class="text-sm text-slate-500">{{ $englishProgramme->code }}</p>
            @endif
            <div class="mt-2"><x-status-badge :status="$englishProgramme->status" /></div>
        </div>
        @can('update', $englishProgramme)
            <a href="{{ route('english-programmes.edit', $englishProgramme) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Edit</a>
        @endcan
    </div>

    @if ($englishProgramme->description)
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-sm text-slate-600">{{ $englishProgramme->description }}</p>
        </div>
    @endif

    {{-- Proficiency levels --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700">Proficiency Levels</h2>
            @can('update', $englishProgramme)
                <button type="button" wire:click="$toggle('showAddLevel')" class="text-sm font-medium text-slate-600 hover:text-slate-900">+ Add Level</button>
            @endcan
        </div>

        @if ($showAddLevel)
            <form wire:submit="addLevel" class="mb-4 flex flex-col gap-3 rounded-lg bg-slate-50 p-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label class="mb-1 block text-xs font-medium text-slate-600">Level Name</label>
                    <input type="text" wire:model="level_name" placeholder="e.g. Green" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    @error('level_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:w-32">
                    <label class="mb-1 block text-xs font-medium text-slate-600">Code (optional)</label>
                    <input type="text" wire:model="level_code" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    @error('level_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Add</button>
            </form>
        @endif

        <ul class="divide-y divide-slate-100 text-sm">
            @forelse ($levels as $level)
                <li class="flex items-center justify-between gap-2 py-2">
                    <div class="flex min-w-0 items-center gap-2">
                        <span class="w-6 flex-shrink-0 text-xs text-slate-400">{{ $level->sequence }}</span>
                        <span class="truncate {{ $level->isActive() ? 'font-medium text-slate-900' : 'text-slate-400 line-through' }}">
                            {{ $level->name }}
                        </span>
                        @if ($level->code)
                            <span class="flex-shrink-0 text-xs text-slate-400">{{ $level->code }}</span>
                        @endif
                    </div>

                    @can('update', $englishProgramme)
                        <div class="flex flex-shrink-0 items-center gap-1">
                            <button type="button" wire:click="moveLevel({{ $level->id }}, 'up')"
                                @disabled($loop->first)
                                class="rounded px-2 py-1 text-xs text-slate-500 hover:bg-slate-100 disabled:opacity-30" aria-label="Move up">&uarr;</button>
                            <button type="button" wire:click="moveLevel({{ $level->id }}, 'down')"
                                @disabled($loop->last)
                                class="rounded px-2 py-1 text-xs text-slate-500 hover:bg-slate-100 disabled:opacity-30" aria-label="Move down">&darr;</button>
                            <button type="button" wire:click="toggleLevelStatus({{ $level->id }})"
                                class="rounded px-2 py-1 text-xs {{ $level->isActive() ? 'text-red-500 hover:text-red-700' : 'text-emerald-600 hover:text-emerald-800' }}">
                                {{ $level->isActive() ? 'Archive' : 'Restore' }}
                            </button>
                        </div>
                    @endcan
                </li>
            @empty
                <li class="py-2 text-slate-500">No levels defined yet.</li>
            @endforelse
        </ul>
        <p class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-400">
            Levels are archived rather than deleted &mdash; a level stays valid reference data even with no students at it.
        </p>
    </div>

    {{-- Grade applicability --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700">Applies to Grades</h2>
            @can('update', $englishProgramme)
                @if ($availableGrades->isNotEmpty())
                    <button type="button" wire:click="$toggle('showLinkGrade')" class="text-sm font-medium text-slate-600 hover:text-slate-900">+ Add Grade</button>
                @endif
            @endcan
        </div>

        @if ($showLinkGrade)
            <form wire:submit="linkGrade" class="mb-4 flex flex-col gap-3 rounded-lg bg-slate-50 p-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label class="mb-1 block text-xs font-medium text-slate-600">Grade</label>
                    <select wire:model="grade_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        <option value="">Select a grade&hellip;</option>
                        @foreach ($availableGrades as $grade)
                            <option value="{{ $grade->id }}">{{ $grade->name }}</option>
                        @endforeach
                    </select>
                    @error('grade_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-slate-500">Only grades not already in another programme are listed.</p>
                </div>
                <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Add</button>
            </form>
        @endif

        <ul class="divide-y divide-slate-100 text-sm">
            @forelse ($linkedGrades as $link)
                <li class="flex items-center justify-between py-2">
                    <span class="font-medium text-slate-900">{{ $link->grade->name }}</span>
                    @can('update', $englishProgramme)
                        <button type="button" wire:click="unlinkGrade({{ $link->grade_id }})"
                            wire:confirm="Remove {{ $link->grade->name }} from {{ $englishProgramme->name }}?"
                            class="text-xs text-red-500 hover:text-red-700">Remove</button>
                    @endcan
                </li>
            @empty
                <li class="py-2 text-slate-500">This programme is not applied to any grade yet.</li>
            @endforelse
        </ul>
    </div>
</div>
