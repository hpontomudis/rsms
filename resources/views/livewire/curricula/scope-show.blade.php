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


    {{-- Learning objectives (TP). Authored while the curriculum is draft OR
         active -- only an archived version closes the library. --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-700">{{ $vocabulary['objectives'] }}</h2>
            @if ($objectivesEditable)
                @can('create', \App\Models\LearningObjective::class)
                    <button type="button" wire:click="startAddingObjective" class="text-xs font-medium text-brand-navy hover:underline">+ Add</button>
                @endcan
            @endif
        </div>
        <p class="mb-3 text-xs text-slate-500">
            Reference order below is for the library only &mdash; the teaching sequence is decided later by ATP.
        </p>

        @if ($showAddObjective || $editingObjectiveId)
            <form wire:submit="saveObjective" class="mb-4 space-y-3 rounded-lg bg-slate-50 p-3">
                @unless ($editingObjectiveId)
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Subject</label>
                        <select wire:model="objective_subject_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                            <option value="">Select a subject&hellip;</option>
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                            @endforeach
                        </select>
                        @error('objective_subject_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-slate-500">Fixed once saved &mdash; an objective in the wrong place is deleted and rewritten.</p>
                    </div>
                @endunless

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Code (optional)</label>
                        <input type="text" wire:model="objective_code" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        @error('objective_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Title (optional)</label>
                        <input type="text" wire:model="objective_title" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        @error('objective_title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">{{ $vocabulary['objective'] }}</label>
                    <textarea wire:model="objective_text" rows="4" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
                    @error('objective_text') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-slate-500">State the competency expected and the content it applies to.</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Save Draft</button>
                    <button type="button" wire:click="cancelObjective" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-white">Cancel</button>
                </div>
                @error('curriculum') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </form>
        @endif

        @forelse ($objectives as $subjectName => $group)
            <div class="mb-4 last:mb-0">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $subjectName }}</p>
                <ul class="space-y-3">
                    @foreach ($group as $objective)
                        <li class="rounded-lg border border-slate-200 p-3">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="mb-0.5 flex flex-wrap items-center gap-2">
                                        <span class="text-xs text-slate-400">#{{ $objective->reference_order }}</span>
                                        @if ($objective->code)<span class="text-xs text-slate-500">{{ $objective->code }}</span>@endif
                                        <x-status-badge :status="$objective->status" />
                                    </div>
                                    @if ($objective->title)
                                        <p class="text-sm font-medium text-slate-900">{{ $objective->title }}</p>
                                    @endif
                                    <p class="whitespace-pre-line text-sm text-slate-700">{{ $objective->objective_text }}</p>

                                    {{-- Traceability: which outcomes this derives from. --}}
                                    <div class="mt-2">
                                        @forelse ($objective->outcomeLinks as $link)
                                            <span class="mb-1 mr-1 inline-block rounded bg-slate-50 px-2 py-0.5 text-xs text-slate-600 ring-1 ring-slate-200">
                                                {{ $link->learningOutcome->code ?? Str::limit($link->learningOutcome->outcome_text, 40) }}
                                                @if ($objective->isDraft() && $objectivesEditable)
                                                    @can('update', $objective)
                                                        <button type="button" wire:click="unlinkOutcome({{ $objective->id }}, {{ $link->learning_outcome_id }})"
                                                            class="ml-1 text-red-500 hover:text-red-700">&times;</button>
                                                    @endcan
                                                @endif
                                            </span>
                                        @empty
                                            <span class="text-xs text-amber-700">Not yet linked to any {{ $vocabulary['outcome'] }}.</span>
                                        @endforelse
                                    </div>
                                </div>

                                @if ($objectivesEditable)
                                    <div class="flex flex-shrink-0 flex-wrap items-center justify-end gap-2 text-xs">
                                        @if ($objective->isDraft())
                                            @can('update', $objective)
                                                <button type="button" wire:click="moveObjective({{ $objective->id }}, 'up')" class="text-slate-400 hover:text-slate-700" title="Move up">&uarr;</button>
                                                <button type="button" wire:click="moveObjective({{ $objective->id }}, 'down')" class="text-slate-400 hover:text-slate-700" title="Move down">&darr;</button>
                                                <button type="button" wire:click="startLinking({{ $objective->id }})" class="font-medium text-brand-navy hover:underline">Link {{ $vocabulary['outcome'] }}</button>
                                                <button type="button" wire:click="startEditingObjective({{ $objective->id }})" class="font-medium text-brand-navy hover:underline">Edit</button>
                                                <button type="button" wire:click="deleteObjective({{ $objective->id }})"
                                                    wire:confirm="Delete this draft objective?" class="text-red-500 hover:text-red-700">Delete</button>
                                            @endcan
                                            @can('transition', $objective)
                                                <button type="button" wire:click="activateObjective({{ $objective->id }})"
                                                    class="rounded-md bg-brand-navy px-3 py-1.5 font-medium text-white hover:bg-brand-navy-light">Activate</button>
                                            @endcan
                                        @elseif ($objective->isActive())
                                            @can('transition', $objective)
                                                <button type="button" wire:click="archiveObjective({{ $objective->id }})"
                                                    wire:confirm="Archive this objective? It stays readable and stays attached to anything that used it."
                                                    class="rounded-md border border-slate-300 px-3 py-1.5 text-slate-600 hover:bg-slate-50">Archive</button>
                                            @endcan
                                        @endif
                                    </div>
                                @endif
                            </div>

                            @if ($linkingObjectiveId === $objective->id)
                                <form wire:submit="linkOutcome" class="mt-3 flex flex-wrap items-end gap-2 rounded-lg bg-slate-50 p-3">
                                    <div class="min-w-0 flex-1">
                                        <label class="mb-1 block text-xs font-medium text-slate-600">{{ $vocabulary['outcome'] }}</label>
                                        <select wire:model="link_outcome_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                                            <option value="">Select&hellip;</option>
                                            @foreach ($linkableOutcomes as $outcome)
                                                <option value="{{ $outcome->id }}">{{ $outcome->code ? $outcome->code.' — ' : '' }}{{ Str::limit($outcome->outcome_text, 60) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Link</button>
                                    <button type="button" wire:click="cancelObjective" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-white">Cancel</button>
                                    @error('link_outcome_id') <p class="w-full text-xs text-red-600">{{ $message }}</p> @enderror
                                    @if ($linkableOutcomes->isEmpty())
                                        <p class="w-full text-xs text-amber-700">Every {{ $vocabulary['outcome'] }} in this scope and subject is already linked.</p>
                                    @endif
                                </form>
                            @endif

                            @error('status') @if ($editingObjectiveId === null) <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @endif @enderror
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="text-sm text-slate-500">No {{ $vocabulary['objectives'] }} recorded yet.</p>
        @endforelse

        @error('learning_outcome_id') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        @error('reference_order') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        @error('code') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>


    {{-- Learning pathways (ATP). Several may be active at once: they are
         alternative approved routes, not competing versions. --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-700">{{ $vocabulary['pathways'] }}</h2>
            @if ($objectivesEditable && $canPlan)
                <button type="button" wire:click="$toggle('showAddPathway')" class="text-xs font-medium text-brand-navy hover:underline">+ Add</button>
            @endif
        </div>
        <p class="mb-3 text-xs text-slate-500">
            An ordered route through this {{ $vocabulary['basis'] }}. More than one may be in force &mdash; they are alternatives, not versions.
        </p>

        @if ($showAddPathway)
            <form wire:submit="savePathway" class="mb-4 space-y-3 rounded-lg bg-slate-50 p-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Subject</label>
                    <select wire:model="pathway_subject_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        <option value="">Select a subject&hellip;</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                        @endforeach
                    </select>
                    @error('pathway_subject_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Code (optional)</label>
                        <input type="text" wire:model="pathway_code" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-slate-600">Title</label>
                        <input type="text" wire:model="pathway_title" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        @error('pathway_title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Description (optional)</label>
                    <textarea wire:model="pathway_description" rows="2" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Save Draft</button>
                    <button type="button" wire:click="cancelPathway" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-white">Cancel</button>
                </div>
                @error('curriculum') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                @error('pathway') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </form>
        @endif

        @forelse ($pathways as $subjectName => $group)
            <div class="mb-4 last:mb-0">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $subjectName }}</p>
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($group as $pathway)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <a href="{{ route('curricula.pathways.show', [$curriculum, $scope, $pathway]) }}" class="min-w-0 hover:underline">
                                <span class="font-medium text-slate-900">{{ $pathway->title }}</span>
                                @if ($pathway->code)<span class="ml-1 text-xs text-slate-500">{{ $pathway->code }}</span>@endif
                            </a>
                            <span class="flex flex-shrink-0 items-center gap-2 text-xs text-slate-500">
                                {{ $pathway->items_count }} {{ Str::plural('step', $pathway->items_count) }}
                                <x-status-badge :status="$pathway->status" />
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="text-sm text-slate-500">No {{ $vocabulary['pathways'] }} yet.</p>
        @endforelse
    </div>

    <p class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
        Prota and Prosem are not implemented yet, and curriculum standards are not
        yet linked to teaching assignments.
    </p>
</div>
