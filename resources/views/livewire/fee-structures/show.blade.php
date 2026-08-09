<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('fee-structures.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Fee Structures</a>

    <div class="flex flex-col gap-3 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="font-serif text-xl font-bold text-brand-navy">{{ $feeStructure->name }}</h1>
            <p class="text-sm text-slate-500">{{ $feeStructure->grade->name }} &middot; {{ $feeStructure->academicYear->name }}</p>
        </div>
        @can('update', $feeStructure)
            <a href="{{ route('fee-structures.edit', $feeStructure) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Edit</a>
        @endcan
    </div>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700">Fee Items</h2>
            @can('update', $feeStructure)
                <button type="button" wire:click="$toggle('showAddItem')" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                    + Add Item
                </button>
            @endcan
        </div>

        @if ($showAddItem)
            <form wire:submit="addItem" class="mb-4 flex flex-col gap-3 rounded-lg bg-slate-50 p-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label class="mb-1 block text-xs font-medium text-slate-600">Item Name</label>
                    <input type="text" wire:model="item_name" placeholder="e.g. Tuition" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    @error('item_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:w-40">
                    <label class="mb-1 block text-xs font-medium text-slate-600">Amount (Rp)</label>
                    <input type="number" wire:model="item_amount" min="0" step="1000" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    @error('item_amount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Add</button>
            </form>
        @endif

        <ul class="divide-y divide-slate-100 text-sm">
            @forelse ($feeStructure->items as $item)
                <li class="flex items-center justify-between py-2">
                    <span class="text-slate-900">{{ $item->name }}</span>
                    <div class="flex items-center gap-3">
                        <span class="font-medium text-slate-900">Rp {{ number_format($item->amount, 0, ',', '.') }}</span>
                        @can('update', $feeStructure)
                            <button
                                type="button"
                                wire:click="removeItem({{ $item->id }})"
                                wire:confirm="Remove this fee item?"
                                class="text-xs text-red-500 hover:text-red-700"
                            >
                                Remove
                            </button>
                        @endcan
                    </div>
                </li>
            @empty
                <li class="py-2 text-slate-500">No fee items yet.</li>
            @endforelse
        </ul>

        <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3 text-sm font-semibold">
            <span class="text-slate-700">Total</span>
            <span class="text-slate-900">Rp {{ number_format($feeStructure->total(), 0, ',', '.') }}</span>
        </div>
    </div>
</div>
