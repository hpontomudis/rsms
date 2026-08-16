<div class="mx-auto max-w-4xl space-y-4">
    <div>
        <h1 class="font-serif text-xl font-bold text-brand-navy">Management Insights</h1>
        <p class="text-sm text-slate-500">Deterministic operational facts drawn from live RSMS data. Every count links back to real records.</p>
    </div>

    {{-- Filters --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Academic Year</label>
                <select wire:model.live="academic_year_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    <option value="">Select…</option>
                    @foreach ($years as $year)
                        <option value="{{ $year->id }}">{{ $year->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Academic Period (optional)</label>
                <select wire:model.live="academic_period_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    <option value="">Whole year</option>
                    @foreach ($periods as $period)
                        <option value="{{ $period->id }}">{{ $period->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Deterministic insights --}}
    <div class="space-y-3">
        @forelse ($insights as $insight)
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="mb-1 flex flex-wrap items-center gap-2">
                            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs uppercase tracking-wide text-slate-500">{{ str_replace('_', ' ', $insight->category) }}</span>
                            @if ($insight->severity === 'attention')
                                <span class="rounded bg-amber-50 px-1.5 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-amber-200">Attention</span>
                            @else
                                <span class="rounded bg-slate-50 px-1.5 py-0.5 text-xs text-slate-600 ring-1 ring-slate-200">Info</span>
                            @endif
                            @if ($insight->reliability !== 'reliable')
                                <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600" title="{{ $insight->reliabilityNote ?? '' }}">
                                    {{ $insight->reliability }}
                                </span>
                            @endif
                        </div>
                        <h2 class="text-base font-semibold text-slate-800">{{ $insight->title }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ $insight->description }}</p>
                        @if ($insight->reliabilityNote && $insight->reliability !== 'reliable')
                            <p class="mt-1 text-xs text-slate-500">{{ $insight->reliabilityNote }}</p>
                        @endif
                    </div>
                    <div class="flex flex-shrink-0 flex-col items-end gap-1">
                        <span class="font-serif text-3xl text-brand-navy">
                            @if ($insight->count === null)
                                &mdash;
                            @else
                                {{ $insight->count }}
                            @endif
                        </span>
                        @if ($insight->actionRouteName && $insight->count !== null && $insight->count > 0 && Route::has($insight->actionRouteName))
                            <a href="{{ route($insight->actionRouteName, $insight->actionRouteParams) }}" wire:navigate class="text-xs font-medium text-brand-navy hover:underline">Review →</a>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-xl bg-white p-5 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">
                Pick an Academic Year to see insights.
            </div>
        @endforelse
    </div>

    {{-- AI narrative (optional, secondary) --}}
    @if ($canUseAi && count($insights) > 0)
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-700">AI narrative</h2>
                @if ($aiSummary === null && empty($aiAttentionPoints))
                    <button type="button" wire:click="generateAiSummary" wire:loading.attr="disabled"
                        @unless ($canGenerateAi) disabled @endunless
                        class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light disabled:opacity-50">
                        <span wire:loading.remove wire:target="generateAiSummary">Generate management summary</span>
                        <span wire:loading wire:target="generateAiSummary">Generating…</span>
                    </button>
                @else
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="generateAiSummary" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-50">Regenerate</button>
                        <button type="button" wire:click="dismissAiSummary" class="rounded-md px-3 py-1.5 text-xs text-slate-500 hover:bg-slate-100">Dismiss</button>
                    </div>
                @endif
            </div>

            @if ($aiError)
                <p class="mb-3 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">{{ $aiError }}</p>
            @endif

            @if ($aiSummary || ! empty($aiAttentionPoints))
                <p class="mb-3 text-xs text-slate-500">AI-generated summary of the verified RSMS facts shown above. Review before acting.</p>
                @if ($aiSummary)
                    <p class="whitespace-pre-line rounded-md bg-slate-50 p-3 text-sm text-slate-700 ring-1 ring-slate-200">{{ $aiSummary }}</p>
                @endif
                @if (! empty($aiAttentionPoints))
                    <div class="mt-3">
                        <p class="mb-1 text-xs font-medium text-slate-500">Areas to review</p>
                        <ul class="list-disc space-y-1 pl-5 text-sm text-slate-700">
                            @foreach ($aiAttentionPoints as $point)
                                <li>{{ $point }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endif
        </div>
    @endif
</div>
