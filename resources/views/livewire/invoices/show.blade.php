<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('invoices.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Invoices</a>

    <div class="flex flex-col gap-3 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="font-serif text-xl font-bold text-brand-navy">{{ $invoice->invoice_number }}</h1>
            <p class="text-sm text-slate-500">
                <a href="{{ route('students.show', $invoice->student) }}" class="hover:underline">{{ $invoice->student->fullName() }}</a>
                &middot; Due {{ $invoice->due_date->format('d M Y') }}
            </p>
            <div class="mt-2"><x-status-badge :status="$invoice->status" /></div>
        </div>
        @can('void', $invoice)
            <button
                type="button"
                wire:click="voidInvoice"
                wire:confirm="Void invoice {{ $invoice->invoice_number }}? This cannot be undone."
                class="rounded-md border border-red-200 px-4 py-2 text-sm text-red-600 hover:bg-red-50"
            >
                Void Invoice
            </button>
        @endcan
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div class="text-lg font-semibold text-slate-900">Rp {{ number_format($invoice->itemsTotal(), 0, ',', '.') }}</div>
            <div class="text-xs text-slate-500">Items</div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div class="text-lg font-semibold text-slate-900">Rp {{ number_format($invoice->discountsTotal(), 0, ',', '.') }}</div>
            <div class="text-xs text-slate-500">Discounts</div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div class="text-lg font-semibold text-slate-900">Rp {{ number_format($invoice->paidTotal(), 0, ',', '.') }}</div>
            <div class="text-xs text-slate-500">Paid</div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div class="text-lg font-semibold {{ $invoice->balance() > 0 ? 'text-red-600' : 'text-emerald-600' }}">Rp {{ number_format($invoice->balance(), 0, ',', '.') }}</div>
            <div class="text-xs text-slate-500">Balance</div>
        </div>
    </div>

    {{-- Items --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700">Items</h2>
            @can('manageItems', $invoice)
                <button type="button" wire:click="$toggle('showAddItem')" class="text-sm font-medium text-slate-600 hover:text-slate-900">+ Add Item</button>
            @endcan
        </div>

        @if ($showAddItem)
            <form wire:submit="addItem" class="mb-4 flex flex-col gap-3 rounded-lg bg-slate-50 p-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label class="mb-1 block text-xs font-medium text-slate-600">Description</label>
                    <input type="text" wire:model="item_description" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    @error('item_description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
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
            @forelse ($invoice->items as $item)
                <li class="flex items-center justify-between py-2">
                    <span class="text-slate-900">{{ $item->description }}</span>
                    <div class="flex items-center gap-3">
                        <span class="font-medium text-slate-900">Rp {{ number_format($item->amount, 0, ',', '.') }}</span>
                        @can('manageItems', $invoice)
                            <button type="button" wire:click="removeItem({{ $item->id }})" wire:confirm="Remove this item?" class="text-xs text-red-500 hover:text-red-700">Remove</button>
                        @endcan
                    </div>
                </li>
            @empty
                <li class="py-2 text-slate-500">No items on this invoice.</li>
            @endforelse
        </ul>
        @if (! $invoice->payments->isEmpty())
            <p class="mt-3 text-xs text-slate-400">Items are locked once a payment has been recorded.</p>
        @endif
    </div>

    @if (! $hasStaffProfile)
        <div class="rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-700 ring-1 ring-amber-200">
            Your account isn't linked to a staff profile, so you can't approve discounts or record payments. Ask an
            administrator to link your user account to a staff record.
        </div>
    @endif

    {{-- Discounts --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700">Discounts &amp; Scholarships</h2>
            @can('addDiscount', $invoice)
                @if ($hasStaffProfile)
                    <button type="button" wire:click="$toggle('showAddDiscount')" class="text-sm font-medium text-slate-600 hover:text-slate-900">+ Add Discount</button>
                @endif
            @endcan
        </div>

        @if ($showAddDiscount)
            <form wire:submit="addDiscount" class="mb-4 space-y-3 rounded-lg bg-slate-50 p-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Type</label>
                        <select wire:model="discount_type" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                            <option value="discount">Discount</option>
                            <option value="scholarship">Scholarship</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Basis</label>
                        <select wire:model.live="discount_mode" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                            <option value="amount">Fixed Amount</option>
                            <option value="percentage">Percentage</option>
                        </select>
                    </div>
                </div>
                @if ($discount_mode === 'amount')
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Amount (Rp)</label>
                        <input type="number" wire:model="discount_amount" min="0" step="1000" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        @error('discount_amount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @else
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Percentage (%)</label>
                        <input type="number" wire:model="discount_percentage" min="0" max="100" step="1" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        @error('discount_percentage') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Reason</label>
                    <input type="text" wire:model="discount_reason" placeholder="e.g. sibling discount, financial hardship" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    @error('discount_reason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Approve Discount</button>
            </form>
        @endif

        <ul class="divide-y divide-slate-100 text-sm">
            @forelse ($invoice->discounts as $discount)
                <li class="py-2">
                    <div class="flex items-center justify-between">
                        <span class="capitalize text-slate-900">{{ $discount->type }}</span>
                        <span class="font-medium text-slate-900">
                            {{ $discount->amount !== null ? 'Rp '.number_format($discount->amount, 0, ',', '.') : $discount->percentage.'%' }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500">{{ $discount->reason }} &middot; approved by {{ $discount->approver->fullName() }}</p>
                </li>
            @empty
                <li class="py-2 text-slate-500">No discounts applied.</li>
            @endforelse
        </ul>
    </div>

    {{-- Payments --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700">Payments</h2>
            @can('recordPayment', $invoice)
                @if ($hasStaffProfile)
                    <button type="button" wire:click="$toggle('showRecordPayment')" class="text-sm font-medium text-slate-600 hover:text-slate-900">+ Record Payment</button>
                @endif
            @endcan
        </div>

        @if ($showRecordPayment)
            <form wire:submit="recordPayment" class="mb-4 space-y-3 rounded-lg bg-slate-50 p-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Amount (Rp)</label>
                        <input type="number" wire:model="payment_amount" min="0" step="1000" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        @error('payment_amount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Method</label>
                        <select wire:model="payment_method" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Date Paid</label>
                    <input type="date" wire:model="payment_date" max="{{ now()->toDateString() }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    @error('payment_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Record Payment</button>
            </form>
        @endif

        <ul class="divide-y divide-slate-100 text-sm">
            @forelse ($invoice->payments as $payment)
                <li class="flex items-center justify-between py-2">
                    <div>
                        <span class="text-slate-900">{{ $payment->paid_at->format('d M Y') }}</span>
                        <span class="ml-1 capitalize text-slate-500">({{ str_replace('_', ' ', $payment->method) }})</span>
                        <p class="text-xs text-slate-400">Received by {{ $payment->receivedBy->fullName() }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="font-medium text-slate-900">Rp {{ number_format($payment->amount, 0, ',', '.') }}</span>
                        <a href="{{ route('payments.receipt', $payment) }}" target="_blank" class="text-xs text-brand-navy hover:underline">Receipt</a>
                    </div>
                </li>
            @empty
                <li class="py-2 text-slate-500">No payments recorded.</li>
            @endforelse
        </ul>
    </div>
</div>
