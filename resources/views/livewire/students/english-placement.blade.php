<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('students.show', $student) }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; {{ $student->fullName() }}</a>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h1 class="font-serif text-xl font-bold text-brand-navy">English Proficiency</h1>
        <p class="text-sm text-slate-500">
            {{ $student->fullName() }}
            @if ($grade) &middot; {{ $grade->name }} @endif
            @if ($programme) &middot; {{ $programme->name }} @endif
        </p>

        @if ($gradeProblem)
            <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200">{{ $gradeProblem }}</p>
        @elseif (! $programme)
            <p class="mt-3 rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-600">
                {{ $grade->name }} is not covered by an English proficiency programme, so no level can be recorded. English is taught to this grade as an ordinary class-based subject.
            </p>
        @endif
    </div>

    {{-- Current assessed level --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700">Assessed Level</h2>
            @can('create', \App\Models\StudentEnglishLevelPlacement::class)
                @if ($programme)
                    <button type="button" wire:click="$toggle('showChangeLevel')" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                        {{ $current ? 'Change Level' : '+ Record Level' }}
                    </button>
                @endif
            @endcan
        </div>

        @if ($current)
            <p class="text-lg font-medium text-slate-900">{{ $current->englishLevel->name }}</p>
            <p class="text-xs text-slate-500">
                Since {{ $current->started_on->format('d M Y') }}
                @if ($current->assessed_on) &middot; assessed {{ $current->assessed_on->format('d M Y') }} @endif
                @if ($current->placement_reason) &middot; {{ $current->placement_reason }} @endif
            </p>
        @else
            <p class="text-sm text-slate-500">No level recorded yet.</p>
        @endif

        @if ($showChangeLevel)
            <form wire:submit="placeLevel" class="mt-4 space-y-3 rounded-lg bg-slate-50 p-3">
                <div class="flex flex-col gap-3 sm:flex-row">
                    <div class="flex-1">
                        <label class="mb-1 block text-xs font-medium text-slate-600">Level</label>
                        <select wire:model="english_level_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                            <option value="">Select a level&hellip;</option>
                            @foreach ($eligibleLevels as $level)
                                <option value="{{ $level->id }}">{{ $level->name }}</option>
                            @endforeach
                        </select>
                        @error('english_level_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:w-40">
                        <label class="mb-1 block text-xs font-medium text-slate-600">Effective From</label>
                        <input type="date" wire:model="started_on" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        @error('started_on') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:w-40">
                        <label class="mb-1 block text-xs font-medium text-slate-600">Assessed On</label>
                        <input type="date" wire:model="assessed_on" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        @error('assessed_on') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Reason (optional)</label>
                    <input type="text" wire:model="placement_reason" placeholder="e.g. End-of-semester assessment" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Notes (optional)</label>
                    <textarea wire:model="notes" rows="2" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
                </div>
                <p class="text-xs text-slate-500">
                    The current level is closed the day before this one starts. This records the assessment only &mdash; it does not move the student between teaching groups.
                </p>
                <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Save Level</button>
            </form>
        @endif
    </div>

    {{-- Where they actually sit --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-1 text-sm font-semibold text-slate-700">Current Teaching Groups</h2>
        <p class="mb-3 text-xs text-slate-500">
            Which group a student attends is a separate decision from their assessed level, and the two are allowed to differ.
        </p>
        @if ($activeGroups->isEmpty())
            <p class="text-sm text-slate-500">Not in any teaching group.</p>
        @else
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($activeGroups as $membership)
                    <li class="flex items-center justify-between gap-2 py-2">
                        <a href="{{ route('teaching-groups.show', $membership->teachingGroup) }}" class="truncate text-slate-700 hover:text-brand-navy">
                            {{ $membership->teachingGroup->name }}
                        </a>
                        <span class="flex-shrink-0 text-xs text-slate-500">
                            {{ $membership->teachingGroup->englishLevel?->name ?? 'general' }}
                            &middot; since {{ $membership->started_on->format('d M Y') }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- History --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-3 text-sm font-semibold text-slate-700">Placement History</h2>
        @if ($history->isEmpty())
            <p class="text-sm text-slate-500">No placement history.</p>
        @else
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($history as $placement)
                    <li class="flex items-start justify-between gap-2 py-2">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-900">{{ $placement->englishLevel->name }}</p>
                            @if ($placement->placement_reason)
                                <p class="text-xs text-slate-500">{{ $placement->placement_reason }}</p>
                            @endif
                        </div>
                        <span class="flex-shrink-0 text-xs text-slate-500">
                            {{ $placement->started_on->format('d M Y') }} &ndash;
                            {{ $placement->ended_on?->format('d M Y') ?? 'current' }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
