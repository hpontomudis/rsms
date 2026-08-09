<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="font-serif text-xl font-bold text-brand-navy">Fee Structures</h1>
        @can('create', \App\Models\FeeStructure::class)
            <a href="{{ route('fee-structures.create') }}" class="inline-flex justify-center rounded-md bg-brand-navy px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-navy-light">
                + Add Fee Structure
            </a>
        @endcan
    </div>

    <select wire:model.live="academic_year_id" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-1 focus:ring-brand-navy sm:max-w-xs">
        <option value="">All academic years</option>
        @foreach ($academicYears as $year)
            <option value="{{ $year->id }}">{{ $year->name }}{{ $year->is_current ? ' (current)' : '' }}</option>
        @endforeach
    </select>

    {{-- Mobile: stacked cards --}}
    <div class="space-y-2 md:hidden">
        @forelse ($structures as $structure)
            <a href="{{ route('fee-structures.show', $structure) }}" class="block rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center justify-between">
                    <span class="font-medium text-slate-900">{{ $structure->name }}</span>
                    <span class="text-sm text-slate-700">Rp {{ number_format($structure->total(), 0, ',', '.') }}</span>
                </div>
                <div class="mt-1 text-sm text-slate-500">{{ $structure->grade->name }} &middot; {{ $structure->academicYear->name }}</div>
            </a>
        @empty
            <p class="rounded-lg bg-white p-4 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">No fee structures found.</p>
        @endforelse
    </div>

    {{-- Desktop: table --}}
    <div class="hidden overflow-x-auto rounded-lg bg-white shadow-sm ring-1 ring-slate-200 md:block">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Name</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Grade</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Academic Year</th>
                    <th class="px-4 py-3 text-right font-medium text-slate-500">Total</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($structures as $structure)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $structure->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $structure->grade->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $structure->academicYear->name }}</td>
                        <td class="px-4 py-3 text-right text-slate-900">Rp {{ number_format($structure->total(), 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('fee-structures.show', $structure) }}" class="text-slate-600 hover:text-slate-900">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">No fee structures found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $structures->links() }}</div>
</div>
