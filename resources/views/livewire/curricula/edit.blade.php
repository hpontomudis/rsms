@php $identityEditable = $this->identityEditable(); @endphp
<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('curricula.show', $curriculum) }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; {{ $curriculum->name }}</a>
    <h1 class="font-serif text-xl font-bold text-brand-navy">Edit Curriculum</h1>

    @unless ($identityEditable)
        <p class="rounded-md bg-slate-50 px-4 py-3 text-sm text-slate-600 ring-1 ring-slate-200">
            This version has left draft, so its code, version and English programme binding are fixed.
            Records point at it; changing them would rewrite what those records were taught against.
            Create a new version instead.
        </p>
    @endunless

    <form wire:submit="save" class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
            <input type="text" wire:model="name" placeholder="e.g. National Curriculum"
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
            @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            <p class="mt-1 text-xs text-slate-500">Presentation only &mdash; the version's identity is its code and version.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Code</label>
                <input type="text" wire:model="code" placeholder="e.g. NATIONAL" @disabled(! $identityEditable)
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy @if(! $identityEditable) bg-slate-50 @endif">
                @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Version</label>
                <input type="text" wire:model="version" placeholder="e.g. 2026" @disabled(! $identityEditable)
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy @if(! $identityEditable) bg-slate-50 @endif">
                @error('version') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">English Programme (optional)</label>
            <select wire:model="english_programme_id" @disabled(! $identityEditable)
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy @if(! $identityEditable) bg-slate-50 @endif">
                <option value="">None &mdash; national, phase-based curriculum</option>
                @foreach ($programmes as $programme)
                    <option value="{{ $programme->id }}">{{ $programme->name }}</option>
                @endforeach
            </select>
            @error('english_programme_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            <p class="mt-1 text-xs text-slate-500">
                Leave empty for the national curriculum, which is scoped by Learning Phase.
                Choosing a programme marks this as a Rahai English curriculum.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Effective From</label>
                <input type="date" wire:model="effective_from"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                @error('effective_from') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Effective To (optional)</label>
                <input type="date" wire:model="effective_to"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                @error('effective_to') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Source Reference (optional)</label>
            <input type="text" wire:model="source_reference" placeholder="e.g. a regulation or internal approval reference"
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
            @error('source_reference') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Description (optional)</label>
            <textarea wire:model="description" rows="3"
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
            @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-wrap justify-end gap-3 pt-2">
            <a href="{{ route('curricula.show', $curriculum) }}" class="rounded-md border border-slate-300 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50">Cancel</a>
            <button type="submit" class="rounded-md bg-brand-navy px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-navy-light">Save Changes</button>
        </div>
    </form>
</div>
