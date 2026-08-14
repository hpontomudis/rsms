<div class="mx-auto max-w-2xl space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h1 class="font-serif text-xl font-bold text-brand-navy">Communications</h1>
        <a href="{{ route('communications.inbox') }}" class="text-sm text-brand-navy hover:underline">My Inbox</a>
    </div>

    @if (session('status'))
        <div class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700 ring-1 ring-emerald-200">{{ session('status') }}</div>
    @endif

    @if ($canCreate)
        <a href="{{ route('communications.create') }}" class="inline-block rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">New communication</a>
    @endif

    <div class="flex gap-1 rounded-lg bg-slate-100 p-1 text-sm">
        @foreach (['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'] as $value => $label)
            <button type="button" wire:click="$set('tab', '{{ $value }}')"
                class="flex-1 rounded-md px-3 py-1.5 font-medium transition {{ $tab === $value ? 'bg-white text-brand-navy shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="space-y-3">
        @forelse ($communications as $communication)
            <a href="{{ route('communications.show', $communication) }}" class="block rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 hover:ring-brand-navy">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="mb-1 flex flex-wrap items-center gap-2">
                            @if ($communication->priority !== 'normal')
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize ring-1 ring-inset
                                    {{ $communication->priority === 'urgent' ? 'bg-red-50 text-red-700 ring-red-200' : 'bg-amber-50 text-amber-700 ring-amber-200' }}">
                                    {{ $communication->priority }}
                                </span>
                            @endif
                            <span class="font-medium text-slate-800">{{ $communication->title }}</span>
                        </div>
                        <p class="text-xs text-slate-500">
                            {{ $communication->display_sender }}
                            @if ($communication->published_at) &middot; {{ $communication->published_at->format('d M Y') }} @endif
                        </p>
                    </div>
                </div>
            </a>
        @empty
            <p class="rounded-lg bg-white p-4 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">
                Nothing here yet.
            </p>
        @endforelse
    </div>
</div>
