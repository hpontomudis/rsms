<div class="mx-auto max-w-2xl space-y-4">
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h1 class="font-serif text-xl font-bold text-brand-navy">My Evaluations</h1>
        <p class="mt-1 text-sm text-slate-500">
            Finalized performance evaluations recorded against your staff profile. A draft in progress is
            not shown here &mdash; only the finished, accountable record.
        </p>
    </div>

    @if ($ambiguous)
        <p class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
            Your login is linked to more than one staff record, so evaluations cannot be shown here
            unambiguously. Contact an administrator.
        </p>
    @elseif (! $me)
        <p class="rounded-lg bg-white p-4 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">
            No staff profile is linked to your login.
        </p>
    @else
        <div class="space-y-2">
            @forelse ($evaluations as $evaluation)
                <a href="{{ route('performance.evaluations.show', $evaluation) }}" class="block rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200 hover:bg-slate-50">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-medium text-slate-900">{{ $evaluation->framework_name_snapshot }}</span>
                        <x-status-badge :status="$evaluation->status" />
                    </div>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $evaluation->period_start->format('d M Y') }} &ndash; {{ $evaluation->period_end->format('d M Y') }}
                        &middot; finalized {{ $evaluation->finalized_at->format('d M Y') }}
                    </p>
                </a>
            @empty
                <p class="rounded-lg bg-white p-4 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">
                    No finalized evaluations yet.
                </p>
            @endforelse
        </div>
    @endif
</div>
