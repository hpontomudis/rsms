<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="font-serif text-xl font-bold text-brand-navy">English Programmes</h1>
            <p class="text-sm text-slate-500">Proficiency frameworks and the grades they apply to.</p>
        </div>
        @can('create', \App\Models\EnglishProgramme::class)
            <a href="{{ route('english-programmes.create') }}" class="inline-flex justify-center rounded-md bg-brand-navy px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-navy-light">
                + Add Programme
            </a>
        @endcan
    </div>

    <div class="space-y-2">
        @forelse ($programmes as $programme)
            <a href="{{ route('english-programmes.show', $programme) }}" class="block rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200 hover:ring-slate-300">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-medium text-slate-900">{{ $programme->name }}</span>
                    <x-status-badge :status="$programme->status" />
                </div>
                <div class="mt-1 text-sm text-slate-500">
                    {{ $programme->levels_count }} {{ Str::plural('level', $programme->levels_count) }}
                    &middot;
                    {{ $programme->grade_links_count }} {{ Str::plural('grade', $programme->grade_links_count) }}
                    @if ($programme->code) &middot; {{ $programme->code }} @endif
                </div>
            </a>
        @empty
            <p class="rounded-lg bg-white p-4 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">No English programmes yet.</p>
        @endforelse
    </div>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-1 text-sm font-semibold text-slate-700">Grades without a proficiency programme</h2>
        <p class="mb-3 text-xs text-slate-500">
            English is taught to these grades as an ordinary class-based subject. A grade can belong to at most one programme.
        </p>
        @if ($unmappedGrades->isEmpty())
            <p class="text-sm text-slate-500">Every grade is mapped to a programme.</p>
        @else
            <div class="flex flex-wrap gap-1.5">
                @foreach ($unmappedGrades as $grade)
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-600">{{ $grade->name }}</span>
                @endforeach
            </div>
        @endif
    </div>
</div>
