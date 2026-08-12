<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('planning.annual.show', $annual) }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; {{ $vocabulary['annual'] }}</a>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
                <div class="mb-1"><x-status-badge :status="$semesterProgramme->status" /></div>
                <h1 class="font-serif text-xl font-bold text-brand-navy">{{ $vocabulary['semester'] }}</h1>
                <p class="text-sm text-slate-500">
                    {{ $annual->rosterName() }} &middot; {{ $annual->subject->name }} &middot; {{ $semesterProgramme->academicPeriod->name }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    {{ $semesterProgramme->academicPeriod->start_date->format('d M Y') }} &ndash;
                    {{ $semesterProgramme->academicPeriod->end_date->format('d M Y') }}
                </p>
            </div>

            @if ($canTransition)
                <div class="flex flex-shrink-0 gap-2">
                    @if ($semesterProgramme->isDraft())
                        <button type="button" wire:click="activate" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Activate</button>
                    @elseif ($semesterProgramme->isActive())
                        <button type="button" wire:click="archive" wire:confirm="Archive this semester plan?"
                            class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Archive</button>
                    @endif
                </div>
            @endif
        </div>

        @error('items') <p class="mt-3 text-xs text-red-600">{{ $message }}</p> @enderror
        @error('status') <p class="mt-3 text-xs text-red-600">{{ $message }}</p> @enderror
        @error('academic_period_id') <p class="mt-3 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    {{-- Coverage and JP reconciliation, visible before activation rather than
         only when it is refused. --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-3 text-sm font-semibold text-slate-700">Allocated this period</h2>
        <ul class="divide-y divide-slate-100 text-sm">
            @foreach ($allocated as $item)
                @php $s = $summary[$item->id]; @endphp
                <li class="flex flex-wrap items-start justify-between gap-2 py-2">
                    <span class="min-w-0 text-slate-700">
                        @if ($item->learningObjective()?->code)<span class="text-xs text-slate-500">{{ $item->learningObjective()->code }}</span> @endif
                        {{ Str::limit($item->learningObjective()?->objective_text, 70) }}
                    </span>
                    <span class="flex-shrink-0 text-xs">
                        @if ($s->slots === 0)
                            <span class="text-amber-700">not scheduled</span>
                        @else
                            <span class="text-slate-500">{{ $s->slots }} {{ Str::plural('slot', $s->slots) }}</span>
                            @if ($s->budget)
                                @if ($s->scheduled === null)
                                    <span class="text-amber-700">&middot; JP incomplete</span>
                                @elseif ($s->scheduled === (int) $s->budget)
                                    <span class="text-emerald-700">&middot; {{ $s->scheduled }}/{{ $s->budget }} JP</span>
                                @else
                                    <span class="text-red-600">&middot; {{ $s->scheduled }}/{{ $s->budget }} JP</span>
                                @endif
                            @endif
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-700">Schedule</h2>
            @if ($canEdit)
                <button type="button" wire:click="$toggle('showAddSlot')" class="text-xs font-medium text-brand-navy hover:underline">+ Add slot</button>
            @endif
        </div>
        <p class="mb-3 text-xs text-slate-500">One objective may take several slots &mdash; weeks 3, 4 and 6, for instance.</p>

        @if ($showAddSlot || $editingSlotId)
            <form wire:submit="{{ $editingSlotId ? 'saveSlot' : 'addSlot' }}" class="mb-4 space-y-3 rounded-lg bg-slate-50 p-3">
                @unless ($editingSlotId)
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">{{ $vocabulary['objective'] }}</label>
                        <select wire:model="annual_programme_item_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                            <option value="">Select&hellip;</option>
                            @foreach ($allocated as $item)
                                <option value="{{ $item->id }}">
                                    {{ $item->learningObjective()?->code ? $item->learningObjective()->code.' — ' : '' }}{{ Str::limit($item->learningObjective()?->objective_text, 55) }}
                                </option>
                            @endforeach
                        </select>
                        @error('annual_programme_item_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endunless

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Week label (optional)</label>
                        <input type="text" wire:model="week_label" placeholder="Week 3, Minggu Efektif 7&hellip;"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        @error('week_label') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">JP for this slot</label>
                        <input type="number" min="1" wire:model="planned_lesson_periods"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        @error('planned_lesson_periods') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Start (optional)</label>
                        <input type="date" wire:model="planned_start_date" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        @error('planned_start_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">End (optional)</label>
                        <input type="date" wire:model="planned_end_date" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        @error('planned_end_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Save</button>
                    <button type="button" wire:click="cancel" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-white">Cancel</button>
                </div>
            </form>
        @endif

        <ol class="space-y-3">
            @forelse ($scheduleSlots as $slot)
                <li class="rounded-lg border border-slate-200 p-3">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="mb-0.5 flex flex-wrap items-center gap-2">
                                <span class="rounded bg-brand-navy px-1.5 py-0.5 text-xs font-medium text-white">{{ $slot->position }}</span>
                                @if ($slot->week_label)<span class="text-xs font-medium text-slate-700">{{ $slot->week_label }}</span>@endif
                                @if ($slot->planned_lesson_periods)<span class="text-xs text-slate-500">{{ $slot->planned_lesson_periods }} JP</span>@endif
                            </div>
                            <p class="text-sm text-slate-700">
                                {{ $slot->annualProgrammeItem->learningPathwayItem->learningObjective->objective_text }}
                            </p>
                            @if ($slot->planned_start_date)
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ $slot->planned_start_date->format('d M Y') }}
                                    @if ($slot->planned_end_date) &ndash; {{ $slot->planned_end_date->format('d M Y') }} @endif
                                </p>
                            @endif
                            @if ($slot->notes)<p class="mt-1 text-xs italic text-slate-500">{{ $slot->notes }}</p>@endif
                        </div>

                        @if ($canEdit)
                            <div class="flex flex-shrink-0 flex-wrap items-center gap-2 text-xs">
                                <button type="button" wire:click="moveSlot({{ $slot->id }}, 'up')" class="text-slate-400 hover:text-slate-700" title="Move up">&uarr;</button>
                                <button type="button" wire:click="moveSlot({{ $slot->id }}, 'down')" class="text-slate-400 hover:text-slate-700" title="Move down">&darr;</button>
                                <button type="button" wire:click="startEditing({{ $slot->id }})" class="font-medium text-brand-navy hover:underline">Edit</button>
                                <button type="button" wire:click="removeSlot({{ $slot->id }})" wire:confirm="Remove this slot?" class="text-red-500 hover:text-red-700">Remove</button>
                            </div>
                        @endif
                    </div>
                </li>
            @empty
                <li class="text-sm text-slate-500">Nothing scheduled yet.</li>
            @endforelse
        </ol>
    </div>
</div>
