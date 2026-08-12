<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('my-teaching') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700">&larr; My Teaching</a>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h1 class="font-serif text-xl font-bold text-brand-navy">{{ $vocabulary['journal'] }}</h1>
        <p class="text-sm text-slate-500">
            {{ $classSubject->displayName() }} &middot; {{ $classSubject->subject->name }}
        </p>
        <p class="mt-1 text-xs text-slate-500">
            {{ $classSubject->teacher?->fullName() }} &middot;
            {{ $classSubject->started_on->format('d M Y') }}
            @if ($classSubject->ended_on) &ndash; {{ $classSubject->ended_on->format('d M Y') }} @endif
        </p>

        @if ($isBackfill)
            <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
                This assignment has ended. Only a manager may add a record here, and only for a date it actually covered.
                That is a correction, not new teaching.
            </p>
        @endif

        @error('journal_date') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        @error('academic_period_id') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        @error('class_subject_id') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    @if ($canCreate)
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-700">Record a session</h2>
                <button type="button" wire:click="$toggle('showCreate')" class="text-xs font-medium text-brand-navy hover:underline">+ Add</button>
            </div>

            @if ($showCreate)
                <form wire:submit="create" class="space-y-3 rounded-lg bg-slate-50 p-3">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Date</label>
                            <input type="date" wire:model.live="journal_date" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                            @error('journal_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Reporting period</label>
                            @if ($journal_date === '')
                                <p class="py-2 text-xs text-slate-500">Pick a date first.</p>
                            @elseif ($periods->isEmpty())
                                <p class="py-2 text-xs text-red-600">No reporting period covers that date. The academic calendar needs correcting.</p>
                            @else
                                <select wire:model="academic_period_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                                    <option value="">Select&hellip;</option>
                                    @foreach ($periods as $period)
                                        <option value="{{ $period->id }}">{{ $period->name }}</option>
                                    @endforeach
                                </select>
                                @if ($periods->count() > 1)
                                    <p class="mt-1 text-xs text-amber-700">More than one period covers that date. Choose which one this session belongs to.</p>
                                @endif
                            @endif
                            @error('academic_period_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Curriculum scope</label>
                        @if ($scopes->isEmpty())
                            <p class="text-xs text-amber-700">No active curriculum covers this roster yet.</p>
                        @else
                            <select wire:model="curriculum_scope_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                                <option value="">Select&hellip;</option>
                                @foreach ($scopes as $scope)
                                    <option value="{{ $scope->id }}">{{ $scope->curriculum->name }} &mdash; {{ $scope->displayName() }}</option>
                                @endforeach
                            </select>
                        @endif
                        @error('curriculum_scope_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Conducted by</label>
                        <select wire:model="conducted_by_staff_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                            @foreach ($staff as $member)
                                <option value="{{ $member->id }}">{{ $member->fullName() }}{{ $member->status !== 'active' ? ' (former staff)' : '' }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Defaults to the assigned teacher. Naming a substitute here changes nothing about the assignment.</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">What was taught</label>
                        <input type="text" wire:model="topic" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        @error('topic') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">What actually happened</label>
                        <textarea wire:model="actual_activity" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
                        @error('actual_activity') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Save draft</button>
                        <button type="button" wire:click="$toggle('showCreate')" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-white">Cancel</button>
                    </div>
                </form>
            @endif
        </div>
    @endif

    <div class="space-y-3">
        @forelse ($journals as $journal)
            <a href="{{ route('teaching.journal.show', $journal) }}" wire:navigate
                class="block rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 hover:ring-brand-navy">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-800">{{ $journal->journal_date->format('d M Y') }}</p>
                        <p class="mt-0.5 text-sm text-slate-700">{{ $journal->topic }}</p>
                        <p class="mt-1 text-xs text-slate-400">
                            {{ $journal->academicPeriod->name }}
                            &middot; {{ $journal->conductedBy?->fullName() }}
                            @if ($journal->wasSubstituted())<span class="text-amber-700">(substitute)</span>@endif
                            @if ($journal->actual_lesson_periods) &middot; {{ $journal->actual_lesson_periods }} JP @endif
                        </p>
                    </div>
                    <x-status-badge :status="$journal->status" />
                </div>
            </a>
        @empty
            <p class="rounded-lg bg-white p-4 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">
                Nothing recorded for this assignment yet.
            </p>
        @endforelse
    </div>
</div>
