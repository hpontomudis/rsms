<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('teaching.modules.index', $teachingModule->class_subject_id) }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700">&larr; {{ $vocabulary['modules'] }}</a>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
                <div class="mb-1 flex flex-wrap items-center gap-2">
                    <x-status-badge :status="$teachingModule->status" />
                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600">{{ $teachingModule->rosterLabel() }}</span>
                </div>
                <h1 class="font-serif text-xl font-bold text-brand-navy">{{ $teachingModule->title }}</h1>
                <p class="text-sm text-slate-500">
                    {{ $teachingModule->rosterName() }} &middot; {{ $teachingModule->subject->name }} &middot; {{ $teachingModule->curriculumScope->displayName() }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    Written by {{ $teachingModule->classSubject->teacher?->fullName() }}
                    @if ($journalCount > 0)
                        &middot; taught in {{ $journalCount }} {{ Str::plural('session', $journalCount) }}
                    @endif
                </p>
            </div>

            @if ($canTransition)
                <div class="flex flex-shrink-0 flex-wrap gap-2">
                    @if ($teachingModule->isDraft())
                        <button type="button" wire:click="markReady" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Mark ready</button>
                    @elseif ($teachingModule->isReady())
                        @if ($journalCount === 0)
                            <button type="button" wire:click="returnToDraft" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Back to draft</button>
                        @endif
                        <button type="button" wire:click="archive" wire:confirm="Archive this module?" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Archive</button>
                    @endif
                </div>
            @endif
        </div>

        @if ($teachingModule->isReady())
            <p class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-xs text-emerald-800 ring-1 ring-emerald-200">
                Ready to teach, so the plan is frozen.
                {{ $journalCount > 0 ? 'A journal already refers to it, so it stays that way.' : 'Return it to draft to change it.' }}
            </p>
        @elseif ($teachingModule->isArchived())
            <p class="mt-3 rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600 ring-1 ring-slate-200">Archived and read-only.</p>
        @endif

        @error('status') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        @error('objectives') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        @error('slots') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        @error('curriculum_scope_id') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    {{-- The plan --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-3 text-sm font-semibold text-slate-700">The plan</h2>

        @if ($canEdit && $teachingModule->isDraft())
            <form wire:submit="savePlan" class="space-y-3">
                @foreach ([
                    'title' => ['Title', 'input'],
                    'topic' => ['Topic', 'input'],
                    'planned_activity' => ['Planned activity', 'textarea'],
                    'teaching_strategy' => ['Method and approach', 'textarea'],
                    'resources' => ['Resources and media', 'textarea'],
                    'differentiation' => ['Differentiation and support', 'textarea'],
                    'planned_assessment' => ['Planned assessment', 'textarea'],
                ] as $field => [$label, $type])
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">{{ $label }}</label>
                        @if ($type === 'input')
                            <input type="text" wire:model="plan.{{ $field }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        @else
                            <textarea wire:model="plan.{{ $field }}" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
                        @endif
                        @error('plan.'.$field) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endforeach
                <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Save plan</button>
            </form>
        @else
            <dl class="space-y-3 text-sm">
                @foreach ([
                    'topic' => 'Topic',
                    'planned_activity' => 'Planned activity',
                    'teaching_strategy' => 'Method and approach',
                    'resources' => 'Resources and media',
                    'differentiation' => 'Differentiation and support',
                    'planned_assessment' => 'Planned assessment',
                ] as $field => $label)
                    @if ($teachingModule->$field)
                        <div>
                            <dt class="text-xs font-medium text-slate-500">{{ $label }}</dt>
                            <dd class="whitespace-pre-line text-slate-700">{{ $teachingModule->$field }}</dd>
                        </div>
                    @endif
                @endforeach
            </dl>
        @endif
    </div>

    {{-- Objectives --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-1 text-sm font-semibold text-slate-700">{{ $vocabulary['objectives'] }}</h2>
        <p class="mb-3 text-xs text-slate-500">What this module sets out to teach. The text itself lives in the curriculum, not here.</p>

        <ul class="mb-3 divide-y divide-slate-100 text-sm">
            @forelse ($objectives as $objective)
                <li class="flex flex-wrap items-start justify-between gap-2 py-2">
                    <span class="min-w-0 text-slate-700">
                        @if ($objective->code)<span class="text-xs text-slate-500">{{ $objective->code }}</span> @endif
                        {{ $objective->objective_text }}
                    </span>
                    @if ($canEdit && $teachingModule->isDraft())
                        <button type="button" wire:click="removeObjective({{ $objective->id }})" class="flex-shrink-0 text-xs text-red-500 hover:text-red-700">Remove</button>
                    @endif
                </li>
            @empty
                <li class="py-2 text-slate-500">None linked yet. At least one is needed before this module can be marked ready.</li>
            @endforelse
        </ul>

        @if ($canEdit && $teachingModule->isDraft() && $available->isNotEmpty())
            <form wire:submit="addObjective" class="flex flex-wrap gap-2">
                <select wire:model="learning_objective_id" class="min-w-0 flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    <option value="">Add an objective&hellip;</option>
                    @foreach ($available as $objective)
                        <option value="{{ $objective->id }}">{{ $objective->code ? $objective->code.' — ' : '' }}{{ Str::limit($objective->objective_text, 60) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Add</button>
            </form>
            @error('learning_objective_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        @endif
    </div>

    {{-- Scheduled slots --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-1 text-sm font-semibold text-slate-700">Planned for</h2>
        <p class="mb-3 text-xs text-slate-500">
            Which scheduled meetings this design is meant to serve. A module may cover some slots of an objective and not others.
        </p>

        <ul class="mb-3 divide-y divide-slate-100 text-sm">
            @forelse ($slotLinks as $link)
                @php $slot = $link->semesterProgrammeItem; @endphp
                <li class="flex flex-wrap items-start justify-between gap-2 py-2">
                    <span class="text-slate-700">
                        {{ $slot->semesterProgramme->academicPeriod->name }}
                        &middot; {{ $slot->week_label ?? 'Slot '.$slot->position }}
                        @if ($slot->planned_lesson_periods)<span class="text-xs text-slate-500">{{ $slot->planned_lesson_periods }} JP</span>@endif
                    </span>
                    @if ($canEdit && $teachingModule->isDraft())
                        <button type="button" wire:click="removeSlot({{ $slot->id }})" class="text-xs text-red-500 hover:text-red-700">Remove</button>
                    @endif
                </li>
            @empty
                <li class="py-2 text-slate-500">Not tied to any scheduled slot. That is fine &mdash; a module may be written before the semester is scheduled.</li>
            @endforelse
        </ul>

        @if ($canEdit && $teachingModule->isDraft() && $candidateSlots->isNotEmpty())
            <form wire:submit="addSlot" class="flex flex-wrap gap-2">
                <select wire:model="semester_programme_item_id" class="min-w-0 flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    <option value="">Add a scheduled slot&hellip;</option>
                    @foreach ($candidateSlots as $slot)
                        <option value="{{ $slot->id }}">
                            {{ $slot->semesterProgramme->academicPeriod->name }} — {{ $slot->week_label ?? 'Slot '.$slot->position }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Add</button>
            </form>
            @error('semester_programme_item_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        @endif
    </div>

    {{-- Teacher notes: not the plan, so still editable once ready --}}
    @if ($canEdit && ! $teachingModule->isArchived())
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-1 text-sm font-semibold text-slate-700">Preparation notes</h2>
            <p class="mb-3 text-xs text-slate-500">Margin notes rather than the plan, so they stay editable after the module is ready.</p>
            <form wire:submit="saveNotes" class="space-y-2">
                <textarea wire:model="teacher_notes" rows="2" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
                <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Save notes</button>
            </form>
        </div>
    @elseif ($teachingModule->teacher_notes)
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-1 text-sm font-semibold text-slate-700">Preparation notes</h2>
            <p class="whitespace-pre-line text-sm text-slate-700">{{ $teachingModule->teacher_notes }}</p>
        </div>
    @endif
</div>
