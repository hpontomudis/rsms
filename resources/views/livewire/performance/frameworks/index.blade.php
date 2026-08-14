<div class="mx-auto max-w-2xl space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h1 class="font-serif text-xl font-bold text-brand-navy">Performance Frameworks</h1>
        <div class="flex gap-3 text-sm">
            <a href="{{ route('performance.staff-categories.index') }}" class="text-brand-navy hover:underline">Staff Categories</a>
            <a href="{{ route('performance.evaluations.index') }}" class="text-brand-navy hover:underline">Evaluations</a>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700 ring-1 ring-emerald-200">{{ session('status') }}</div>
    @endif

    @if ($canManage)
        <a href="{{ route('performance.frameworks.create') }}" class="inline-block rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">New framework</a>
    @endif

    @forelse ($byCategory as $categoryName => $frameworks)
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">{{ $categoryName }}</h2>
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($frameworks as $framework)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <a href="{{ route('performance.frameworks.show', $framework) }}" class="min-w-0 hover:underline">
                            <span class="font-medium text-slate-900">{{ $framework->name }}</span>
                            <span class="ml-1 text-xs text-slate-500">{{ $framework->code }} v{{ $framework->version }}</span>
                        </a>
                        <div class="flex flex-shrink-0 items-center gap-2">
                            <span class="text-xs text-slate-500">{{ $framework->evaluations_count }} {{ Str::plural('evaluation', $framework->evaluations_count) }}</span>
                            <x-status-badge :status="$framework->status" />
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @empty
        <p class="rounded-lg bg-white p-4 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">
            No performance frameworks yet.
        </p>
    @endforelse
</div>
