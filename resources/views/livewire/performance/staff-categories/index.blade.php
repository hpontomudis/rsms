<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('performance.frameworks.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Performance</a>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h1 class="font-serif text-xl font-bold text-brand-navy">Staff Categories</h1>
        <p class="mt-1 text-sm text-slate-500">
            What a Performance Framework applies to &mdash; Teacher, Driver, Security, and so on.
        </p>
    </div>

    @if ($canManage)
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            @if (! $showForm)
                <button type="button" wire:click="startCreating" class="text-sm font-medium text-brand-navy hover:underline">+ New category</button>
            @else
                <form wire:submit="save" class="space-y-3">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Code</label>
                            <input type="text" wire:model="code" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy" />
                            @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Name</label>
                            <input type="text" wire:model="name" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy" />
                            @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Description</label>
                        <textarea wire:model="description" rows="2" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
                        @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Save</button>
                        <button type="button" wire:click="cancel" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
                    </div>
                </form>
            @endif
        </div>
    @endif

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs text-slate-500">
                    <th class="py-2">Code</th>
                    <th class="py-2">Name</th>
                    <th class="py-2 text-right">Staff</th>
                    <th class="py-2 text-right">Frameworks</th>
                    @if ($canManage) <th class="py-2"></th> @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr class="border-b border-slate-100">
                        <td class="py-2 font-mono text-xs text-slate-600">{{ $category->code }}</td>
                        <td class="py-2 text-slate-800">{{ $category->name }}</td>
                        <td class="py-2 text-right text-slate-600">{{ $category->staff_count }}</td>
                        <td class="py-2 text-right text-slate-600">{{ $category->frameworks_count }}</td>
                        @if ($canManage)
                            <td class="py-2 text-right">
                                <button type="button" wire:click="startEditing({{ $category->id }})" class="text-xs font-medium text-brand-navy hover:underline">Edit</button>
                                @if ($category->staff_count === 0 && $category->frameworks_count === 0)
                                    <button type="button" wire:click="delete({{ $category->id }})" wire:confirm="Delete {{ $category->name }}?" class="ml-2 text-xs text-red-500 hover:text-red-700">Delete</button>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-4 text-center text-slate-500">No staff categories yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
