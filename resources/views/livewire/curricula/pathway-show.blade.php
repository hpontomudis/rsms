<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('curricula.scopes.show', [$curriculum, $scope]) }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; {{ $scope->displayName() }}</a>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
                <div class="mb-1 flex flex-wrap items-center gap-2">
                    @if ($pathway->code)<span class="text-xs text-slate-500">{{ $pathway->code }}</span>@endif
                    <x-status-badge :status="$pathway->status" />
                </div>
                <h1 class="font-serif text-xl font-bold text-brand-navy">{{ $pathway->title }}</h1>
                <p class="text-sm text-slate-500">
                    {{ $vocabulary['pathway'] }} &middot; {{ $scope->displayName() }} &middot; {{ $pathway->subject->name }}
                </p>
                @if ($pathway->description)
                    <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $pathway->description }}</p>
                @endif
            </div>

            @if ($canTransition)
                <div class="flex flex-shrink-0 gap-2">
                    @if ($pathway->isDraft())
                        <button type="button" wire:click="activate"
                            class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Activate</button>
                    @elseif ($pathway->isActive())
                        <button type="button" wire:click="archive"
                            wire:confirm="Archive this pathway? Anything already following it keeps working."
                            class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Archive</button>
                    @endif
                </div>
            @endif
        </div>

        @unless ($pathway->isDraft())
            <p class="mt-3 rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600 ring-1 ring-slate-200">
                This pathway is {{ $pathway->status }}, so its sequence is fixed. A change means a new pathway.
            </p>
        @endunless

        @error('items') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        @error('code') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        @error('status') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        @error('title') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-700">Sequence</h2>
            @if ($canEdit)
                <button type="button" wire:click="$toggle('showAddItem')" class="text-xs font-medium text-brand-navy hover:underline">+ Add {{ $vocabulary['objective'] }}</button>
            @endif
        </div>
        <p class="mb-3 text-xs text-slate-500">
            This order is the teaching sequence. It is independent of the library's reference order.
        </p>

        @if ($showAddItem)
            <form wire:submit="addItem" class="mb-4 space-y-3 rounded-lg bg-slate-50 p-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">{{ $vocabulary['objective'] }}</label>
                    <select wire:model="learning_objective_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        <option value="">Select&hellip;</option>
                        @foreach ($addableObjectives as $objective)
                            <option value="{{ $objective->id }}">
                                #{{ $objective->reference_order }} {{ $objective->code ? $objective->code.' — ' : '' }}{{ Str::limit($objective->objective_text, 60) }}
                            </option>
                        @endforeach
                    </select>
                    @error('learning_objective_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    @if ($addableObjectives->isEmpty())
                        <p class="mt-1 text-xs text-amber-700">Nothing left to add from this scope and subject.</p>
                    @endif
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Why here? (optional)</label>
                    <textarea wire:model="item_notes" rows="2" placeholder="Sequencing rationale — not a copy of the objective."
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Add</button>
                    <button type="button" wire:click="cancel" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-white">Cancel</button>
                </div>
            </form>
        @endif

        <ol class="space-y-3">
            @forelse ($items as $item)
                <li class="rounded-lg border border-slate-200 p-3">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="mb-0.5 flex flex-wrap items-center gap-2">
                                <span class="rounded bg-brand-navy px-1.5 py-0.5 text-xs font-medium text-white">{{ $item->position }}</span>
                                @if ($item->learningObjective->code)
                                    <span class="text-xs text-slate-500">{{ $item->learningObjective->code }}</span>
                                @endif
                                <span class="text-xs text-slate-400">library #{{ $item->learningObjective->reference_order }}</span>
                                <x-status-badge :status="$item->learningObjective->status" />
                            </div>
                            <p class="whitespace-pre-line text-sm text-slate-700">{{ $item->learningObjective->objective_text }}</p>
                            @if ($item->notes)
                                <p class="mt-1 whitespace-pre-line text-xs italic text-slate-500">{{ $item->notes }}</p>
                            @endif
                        </div>

                        @if ($canEdit)
                            <div class="flex flex-shrink-0 flex-wrap items-center gap-2 text-xs">
                                <button type="button" wire:click="moveItem({{ $item->id }}, 'up')" class="text-slate-400 hover:text-slate-700" title="Move up">&uarr;</button>
                                <button type="button" wire:click="moveItem({{ $item->id }}, 'down')" class="text-slate-400 hover:text-slate-700" title="Move down">&darr;</button>
                                <button type="button" wire:click="startEditingNotes({{ $item->id }})" class="font-medium text-brand-navy hover:underline">Notes</button>
                                <button type="button" wire:click="removeItem({{ $item->id }})"
                                    wire:confirm="Remove this objective from the sequence?" class="text-red-500 hover:text-red-700">Remove</button>
                            </div>
                        @endif
                    </div>

                    @if ($editingNotesId === $item->id)
                        <form wire:submit="saveNotes" class="mt-3 space-y-2 rounded-lg bg-slate-50 p-3">
                            <label class="block text-xs font-medium text-slate-600">Sequencing rationale</label>
                            <textarea wire:model="item_notes" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Save</button>
                                <button type="button" wire:click="cancel" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-white">Cancel</button>
                            </div>
                        </form>
                    @endif
                </li>
            @empty
                <li class="text-sm text-slate-500">Nothing sequenced yet.</li>
            @endforelse
        </ol>
    </div>

    <p class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
        Prota and Prosem are not implemented yet. A pathway says what order; when and by whom it is
        taught belongs to the next planning layer, and teaching assignments will select a pathway
        rather than own one.
    </p>
</div>
