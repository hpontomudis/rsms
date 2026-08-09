<div class="mx-auto max-w-xl space-y-4">
    <a href="{{ route('guardians.show', $guardian) }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; {{ $guardian->fullName() }}</a>
    <h1 class="text-xl font-semibold text-slate-800">Edit Guardian</h1>

    <form wire:submit="save" class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">First Name</label>
                <input type="text" wire:model="first_name" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base">
                @error('first_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Last Name</label>
                <input type="text" wire:model="last_name" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base">
                @error('last_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Phone</label>
                <input type="tel" wire:model="phone" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base">
                @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Email (optional)</label>
                <input type="email" wire:model="email" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Occupation (optional)</label>
                <input type="text" wire:model="occupation" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base">
                @error('occupation') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Address (optional)</label>
                <textarea wire:model="address" rows="2" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base"></textarea>
                @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('guardians.show', $guardian) }}" class="rounded-md border border-slate-300 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50">Cancel</a>
            <button type="submit" class="rounded-md bg-slate-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-slate-800" wire:loading.attr="disabled">
                Save Changes
            </button>
        </div>
    </form>
</div>
