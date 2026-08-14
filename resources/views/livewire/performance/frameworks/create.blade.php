<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('performance.frameworks.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Performance Frameworks</a>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h1 class="mb-4 font-serif text-xl font-bold text-brand-navy">New Performance Framework</h1>

        <form wire:submit="save" class="space-y-4">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Staff category</label>
                <select wire:model="staff_category_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    <option value="">Select&hellip;</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('staff_category_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Name</label>
                <input type="text" wire:model="name" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy" />
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Code</label>
                    <input type="text" wire:model="code" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy" />
                    @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Version</label>
                    <input type="text" wire:model="version" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy" />
                    @error('version') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Effective from</label>
                    <input type="date" wire:model="effective_from" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy" />
                    @error('effective_from') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Effective to (optional)</label>
                    <input type="date" wire:model="effective_to" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy" />
                    @error('effective_to') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <p class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
                Created as a draft. Add sections, indicators and rating options, then activate it explicitly &mdash;
                activating freezes the structure and puts it into force.
            </p>

            <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Create draft</button>
        </form>
    </div>
</div>
