<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="font-serif text-xl font-bold text-brand-navy">Teaching Groups</h1>
            <p class="text-sm text-slate-500">Groups of students taught together, for one academic year.</p>
        </div>
        @can('create', \App\Models\TeachingGroup::class)
            <a href="{{ route('teaching-groups.create') }}" class="inline-flex justify-center rounded-md bg-brand-navy px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-navy-light">
                + Add Group
            </a>
        @endcan
    </div>

    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <label class="mb-1 block text-xs font-medium text-slate-600">Academic Year</label>
        <select wire:model.live="academic_year_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy sm:max-w-xs">
            @foreach ($academicYears as $year)
                <option value="{{ $year->id }}">{{ $year->name }}@if ($year->is_current) (current) @endif</option>
            @endforeach
        </select>
    </div>

    <div class="space-y-2">
        @forelse ($groups as $group)
            <a href="{{ route('teaching-groups.show', $group) }}" class="block rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200 hover:ring-slate-300">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-medium text-slate-900">{{ $group->name }}</span>
                    <x-status-badge :status="$group->status" />
                </div>
                <div class="mt-1 text-sm text-slate-500">
                    {{ $group->active_memberships_count }} active {{ Str::plural('student', $group->active_memberships_count) }}
                    @if ($group->englishLevel)
                        &middot; {{ $group->englishLevel->name }} &middot; {{ $group->englishLevel->programme?->name }}
                    @else
                        &middot; general group
                    @endif
                </div>
            </a>
        @empty
            <p class="rounded-lg bg-white p-4 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">
                No teaching groups in this academic year yet. Groups are created when the school actually runs them &mdash; they are not generated from the English levels.
            </p>
        @endforelse
    </div>
</div>
