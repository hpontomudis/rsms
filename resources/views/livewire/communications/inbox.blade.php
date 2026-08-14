<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('communications.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Communications</a>

    <h1 class="font-serif text-xl font-bold text-brand-navy">My Inbox</h1>

    <div class="space-y-3">
        @forelse ($recipients as $recipient)
            <a href="{{ route('communications.show', $recipient->communication) }}" class="block rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 hover:ring-brand-navy">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="mb-1 flex flex-wrap items-center gap-2">
                            @unless ($recipient->isRead())
                                <span class="h-2 w-2 flex-shrink-0 rounded-full bg-brand-navy"></span>
                            @endunless
                            @if ($recipient->communication->priority !== 'normal')
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize ring-1 ring-inset
                                    {{ $recipient->communication->priority === 'urgent' ? 'bg-red-50 text-red-700 ring-red-200' : 'bg-amber-50 text-amber-700 ring-amber-200' }}">
                                    {{ $recipient->communication->priority }}
                                </span>
                            @endif
                            <span class="{{ $recipient->isRead() ? 'font-medium' : 'font-semibold' }} text-slate-800">{{ $recipient->communication->title }}</span>
                        </div>
                        <p class="text-xs text-slate-500">
                            {{ $recipient->communication->display_sender }} &middot; {{ $recipient->communication->published_at->format('d M Y') }}
                            @if ($recipient->communication->isArchived()) &middot; archived @endif
                        </p>
                    </div>
                </div>
            </a>
        @empty
            <p class="rounded-lg bg-white p-4 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">
                Nothing in your inbox yet.
            </p>
        @endforelse
    </div>
</div>
