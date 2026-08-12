<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('my-teaching') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700">&larr; My Teaching</a>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h1 class="font-serif text-xl font-bold text-brand-navy">{{ $vocabulary['modules'] }}</h1>
        <p class="text-sm text-slate-500">
            {{ $classSubject->displayName() }} &middot; {{ $classSubject->subject->name }}
        </p>
        <p class="mt-1 text-xs text-slate-500">
            {{ $classSubject->teacher?->fullName() }} &middot;
            {{ $classSubject->started_on->format('d M Y') }}
            @if ($classSubject->ended_on) &ndash; {{ $classSubject->ended_on->format('d M Y') }} @endif
        </p>

        @unless ($classSubject->isActive())
            <p class="mt-3 rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600 ring-1 ring-slate-200">
                This assignment has ended. Its modules stay readable, and no new one may be written against it &mdash;
                a plan cannot honestly be written after the teaching.
            </p>
        @endunless

        @error('curriculum_scope_id') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        @error('class_subject_id') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    @if ($canCreate)
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-700">New {{ $vocabulary['module'] }}</h2>
                <button type="button" wire:click="$toggle('showCreate')" class="text-xs font-medium text-brand-navy hover:underline">+ Add</button>
            </div>

            @if ($showCreate)
                <form wire:submit="create" class="space-y-3 rounded-lg bg-slate-50 p-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Curriculum scope</label>
                        @if ($scopes->isEmpty())
                            <p class="text-xs text-amber-700">
                                No active curriculum covers this roster yet. A class needs its grade mapped to a learning phase,
                                and a teaching group needs an English level.
                            </p>
                        @else
                            <select wire:model="curriculum_scope_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                                <option value="">Select&hellip;</option>
                                @foreach ($scopes as $scope)
                                    <option value="{{ $scope->id }}">{{ $scope->curriculum->name }} &mdash; {{ $scope->displayName() }}</option>
                                @endforeach
                            </select>
                            @if ($scopes->count() > 1)
                                <p class="mt-1 text-xs text-amber-700">More than one curriculum version covers this roster. Choose the one you are teaching.</p>
                            @endif
                        @endif
                        @error('curriculum_scope_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Title</label>
                        <input type="text" wire:model="title" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Topic (optional)</label>
                        <input type="text" wire:model="topic" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Planned activity</label>
                        <textarea wire:model="planned_activity" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
                        @error('planned_activity') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Create draft</button>
                        <button type="button" wire:click="$toggle('showCreate')" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-white">Cancel</button>
                    </div>
                </form>
            @endif
        </div>
    @endif

    <div class="space-y-3">
        @forelse ($modules as $module)
            <a href="{{ route('teaching.modules.show', $module) }}" wire:navigate
                class="block rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 hover:ring-brand-navy">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-800">{{ $module->title }}</p>
                        @if ($module->topic)<p class="mt-0.5 text-xs text-slate-500">{{ $module->topic }}</p>@endif
                        <p class="mt-1 text-xs text-slate-400">
                            {{ $module->objective_links_count }} {{ Str::plural('objective', $module->objective_links_count) }}
                        </p>
                    </div>
                    <x-status-badge :status="$module->status" />
                </div>
            </a>
        @empty
            <p class="rounded-lg bg-white p-4 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">
                No {{ strtolower($vocabulary['modules']) }} written for this assignment yet.
            </p>
        @endforelse
    </div>
</div>
