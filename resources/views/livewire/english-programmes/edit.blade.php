<div class="mx-auto max-w-xl space-y-4">
    <a href="{{ route('english-programmes.show', $englishProgramme) }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; {{ $englishProgramme->name }}</a>
    <h1 class="font-serif text-xl font-bold text-brand-navy">Edit English Programme</h1>

    <form wire:submit="save" class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
            <input type="text" wire:model="name" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Code (optional)</label>
                <input type="text" wire:model="code" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                <select wire:model="status" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    <option value="active">Active</option>
                    <option value="archived">Archived</option>
                </select>
                @error('status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Description (optional)</label>
            <textarea wire:model="description" rows="2" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('english-programmes.show', $englishProgramme) }}" class="rounded-md border border-slate-300 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50">Cancel</a>
            <button type="submit" class="rounded-md bg-brand-navy px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-navy-light" wire:loading.attr="disabled">Save Changes</button>
        </div>
    </form>
</div>
