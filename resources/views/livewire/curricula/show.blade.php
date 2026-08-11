<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('curricula.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Curricula</a>

    @if (session('status'))
        <div class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700 ring-1 ring-emerald-200">{{ session('status') }}</div>
    @endif

    <div class="flex flex-col gap-3 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h1 class="font-serif text-xl font-bold text-brand-navy">{{ $curriculum->name }}</h1>
            <p class="text-sm text-slate-500">{{ $curriculum->code }} &middot; version {{ $curriculum->version }}</p>
            <div class="mt-2"><x-status-badge :status="$curriculum->status" /></div>
        </div>
        @can('update', $curriculum)
            <a href="{{ route('curricula.edit', $curriculum) }}" class="flex-shrink-0 rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Edit</a>
        @endcan
    </div>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <dl class="space-y-3 text-sm">
            <div class="flex flex-wrap justify-between gap-2">
                <dt class="text-slate-500">Effective</dt>
                <dd class="text-slate-900">
                    {{ $curriculum->effective_from->format('d M Y') }}
                    &ndash; {{ $curriculum->effective_to?->format('d M Y') ?? 'open' }}
                </dd>
            </div>
            <div class="flex flex-wrap justify-between gap-2">
                <dt class="text-slate-500">Scope basis</dt>
                <dd class="text-right text-slate-900">
                    @if ($curriculum->englishProgramme)
                        {{ $curriculum->englishProgramme->name }}
                    @else
                        National &mdash; scoped by Learning Phase
                    @endif
                </dd>
            </div>
            @if ($curriculum->source_reference)
                <div class="flex flex-wrap justify-between gap-2">
                    <dt class="text-slate-500">Source reference</dt>
                    <dd class="text-right text-slate-900">{{ $curriculum->source_reference }}</dd>
                </div>
            @endif
        </dl>

        @if ($curriculum->description)
            <p class="mt-4 border-t border-slate-100 pt-4 text-sm text-slate-600">{{ $curriculum->description }}</p>
        @endif
    </div>

    @can('update', $curriculum)
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-2 text-sm font-semibold text-slate-700">Lifecycle</h2>
            @error('status') <p class="mb-2 text-xs text-red-600">{{ $message }}</p> @enderror

            <div class="flex flex-wrap gap-3">
                @if ($curriculum->isDraft())
                    <button type="button" wire:click="activate"
                        wire:confirm="Activate this version? Any active version of {{ $curriculum->code }} will be archived."
                        class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Activate</button>
                @endif
                @unless ($curriculum->isArchived())
                    <button type="button" wire:click="archive"
                        wire:confirm="Archive this version? It stays readable as history."
                        class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Archive</button>
                @endunless
            </div>

            <p class="mt-3 text-xs text-slate-500">
                Archiving keeps the row. A curriculum version is never deleted &mdash; work recorded against
                it must keep pointing at the standards that were actually in force.
            </p>
        </div>
    @endcan

    @if ($otherVersions->isNotEmpty())
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Other versions of {{ $curriculum->code }}</h2>
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($otherVersions as $other)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <a href="{{ route('curricula.show', $other) }}" class="text-slate-700 hover:underline">
                            {{ $other->name }} <span class="text-slate-500">v{{ $other->version }}</span>
                        </a>
                        <span class="flex-shrink-0 text-xs text-slate-500">
                            {{ $other->effective_from->format('d M Y') }}
                            &ndash; {{ $other->effective_to?->format('d M Y') ?? 'open' }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <p class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
        Curriculum scopes, learning outcomes (CP) and learning objectives (TP) are not implemented yet.
        This registry records which curriculum versions exist.
    </p>
</div>
