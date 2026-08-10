<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('teaching-groups.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Teaching Groups</a>

    <div class="flex flex-col gap-3 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="font-serif text-xl font-bold text-brand-navy">{{ $teachingGroup->name }}</h1>
            <p class="text-sm text-slate-500">{{ $teachingGroup->academicYear->name }}</p>
            @if ($teachingGroup->englishLevel)
                <p class="mt-1 text-sm text-slate-600">
                    {{ $teachingGroup->englishLevel->name }}
                    <span class="text-slate-400">&middot;</span>
                    {{ $teachingGroup->englishLevel->programme?->name }}
                </p>
            @else
                <p class="mt-1 text-sm text-slate-500">General group &mdash; not tied to an English level.</p>
            @endif
            <div class="mt-2"><x-status-badge :status="$teachingGroup->status" /></div>
        </div>
        @can('update', $teachingGroup)
            <a href="{{ route('teaching-groups.edit', $teachingGroup) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Edit</a>
        @endcan
    </div>

    {{-- Active roster --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700">Current Students</h2>
            @can('update', $teachingGroup)
                @if ($teachingGroup->isActive())
                    <button type="button" wire:click="$toggle('showAddStudent')" class="text-sm font-medium text-slate-600 hover:text-slate-900">+ Add Student</button>
                @endif
            @endcan
        </div>

        @if ($showAddStudent)
            <form wire:submit="addStudent" class="mb-4 space-y-3 rounded-lg bg-slate-50 p-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Student</label>
                    <select wire:model="student_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        <option value="">Select a student&hellip;</option>
                        @foreach ($eligibleStudents as $student)
                            <option value="{{ $student->id }}">{{ $student->fullName() }} ({{ $student->student_number }})</option>
                        @endforeach
                    </select>
                    @if ($eligibleStudents->isEmpty())
                        <p class="mt-1 text-xs text-amber-700">
                            No eligible students. For an English group, a student needs an active class in this academic year, a grade covered by the same programme, and no other open membership in that programme.
                        </p>
                    @endif
                    @error('student_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="sm:w-44">
                        <label class="mb-1 block text-xs font-medium text-slate-600">Started On</label>
                        <input type="date" wire:model="started_on" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        @error('started_on') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex-1">
                        <label class="mb-1 block text-xs font-medium text-slate-600">Notes (optional)</label>
                        <input type="text" wire:model="member_notes" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    </div>
                    <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Add</button>
                </div>
            </form>
        @endif

        <ul class="divide-y divide-slate-100 text-sm">
            @forelse ($activeMembers as $membership)
                <li class="py-2">
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-900">{{ $membership->student->fullName() }}</p>
                            <p class="text-xs text-slate-500">
                                Since {{ $membership->started_on->format('d M Y') }}
                                @if ($membership->notes) &middot; {{ $membership->notes }} @endif
                            </p>
                        </div>
                        @can('update', $teachingGroup)
                            <button type="button" wire:click="startEnding({{ $membership->id }})" class="flex-shrink-0 text-xs text-slate-500 hover:text-slate-800">End</button>
                        @endcan
                    </div>

                    @if ($endingMembershipId === $membership->id)
                        <form wire:submit="endMembership" class="mt-2 flex flex-col gap-2 rounded-lg bg-slate-50 p-3 sm:flex-row sm:items-end">
                            <div class="sm:w-44">
                                <label class="mb-1 block text-xs font-medium text-slate-600">Ended On</label>
                                <input type="date" wire:model="ended_on" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                                @error('ended_on') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">End Membership</button>
                            <button type="button" wire:click="cancelEnding" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-white">Cancel</button>
                        </form>
                    @endif
                </li>
            @empty
                <li class="py-2 text-slate-500">No students in this group yet.</li>
            @endforelse
        </ul>
    </div>

    {{-- History --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-1 text-sm font-semibold text-slate-700">Past Members</h2>
        <p class="mb-3 text-xs text-slate-500">Membership is ended, never deleted &mdash; a student may leave and return later.</p>

        @if ($pastMembers->isEmpty())
            <p class="text-sm text-slate-500">Nobody has left this group.</p>
        @else
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($pastMembers as $membership)
                    <li class="flex items-center justify-between gap-2 py-2">
                        <span class="truncate text-slate-700">{{ $membership->student->fullName() }}</span>
                        <span class="flex-shrink-0 text-xs text-slate-500">
                            {{ $membership->started_on->format('d M Y') }} &ndash; {{ $membership->ended_on->format('d M Y') }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
