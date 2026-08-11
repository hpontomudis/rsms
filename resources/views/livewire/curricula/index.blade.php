<div class="mx-auto max-w-2xl space-y-4">
    <div class="flex flex-col gap-3 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="font-serif text-xl font-bold text-brand-navy">Curricula</h1>
            <p class="mt-1 text-sm text-slate-500">
                Curriculum versions. Superseding a version archives it and opens a new one, so the
                standards work was recorded against stay readable.
            </p>
        </div>
        @can('create', \App\Models\Curriculum::class)
            <a href="{{ route('curricula.create') }}" class="flex-shrink-0 rounded-md bg-brand-navy px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-navy-light">
                + Add Curriculum
            </a>
        @endcan
    </div>

    @if (session('status'))
        <div class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700 ring-1 ring-emerald-200">{{ session('status') }}</div>
    @endif

    @forelse ($families as $code => $versions)
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $code }}</p>
            <ul class="divide-y divide-slate-100">
                @foreach ($versions as $curriculum)
                    <li class="py-2">
                        <a href="{{ route('curricula.show', $curriculum) }}" class="block hover:opacity-80">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <span class="font-medium text-slate-900">{{ $curriculum->name }}</span>
                                    <span class="ml-1 text-sm text-slate-500">v{{ $curriculum->version }}</span>
                                </div>
                                <x-status-badge :status="$curriculum->status" />
                            </div>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ $curriculum->effective_from->format('d M Y') }}
                                &ndash; {{ $curriculum->effective_to?->format('d M Y') ?? 'open' }}
                                @if ($curriculum->englishProgramme)
                                    &middot; {{ $curriculum->englishProgramme->name }}
                                @else
                                    &middot; National (phase-based)
                                @endif
                            </p>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @empty
        <p class="rounded-lg bg-white p-4 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">
            No curriculum versions recorded yet. Nothing is seeded by default &mdash; a version carries a
            real school decision, so it is created here rather than invented.
        </p>
    @endforelse
</div>
