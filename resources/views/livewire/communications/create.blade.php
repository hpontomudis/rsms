<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('communications.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Communications</a>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h1 class="mb-4 font-serif text-xl font-bold text-brand-navy">New Communication</h1>

        <form wire:submit="save" class="space-y-4">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Display sender</label>
                <input type="text" wire:model="display_sender" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy" />
                <p class="mt-1 text-xs text-slate-500">Shown to recipients instead of your personal name -- e.g. "Rahai School Administration".</p>
                @error('display_sender') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Title</label>
                <input type="text" wire:model="title" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy" />
                @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Body</label>
                <textarea wire:model="body" rows="6" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
                @error('body') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Priority</label>
                    <select wire:model="priority" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        <option value="normal">Normal</option>
                        <option value="important">Important</option>
                        <option value="urgent">Urgent</option>
                    </select>
                    @error('priority') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Expires (optional)</label>
                    <input type="date" wire:model="expires_at" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy" />
                    @error('expires_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <p class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
                Created as a draft. Add an audience and review who it actually reaches before publishing --
                publishing freezes the content and audience permanently and cannot be undone.
            </p>

            <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Create draft</button>
        </form>
    </div>
</div>
