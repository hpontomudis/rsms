<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="font-serif text-xl font-bold text-brand-navy">Invoices</h1>
        <div class="flex gap-2">
            @can('viewAny', \App\Models\FeeStructure::class)
                <a href="{{ route('fee-structures.index') }}" class="inline-flex justify-center rounded-md border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
                    Fee Structures
                </a>
            @endcan
            @can('create', \App\Models\Invoice::class)
                <a href="{{ route('invoices.create') }}" class="inline-flex justify-center rounded-md bg-brand-navy px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-navy-light">
                    + New Invoice
                </a>
            @endcan
        </div>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <input
            type="search"
            wire:model.live.debounce.400ms="search"
            placeholder="Search by student name or number&hellip;"
            class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"
        >
        <select wire:model.live="status" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
            <option value="all">All statuses</option>
            <option value="unpaid">Unpaid</option>
            <option value="partially_paid">Partially Paid</option>
            <option value="paid">Paid</option>
            <option value="void">Void</option>
        </select>
        <select wire:model.live="academic_year_id" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
            <option value="">All academic years</option>
            @foreach ($academicYears as $year)
                <option value="{{ $year->id }}">{{ $year->name }}{{ $year->is_current ? ' (current)' : '' }}</option>
            @endforeach
        </select>
    </div>

    {{-- Mobile: stacked cards --}}
    <div class="space-y-2 md:hidden">
        @forelse ($invoices as $invoice)
            <a href="{{ route('invoices.show', $invoice) }}" class="block rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center justify-between">
                    <span class="font-medium text-slate-900">{{ $invoice->student->fullName() }}</span>
                    <x-status-badge :status="$invoice->status" />
                </div>
                <div class="mt-1 flex items-center justify-between text-sm text-slate-500">
                    <span>{{ $invoice->invoice_number }}</span>
                    <span>Rp {{ number_format($invoice->balance(), 0, ',', '.') }}</span>
                </div>
            </a>
        @empty
            <p class="rounded-lg bg-white p-4 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">No invoices found.</p>
        @endforelse
    </div>

    {{-- Desktop: table --}}
    <div class="hidden overflow-x-auto rounded-lg bg-white shadow-sm ring-1 ring-slate-200 md:block">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Invoice No.</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Student</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Due Date</th>
                    <th class="px-4 py-3 text-right font-medium text-slate-500">Balance</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($invoices as $invoice)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-slate-600">{{ $invoice->invoice_number }}</td>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $invoice->student->fullName() }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $invoice->due_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right text-slate-900">Rp {{ number_format($invoice->balance(), 0, ',', '.') }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$invoice->status" /></td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('invoices.show', $invoice) }}" class="text-slate-600 hover:text-slate-900">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">No invoices found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $invoices->links() }}</div>
</div>
