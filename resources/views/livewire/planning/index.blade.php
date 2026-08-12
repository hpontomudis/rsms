<div class="mx-auto max-w-2xl space-y-4">
    <div class="flex flex-col gap-3 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="font-serif text-xl font-bold text-brand-navy">Annual Programmes</h1>
            <p class="text-sm text-slate-500">Program Tahunan &mdash; what each class is taught, and when.</p>
        </div>
        @can('academics.plan')
            <a href="{{ route('planning.annual.create') }}" wire:navigate
                class="flex-shrink-0 rounded-md bg-brand-navy px-4 py-2.5 text-center text-sm font-medium text-white hover:bg-brand-navy-light">
                New Programme
            </a>
        @endcan
    </div>

    <div class="grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-2">
        <select wire:model.live="academic_year_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
            @foreach ($academicYears as $year)
                <option value="{{ $year->id }}">{{ $year->name }}{{ $year->is_current ? ' (current)' : '' }}</option>
            @endforeach
        </select>
        <select wire:model.live="status" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
            <option value="">All statuses</option>
            <option value="draft">Draft</option>
            <option value="active">Active</option>
            <option value="archived">Archived</option>
        </select>
    </div>

    <div class="space-y-3">
        @forelse ($programmes as $programme)
            <a href="{{ route('planning.annual.show', $programme) }}" wire:navigate
                class="block rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 hover:ring-brand-navy">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-serif text-lg font-bold text-brand-navy">{{ $programme->rosterName() }}</span>
                            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600">{{ $programme->rosterLabel() }}</span>
                            <x-status-badge :status="$programme->status" />
                        </div>
                        <p class="mt-0.5 text-sm font-medium text-slate-800">{{ $programme->subject->name }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ $programme->learningPathway->title }} &middot; {{ $programme->curriculumScope->displayName() }}
                        </p>
                        <p class="mt-1 text-xs text-slate-400">
                            {{ $programme->curriculumScope->curriculum->name }}
                            &middot; {{ $programme->items_count }} {{ Str::plural('objective', $programme->items_count) }}
                        </p>
                    </div>
                </div>
            </a>
        @empty
            <p class="rounded-lg bg-white p-4 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">
                No annual programmes match these filters.
            </p>
        @endforelse
    </div>
</div>
