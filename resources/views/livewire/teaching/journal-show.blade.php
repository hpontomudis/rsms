<div class="mx-auto max-w-2xl space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <a href="{{ route('teaching.journal.index', $dailyJournal->class_subject_id) }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700">&larr; {{ $vocabulary['journal'] }}</a>
        <a href="{{ route('documents.daily-journal', $dailyJournal) }}" target="_blank" class="text-sm font-medium text-brand-navy hover:underline">Print</a>
    </div>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
                <div class="mb-1 flex flex-wrap items-center gap-2">
                    <x-status-badge :status="$dailyJournal->status" />
                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600">{{ $dailyJournal->rosterLabel() }}</span>
                </div>
                <h1 class="font-serif text-xl font-bold text-brand-navy">{{ $dailyJournal->journal_date->format('d M Y') }}</h1>
                <p class="text-sm text-slate-500">
                    {{ $dailyJournal->rosterName() }} &middot; {{ $dailyJournal->subject->name }} &middot; {{ $dailyJournal->academicPeriod->name }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    Conducted by {{ $dailyJournal->conductedBy?->fullName() }}
                    @if ($dailyJournal->wasSubstituted())
                        <span class="text-amber-700">&mdash; substituting for {{ $dailyJournal->classSubject->teacher?->fullName() }}</span>
                    @endif
                </p>
            </div>

            @if ($canFinalize)
                <button type="button" wire:click="finalize" class="flex-shrink-0 rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Finalize</button>
            @endif
        </div>

        @if ($dailyJournal->isFinalized())
            <p class="mt-3 rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600 ring-1 ring-slate-200">
                Finalized. This is the record of what happened; only a manager may correct it, and every correction is recorded.
            </p>
        @endif

        @foreach (['status', 'objectives', 'assessments', 'journal_date', 'academic_period_id', 'teaching_module_id', 'semester_programme_item_id', 'conducted_by_staff_id'] as $field)
            @error($field) <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        @endforeach
    </div>

    {{-- The record --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-3 text-sm font-semibold text-slate-700">What happened</h2>

        @if ($canEdit)
            <form wire:submit="save" class="space-y-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">What was taught</label>
                    <input type="text" wire:model="record.topic" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    @error('record.topic') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">What actually happened</label>
                    <textarea wire:model="record.actual_activity" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
                    @error('record.actual_activity') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Reflection</label>
                    <textarea wire:model="record.reflection" rows="2" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Follow-up</label>
                    <textarea wire:model="record.follow_up" rows="2" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Meeting number (optional)</label>
                        <input type="number" min="1" wire:model="record.meeting_number" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        <p class="mt-1 text-xs text-slate-500">Advisory only &mdash; sessions are ordered by date.</p>
                        @error('record.meeting_number') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Actual JP (optional)</label>
                        <input type="number" min="1" wire:model="record.actual_lesson_periods" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        @error('record.actual_lesson_periods') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Save</button>
            </form>
        @else
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs font-medium text-slate-500">What was taught</dt>
                    <dd class="text-slate-700">{{ $dailyJournal->topic }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-slate-500">What actually happened</dt>
                    <dd class="whitespace-pre-line text-slate-700">{{ $dailyJournal->actual_activity }}</dd>
                </div>
                @foreach (['reflection' => 'Reflection', 'follow_up' => 'Follow-up'] as $field => $label)
                    @if ($dailyJournal->$field)
                        <div>
                            <dt class="text-xs font-medium text-slate-500">{{ $label }}</dt>
                            <dd class="whitespace-pre-line text-slate-700">{{ $dailyJournal->$field }}</dd>
                        </div>
                    @endif
                @endforeach
                @if ($dailyJournal->actual_lesson_periods)
                    <div>
                        <dt class="text-xs font-medium text-slate-500">Actual JP</dt>
                        <dd class="text-slate-700">{{ $dailyJournal->actual_lesson_periods }}</dd>
                    </div>
                @endif
            </dl>
        @endif
    </div>

    {{-- Plan it came from --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-1 text-sm font-semibold text-slate-700">Planned from</h2>
        <p class="mb-3 text-xs text-slate-500">Optional. A catch-up or unplanned lesson is a real lesson.</p>

        @if ($canEdit)
            <div class="space-y-3">
                <form wire:submit="setModule" class="flex flex-wrap gap-2">
                    <select wire:model="teaching_module_id" class="min-w-0 flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        <option value="">No {{ strtolower($vocabulary['module']) }}</option>
                        @foreach ($modules as $module)
                            <option value="{{ $module->id }}">
                                {{ $module->title }} — {{ $module->classSubject->teacher?->fullName() }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Set</button>
                </form>
                <form wire:submit="setSlot" class="flex flex-wrap gap-2">
                    <select wire:model="semester_programme_item_id" class="min-w-0 flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        <option value="">No scheduled slot</option>
                        @foreach ($slots as $slot)
                            <option value="{{ $slot->id }}">{{ $slot->week_label ?? 'Slot '.$slot->position }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Set</button>
                </form>
            </div>
        @else
            <p class="text-sm text-slate-700">
                {{ $dailyJournal->teachingModule?->title ?? 'No module' }}
                @if ($dailyJournal->semesterProgrammeItem)
                    &middot; {{ $dailyJournal->semesterProgrammeItem->week_label ?? 'slot '.$dailyJournal->semesterProgrammeItem->position }}
                @endif
            </p>
        @endif
    </div>

    {{-- Actual objectives --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-1 text-sm font-semibold text-slate-700">{{ $vocabulary['objectives'] }} actually covered</h2>
        <p class="mb-3 text-xs text-slate-500">
            Recorded independently of the plan, so a lesson that reached fewer objectives than intended &mdash; or something
            unplanned &mdash; says so here.
        </p>

        <ul class="mb-3 divide-y divide-slate-100 text-sm">
            @forelse ($objectives as $objective)
                <li class="flex flex-wrap items-start justify-between gap-2 py-2">
                    <span class="min-w-0 text-slate-700">
                        @if ($objective->code)<span class="text-xs text-slate-500">{{ $objective->code }}</span> @endif
                        {{ $objective->objective_text }}
                    </span>
                    @if ($canEdit)
                        <button type="button" wire:click="removeObjective({{ $objective->id }})" class="flex-shrink-0 text-xs text-red-500 hover:text-red-700">Remove</button>
                    @endif
                </li>
            @empty
                <li class="py-2 text-slate-500">None recorded yet.</li>
            @endforelse
        </ul>

        @if ($canEdit && $available->isNotEmpty())
            <form wire:submit="addObjective" class="flex flex-wrap gap-2">
                <select wire:model="learning_objective_id" class="min-w-0 flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    <option value="">Add an objective&hellip;</option>
                    @foreach ($available as $objective)
                        <option value="{{ $objective->id }}">{{ $objective->code ? $objective->code.' — ' : '' }}{{ Str::limit($objective->objective_text, 60) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Add</button>
            </form>
        @endif
    </div>

    {{-- Assessment activity --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-1 text-sm font-semibold text-slate-700">Assessment used</h2>
        <p class="mb-3 text-xs text-slate-500">That it happened, not what anyone scored. Marks stay on the assessment.</p>

        <ul class="mb-3 divide-y divide-slate-100 text-sm">
            @forelse ($assessmentLinks as $link)
                <li class="flex flex-wrap items-start justify-between gap-2 py-2">
                    <a href="{{ route('assessments.show', $link->assessment) }}" class="text-brand-navy hover:underline">{{ $link->assessment->name }}</a>
                    @if ($canEdit)
                        <button type="button" wire:click="removeAssessment({{ $link->assessment_id }})" class="text-xs text-red-500 hover:text-red-700">Remove</button>
                    @endif
                </li>
            @empty
                <li class="py-2 text-slate-500">None recorded.</li>
            @endforelse
        </ul>

        @if ($canEdit && $availableAssessments->isNotEmpty())
            <form wire:submit="addAssessment" class="flex flex-wrap gap-2">
                <select wire:model="assessment_id" class="min-w-0 flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    <option value="">Add an assessment&hellip;</option>
                    @foreach ($availableAssessments as $assessment)
                        <option value="{{ $assessment->id }}">{{ $assessment->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Add</button>
            </form>
            @error('assessment_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        @endif
    </div>

    {{-- Attendance context: read-only, never stored --}}
    <div class="rounded-xl bg-slate-50 p-5 ring-1 ring-slate-200">
        <h2 class="mb-1 text-sm font-semibold text-slate-700">Attendance that day</h2>
        @if (! $dailyJournal->isClassBacked())
            <p class="text-xs text-slate-600">
                Attendance is recorded per administrative class, and teaching groups have no attendance of their own yet.
                Nothing is shown rather than implying an empty register.
            </p>
        @elseif ($attendance)
            <p class="text-sm text-slate-700">
                {{ $attendance->records->where('status', 'present')->count() }} present of {{ $attendance->records->count() }} recorded,
                taken by {{ $attendance->takenBy?->fullName() }}.
            </p>
            <p class="mt-1 text-xs text-slate-500">Shown for context only. A journal is not an attendance record and stores none of this.</p>
        @else
            <p class="text-xs text-slate-600">No class attendance was recorded on this date.</p>
        @endif
    </div>
</div>
