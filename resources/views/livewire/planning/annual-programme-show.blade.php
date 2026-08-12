<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('my-teaching') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; My Teaching</a>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
                <div class="mb-1 flex flex-wrap items-center gap-2">
                    <x-status-badge :status="$annualProgramme->status" />
                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600">{{ $annualProgramme->rosterLabel() }}</span>
                </div>
                <h1 class="font-serif text-xl font-bold text-brand-navy">{{ $vocabulary['annual'] }}</h1>
                <p class="text-sm text-slate-500">
                    {{ $annualProgramme->rosterName() }} &middot; {{ $annualProgramme->subject->name }} &middot; {{ $annualProgramme->academicYear->name }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    Following <span class="text-slate-700">{{ $annualProgramme->learningPathway->title }}</span>
                    ({{ $annualProgramme->curriculumScope->displayName() }})
                </p>
            </div>

            @if ($canTransition)
                <div class="flex flex-shrink-0 gap-2">
                    @if ($annualProgramme->isDraft())
                        <button type="button" wire:click="activate" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Activate</button>
                    @elseif ($annualProgramme->isActive())
                        <button type="button" wire:click="archive" wire:confirm="Archive this programme? Its semester plans stay readable."
                            class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Archive</button>
                    @endif
                </div>
            @endif
        </div>

        @if ($annualProgramme->isArchived())
            <p class="mt-3 rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600 ring-1 ring-slate-200">
                This programme is archived and read-only.
            </p>
        @elseif ($annualProgramme->isActive() && $canEdit)
            <p class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-xs text-emerald-800 ring-1 ring-emerald-200">
                In force &mdash; and still editable. A school year shifts, so allocations may be adjusted; every change is recorded.
            </p>
        @endif

        @error('items') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        @error('status') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        @error('learning_pathway_id') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    @if ($canEdit)
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-700">Allocate an objective</h2>
                <button type="button" wire:click="$toggle('showAddItem')" class="text-xs font-medium text-brand-navy hover:underline">+ Add</button>
            </div>

            @if ($showAddItem)
                <form wire:submit="addItem" class="space-y-3 rounded-lg bg-slate-50 p-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">{{ $vocabulary['objective'] }}</label>
                        <select wire:model="learning_pathway_item_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                            <option value="">Select&hellip;</option>
                            @foreach ($addableItems as $item)
                                <option value="{{ $item->id }}">
                                    {{ $item->position }}. {{ $item->learningObjective->code ? $item->learningObjective->code.' — ' : '' }}{{ Str::limit($item->learningObjective->objective_text, 55) }}
                                </option>
                            @endforeach
                        </select>
                        @error('learning_pathway_item_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        @if ($addableItems->isEmpty())
                            <p class="mt-1 text-xs text-amber-700">Every objective in this pathway is already allocated.</p>
                        @endif
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Period</label>
                            <select wire:model="academic_period_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                                <option value="">Select&hellip;</option>
                                @foreach ($periods as $period)
                                    <option value="{{ $period->id }}">{{ $period->name }}</option>
                                @endforeach
                            </select>
                            @error('academic_period_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">JP budget (optional)</label>
                            <input type="number" min="1" wire:model="planned_lesson_periods" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                            @error('planned_lesson_periods') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            <p class="mt-1 text-xs text-slate-500">Total for the period, not per week.</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Allocate</button>
                        <button type="button" wire:click="$toggle('showAddItem')" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-white">Cancel</button>
                    </div>
                </form>
            @endif
        </div>
    @endif

    {{-- Grouped by period: the question a Prota exists to answer. --}}
    @foreach ($periods as $period)
        @php $items = $itemsByPeriod->get($period->id, collect()); @endphp
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-700">{{ $period->name }}</h2>
                <div class="flex items-center gap-2 text-xs">
                    <span class="text-slate-500">{{ $items->count() }} {{ Str::plural('objective', $items->count()) }}</span>
                    @if ($items->isNotEmpty() && $canEdit)
                        <button type="button" wire:click="planPeriod({{ $period->id }})" class="font-medium text-brand-navy hover:underline">
                            {{ $semesterProgrammes->has($period->id) ? 'Open' : 'Plan' }} {{ $vocabulary['semester'] }}
                        </button>
                    @elseif ($semesterProgrammes->has($period->id))
                        <a href="{{ route('planning.semester.show', $semesterProgrammes[$period->id]) }}" class="font-medium text-brand-navy hover:underline">{{ $vocabulary['semester'] }}</a>
                    @endif
                </div>
            </div>

            @if ($lockedPeriods->contains($period->id) && $canEdit)
                <p class="mb-2 rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600 ring-1 ring-slate-200">
                    {{ $period->name }}'s semester plan is in force. New objectives and budget changes have to go through
                    that plan, so it never falls out of step with this one.
                </p>
            @endif

            @forelse ($items as $item)
                <div class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 py-2 last:border-0">
                    <div class="min-w-0">
                        <p class="text-sm text-slate-700">
                            <span class="text-xs text-slate-400">{{ $item->learningPathwayItem->position }}.</span>
                            @if ($item->learningObjective()?->code)<span class="text-xs text-slate-500">{{ $item->learningObjective()->code }}</span> @endif
                            {{ $item->learningObjective()?->objective_text }}
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ $item->planned_lesson_periods ? $item->planned_lesson_periods.' JP' : 'no JP budget' }}
                            @if ($item->semesterItems->isNotEmpty())
                                &middot; scheduled in {{ $item->semesterItems->count() }} {{ Str::plural('slot', $item->semesterItems->count()) }}
                            @endif
                        </p>
                        @if ($item->notes)<p class="mt-0.5 text-xs italic text-slate-500">{{ $item->notes }}</p>@endif
                    </div>
                    @if ($canEdit)
                        <div class="flex flex-shrink-0 gap-2 text-xs">
                            <button type="button" wire:click="startEditingItem({{ $item->id }})" class="font-medium text-brand-navy hover:underline">Edit</button>
                            @if ($item->semesterItems->isEmpty())
                                <button type="button" wire:click="removeItem({{ $item->id }})"
                                    wire:confirm="Remove this objective from the annual plan?"
                                    class="text-red-500 hover:text-red-700">Remove</button>
                            @endif
                        </div>
                    @endif

                    @if ($editingItemId === $item->id)
                        <form wire:submit="saveItem" class="w-full space-y-3 rounded-lg bg-slate-50 p-3">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-600">Period</label>
                                    <select wire:model="academic_period_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                                        @foreach ($periods as $p)
                                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-600">JP budget</label>
                                    <input type="number" min="1" wire:model="planned_lesson_periods" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                                    <p class="mt-1 text-xs text-slate-500">Blank removes the budget, and with it the reconciliation rule.</p>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Note (optional)</label>
                                <input type="text" wire:model="item_notes" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                            </div>
                            @error('academic_period_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            @error('planned_lesson_periods') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Save</button>
                                <button type="button" wire:click="cancel" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-white">Cancel</button>
                            </div>
                        </form>
                    @endif
                </div>
            @empty
                <p class="text-sm text-slate-500">Nothing allocated to {{ $period->name }} yet.</p>
            @endforelse
        </div>
    @endforeach

    <p class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
        Teaching modules and daily journals are not implemented yet. A semester slot says when;
        a module will say how, and a journal what actually happened.
    </p>
</div>
