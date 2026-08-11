<div class="mx-auto max-w-2xl space-y-4">
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h1 class="font-serif text-xl font-bold text-brand-navy">Learning Phases</h1>
        <p class="mt-1 text-sm text-slate-500">
            The national phase structure (Fase). Learning outcomes will be defined per phase, not per
            grade &mdash; Phase C covers Year 5 and Year 6 with one set of outcomes.
        </p>
    </div>

    <div class="space-y-3">
        @foreach ($phases as $phase)
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-serif text-lg font-bold text-brand-navy">{{ $phase->name }}</span>
                            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600">{{ $phase->code }}</span>
                            @if (! $phase->isActive())
                                <x-status-badge :status="$phase->status" />
                            @endif
                        </div>

                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @forelse ($phase->gradeLinks->sortBy(fn ($link) => $link->grade->level_order) as $link)
                                <span class="rounded bg-slate-50 px-2 py-0.5 text-xs text-slate-700 ring-1 ring-slate-200">
                                    {{ $link->grade->name }}
                                </span>
                            @empty
                                <span class="text-xs text-slate-400">No grades mapped.</span>
                            @endforelse
                        </div>

                        @if ($phase->description)
                            <p class="mt-2 text-sm text-slate-600">{{ $phase->description }}</p>
                        @endif
                    </div>

                    @can('update', $phase)
                        <button type="button" wire:click="startEditing({{ $phase->id }})"
                            class="flex-shrink-0 text-xs font-medium text-brand-navy hover:underline">Edit</button>
                    @endcan
                </div>

                @if ($editingId === $phase->id)
                    <form wire:submit="save" class="mt-3 space-y-3 rounded-lg bg-slate-50 p-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Description (optional)</label>
                            <textarea wire:model="description" rows="2"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
                            @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex flex-wrap items-end gap-3">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Status</label>
                                <select wire:model="status" class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                                    <option value="active">Active</option>
                                    <option value="archived">Archived</option>
                                </select>
                            </div>
                            <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Save</button>
                            <button type="button" wire:click="cancelEditing" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-white">Cancel</button>
                        </div>
                        <p class="text-xs text-slate-500">
                            Codes, sequences and grade mappings are national structure and are not editable here.
                        </p>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
</div>
