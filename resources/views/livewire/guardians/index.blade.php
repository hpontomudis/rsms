<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-xl font-semibold text-slate-800">Guardians</h1>
        @can('create', \App\Models\Guardian::class)
            <a href="{{ route('guardians.create') }}" class="inline-flex justify-center rounded-md bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800">
                + Add Guardian
            </a>
        @endcan
    </div>

    <input
        type="search"
        wire:model.live.debounce.400ms="search"
        placeholder="Search by name or phone&hellip;"
        class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-slate-500 focus:ring-slate-500 sm:max-w-xs"
    >

    {{-- Mobile: stacked cards --}}
    <div class="space-y-2 md:hidden">
        @forelse ($guardians as $guardian)
            <a href="{{ route('guardians.show', $guardian) }}" class="block rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center justify-between">
                    <span class="font-medium text-slate-900">{{ $guardian->fullName() }}</span>
                    <span class="text-xs text-slate-500">{{ $guardian->students_count }} {{ Str::plural('child', $guardian->students_count) }}</span>
                </div>
                <div class="mt-1 text-sm text-slate-500">{{ $guardian->phone }}</div>
            </a>
        @empty
            <p class="rounded-lg bg-white p-4 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">No guardians found.</p>
        @endforelse
    </div>

    {{-- Desktop: table --}}
    <div class="hidden overflow-x-auto rounded-lg bg-white shadow-sm ring-1 ring-slate-200 md:block">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Name</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Phone</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Email</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Children</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($guardians as $guardian)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $guardian->fullName() }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $guardian->phone }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $guardian->email ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $guardian->students_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('guardians.show', $guardian) }}" class="text-slate-600 hover:text-slate-900">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">No guardians found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $guardians->links() }}</div>
</div>
