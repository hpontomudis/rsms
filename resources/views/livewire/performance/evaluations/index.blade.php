<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="font-serif text-xl font-bold text-brand-navy">Performance Evaluations</h1>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('performance.frameworks.index') }}" class="text-sm text-brand-navy hover:underline self-center">Frameworks</a>
            @if ($canCreate)
                <a href="{{ route('performance.evaluations.create') }}" class="inline-flex justify-center rounded-md bg-brand-navy px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-navy-light">
                    + New Evaluation
                </a>
            @endif
        </div>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <input
            type="search"
            wire:model.live.debounce.400ms="search"
            placeholder="Search by staff name or number&hellip;"
            class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-slate-500 focus:ring-slate-500 sm:max-w-xs"
        >
        <select wire:model.live="staff_category_id" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-slate-500 focus:ring-slate-500 sm:w-48">
            <option value="">All categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}">{{ $category->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="status" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-slate-500 focus:ring-slate-500 sm:w-40">
            <option value="all">All statuses</option>
            <option value="draft">Draft</option>
            <option value="finalized">Finalized</option>
        </select>
    </div>

    <div class="space-y-2 md:hidden">
        @forelse ($evaluations as $evaluation)
            <a href="{{ route('performance.evaluations.show', $evaluation) }}" class="block rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center justify-between">
                    <span class="font-medium text-slate-900">{{ $evaluation->staff?->fullName() ?? $evaluation->staff_name_snapshot }}</span>
                    <x-status-badge :status="$evaluation->status" />
                </div>
                <div class="mt-1 text-sm text-slate-500">
                    {{ $evaluation->framework?->name ?? $evaluation->framework_name_snapshot }}
                    &middot; {{ $evaluation->period_start->format('d M Y') }} &ndash; {{ $evaluation->period_end->format('d M Y') }}
                </div>
            </a>
        @empty
            <p class="rounded-lg bg-white p-4 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">No evaluations found.</p>
        @endforelse
    </div>

    <div class="hidden overflow-x-auto rounded-lg bg-white shadow-sm ring-1 ring-slate-200 md:block">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Staff</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Category</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Framework</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Period</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($evaluations as $evaluation)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $evaluation->staff?->fullName() ?? $evaluation->staff_name_snapshot }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $evaluation->staffCategory?->name ?? $evaluation->staff_category_name_snapshot }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $evaluation->framework?->name ?? $evaluation->framework_name_snapshot }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $evaluation->period_start->format('d M Y') }} &ndash; {{ $evaluation->period_end->format('d M Y') }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$evaluation->status" /></td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('performance.evaluations.show', $evaluation) }}" class="text-slate-600 hover:text-slate-900">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">No evaluations found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $evaluations->links() }}</div>
</div>
