@php
    // One card renderer for both roster sources -- the model already answers
    // "what is this called" and "what kind of thing is it", so the only
    // source-specific part is the English programme line.
    $card = function ($assignment, bool $closed) use ($canAssess, $rosterCount, $programmeFor) {
        $level = $assignment->teachingGroup?->englishLevel;
        $plan = $programmeFor($assignment);

        return [
            'assignment' => $assignment,
            'level' => $level,
            'programme' => $level?->programme,
            'closed' => $closed,
            'canAssess' => $canAssess($assignment),
            'students' => $rosterCount($assignment),
            'plan' => $plan,
            // The semester plan for the period we are actually in, if it exists.
            'currentSemester' => $plan?->semesterProgrammes->first(fn ($sp) =>
                $sp->academicPeriod
                && today()->between($sp->academicPeriod->start_date, $sp->academicPeriod->end_date)),
        ];
    };
@endphp

<div class="mx-auto max-w-2xl space-y-4">
    <div class="flex flex-col gap-3 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="font-serif text-xl font-bold text-brand-navy">My Teaching Assignments</h1>
            <p class="text-sm text-slate-500">Your classes and teaching groups.</p>
        </div>
        <select wire:model.live="academic_year_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy sm:w-48">
            @foreach ($academicYears as $year)
                <option value="{{ $year->id }}">{{ $year->name }}{{ $year->is_current ? ' (current)' : '' }}</option>
            @endforeach
        </select>
    </div>

    @if ($problem === 'no_staff')
        <p class="rounded-lg bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200">
            Your login isn't linked to a staff profile yet, so there are no teaching assignments to show.
            An administrator can link them from the Staff screen.
        </p>
    @elseif ($problem === 'ambiguous_staff')
        <p class="rounded-lg bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-200">
            More than one staff profile is linked to your login, so we can't tell which one is yours.
            Nothing is shown rather than risk showing you someone else's work &mdash; please ask an
            administrator to correct the staff records.
        </p>
    @else
        {{-- Active --}}
        <div class="space-y-3">
            <h2 class="px-1 text-sm font-semibold text-slate-700">Active</h2>

            @forelse ($active as $assignment)
                @php $c = $card($assignment, false); @endphp
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-serif text-lg font-bold text-brand-navy">{{ $assignment->displayName() }}</span>
                                <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600">{{ $assignment->rosterLabel() }}</span>
                            </div>
                            <p class="mt-0.5 text-sm font-medium text-slate-800">{{ $assignment->subject->name }}</p>

                            {{-- Programme context is derived, never stored on the assignment.
                                 A class-backed English assignment simply has none. --}}
                            @if ($c['programme'])
                                <p class="mt-0.5 text-xs text-slate-500">{{ $c['programme']->name }} &middot; {{ $c['level']->name }}</p>
                            @endif

                            <p class="mt-1 text-xs text-slate-400">
                                {{ $assignment->academicYear()->name }}
                                &middot; since {{ $assignment->started_on->format('d M Y') }}
                                &middot; {{ $c['students'] }} {{ Str::plural('student', $c['students']) }}
                            </p>
                        </div>

                        @if ($c['canAssess'])
                            <a href="{{ route('assessments.index', ['class_subject_id' => $assignment->id]) }}"
                                class="flex-shrink-0 rounded-md bg-brand-navy px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-navy-light">
                                Assessments
                            </a>
                        @endif
                    </div>

                    {{-- Planning. The plan belongs to the roster, so a teacher
                         who took this over mid-year finds it already here. --}}
                    @if ($c['plan'] || $canPlan)
                        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-slate-100 pt-3 text-sm">
                            @if ($c['plan'])
                                <a href="{{ route('planning.annual.show', $c['plan']) }}" wire:navigate class="font-medium text-brand-navy hover:underline">
                                    Annual Programme
                                </a>
                                <x-status-badge :status="$c['plan']->status" />
                                @if ($c['currentSemester'])
                                    <a href="{{ route('planning.semester.show', $c['currentSemester']) }}" wire:navigate class="font-medium text-brand-navy hover:underline">
                                        Semester Programme &middot; {{ $c['currentSemester']->academicPeriod->name }}
                                    </a>
                                @endif
                            @elseif ($canPlan)
                                <a href="{{ route('planning.annual.create', ['class_subject_id' => $assignment->id]) }}" wire:navigate class="font-medium text-brand-navy hover:underline">
                                    + Start an annual programme
                                </a>
                            @endif
                        </div>
                    @endif

                    {{-- The teacher's own work: instructional design and the
                         record of what actually happened. --}}
                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                        <a href="{{ route('teaching.modules.index', $assignment) }}" wire:navigate class="font-medium text-brand-navy hover:underline">
                            Teaching Modules
                        </a>
                        <a href="{{ route('teaching.journal.index', $assignment) }}" wire:navigate class="font-medium text-brand-navy hover:underline">
                            Daily Journal
                        </a>
                    </div>
                </div>
            @empty
                <p class="rounded-lg bg-white p-4 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">
                    No active teaching assignments for this academic year.
                </p>
            @endforelse
        </div>

        {{-- Historical --}}
        @if ($historical->isNotEmpty())
            <div class="space-y-3">
                <h2 class="px-1 pt-2 text-sm font-semibold text-slate-700">Previous</h2>
                <p class="px-1 text-xs text-slate-500">
                    Handed over or ended. You can still read the work recorded under them, but not add anything new.
                </p>

                @foreach ($historical as $assignment)
                    @php $c = $card($assignment, true); @endphp
                    <div class="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium text-slate-700">{{ $assignment->displayName() }}</span>
                                    <span class="rounded bg-slate-200 px-1.5 py-0.5 text-xs text-slate-600">{{ $assignment->rosterLabel() }}</span>
                                </div>
                                <p class="mt-0.5 text-sm text-slate-600">{{ $assignment->subject->name }}</p>
                                @if ($c['programme'])
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $c['programme']->name }} &middot; {{ $c['level']->name }}</p>
                                @endif
                                <p class="mt-1 text-xs text-slate-400">
                                    {{ $assignment->academicYear()->name }}
                                    &middot; {{ $assignment->started_on->format('d M Y') }} &ndash; {{ $assignment->ended_on->format('d M Y') }}
                                </p>
                            </div>

                            @if ($c['canAssess'])
                                <a href="{{ route('assessments.index', ['class_subject_id' => $assignment->id]) }}"
                                    class="flex-shrink-0 rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-white">
                                    Assessments
                                </a>
                            @endif
                        </div>

                        {{-- Still readable after a handover: the plan stayed with
                             the class, and so did this teacher's view of it.
                             The modules and journals stayed with the teacher. --}}
                        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-slate-200 pt-3 text-sm">
                            @if ($c['plan'])
                                <a href="{{ route('planning.annual.show', $c['plan']) }}" wire:navigate class="font-medium text-brand-navy hover:underline">
                                    Annual Programme
                                </a>
                            @endif
                            <a href="{{ route('teaching.modules.index', $assignment) }}" wire:navigate class="font-medium text-brand-navy hover:underline">
                                Teaching Modules
                            </a>
                            <a href="{{ route('teaching.journal.index', $assignment) }}" wire:navigate class="font-medium text-brand-navy hover:underline">
                                Daily Journal
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
