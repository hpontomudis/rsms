<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('curricula.show', $curriculum) }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; {{ $curriculum->name }}</a>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="font-serif text-xl font-bold text-brand-navy">{{ $scope->displayName() }}</h1>
            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600">{{ $vocabulary['basis'] }}</span>
        </div>
        <p class="mt-1 text-sm text-slate-500">{{ $curriculum->name }} &middot; v{{ $curriculum->version }}</p>

        @if ($grades->isNotEmpty())
            {{-- Derived through the phase mapping. No grade is stored on an outcome. --}}
            <p class="mt-2 text-xs text-slate-500">
                Covers
                @foreach ($grades as $grade)<span class="text-slate-700">{{ $grade->name }}</span>@if (! $loop->last), @endif @endforeach
                &mdash; one set of outcomes across all of them.
            </p>
        @endif

        @unless ($editable)
            <p class="mt-3 rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600 ring-1 ring-slate-200">
                This curriculum version is {{ $curriculum->status }}, so its standards are read-only.
                Changes go into a new version.
            </p>
        @endunless
    </div>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-700">{{ $vocabulary['outcomes'] }}</h2>
            @if ($editable)
                @can('create', \App\Models\LearningOutcome::class)
                    <button type="button" wire:click="startAdding" class="text-xs font-medium text-brand-navy hover:underline">+ Add</button>
                @endcan
            @endif
        </div>

        @if ($showAddOutcome || $editingId)
            <form wire:submit="save" class="mb-4 space-y-3 rounded-lg bg-slate-50 p-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Subject</label>
                    <select wire:model="subject_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        <option value="">Select a subject&hellip;</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                        @endforeach
                    </select>
                    @error('subject_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Code (optional)</label>
                        <input type="text" wire:model="code" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Title (optional)</label>
                        <input type="text" wire:model="title" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">{{ $vocabulary['outcome'] }}</label>
                    <textarea wire:model="outcome_text" rows="5" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
                    @error('outcome_text') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Save</button>
                    <button type="button" wire:click="cancel" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-white">Cancel</button>
                </div>
                @error('curriculum') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </form>
        @endif

        @forelse ($outcomes as $subjectName => $group)
            <div class="mb-4 last:mb-0">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $subjectName }}</p>
                <ul class="space-y-3">
                    @foreach ($group as $outcome)
                        <li class="rounded-lg border border-slate-200 p-3">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    @if ($outcome->code || $outcome->title)
                                        <p class="text-sm font-medium text-slate-900">
                                            @if ($outcome->code)<span class="text-slate-500">{{ $outcome->code }}</span> @endif
                                            {{ $outcome->title }}
                                        </p>
                                    @endif
                                    <p class="whitespace-pre-line text-sm text-slate-700">{{ $outcome->outcome_text }}</p>
                                </div>

                                @if ($editable)
                                    <div class="flex flex-shrink-0 items-center gap-2 text-xs">
                                        <button type="button" wire:click="move({{ $outcome->id }}, 'up')" class="text-slate-400 hover:text-slate-700" title="Move up">&uarr;</button>
                                        <button type="button" wire:click="move({{ $outcome->id }}, 'down')" class="text-slate-400 hover:text-slate-700" title="Move down">&darr;</button>
                                        <button type="button" wire:click="startEditing({{ $outcome->id }})" class="font-medium text-brand-navy hover:underline">Edit</button>
                                        <button type="button" wire:click="remove({{ $outcome->id }})"
                                            wire:confirm="Remove this outcome? Only possible while the curriculum is a draft."
                                            class="text-red-500 hover:text-red-700">Remove</button>
                                    </div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="text-sm text-slate-500">Nothing recorded for this {{ strtolower($vocabulary['basis']) }} yet.</p>
        @endforelse
    </div>

    <p class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
        Learning objectives (TP) and ATP are not implemented yet, and curriculum standards are not
        yet linked to teaching assignments.
    </p>
</div>
