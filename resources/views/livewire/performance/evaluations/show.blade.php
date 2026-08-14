<div class="mx-auto max-w-3xl space-y-4">
    <a href="{{ route('performance.evaluations.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Performance Evaluations</a>

    @if (session('status'))
        <div class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700 ring-1 ring-emerald-200">{{ session('status') }}</div>
    @endif

    {{-- Header --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
                <h1 class="font-serif text-xl font-bold text-brand-navy">
                    {{ $evaluation->isFinalized() ? $evaluation->staff_name_snapshot : $evaluation->staff?->fullName() }}
                </h1>
                <p class="text-sm text-slate-500">
                    {{ $evaluation->isFinalized() ? $evaluation->staff_category_name_snapshot : $evaluation->staffCategory?->name }}
                    @if ($evaluation->isFinalized() && $evaluation->position_title_snapshot)
                        &middot; {{ $evaluation->position_title_snapshot }}
                    @endif
                </p>
                <p class="mt-1 text-sm text-slate-600">
                    {{ $evaluation->isFinalized() ? $evaluation->framework_name_snapshot : $evaluation->framework?->name }}
                    v{{ $evaluation->isFinalized() ? $evaluation->framework_version_snapshot : $evaluation->framework?->version }}
                    &middot; {{ $evaluation->period_start->format('d M Y') }} &ndash; {{ $evaluation->period_end->format('d M Y') }}
                </p>
            </div>
            <div class="text-right">
                <x-status-badge :status="$evaluation->status" />
                @if ($evaluation->isFinalized())
                    <p class="mt-2 text-xs text-slate-500">
                        Finalized {{ $evaluation->finalized_at->format('d M Y') }}
                        by {{ $evaluation->evaluator_name_snapshot }}
                    </p>
                @else
                    <p class="mt-2 text-xs text-slate-500">Evaluator: {{ $evaluation->evaluator?->name }}</p>
                @endif
            </div>
        </div>
    </div>

    @if ($evaluation->isFinalized())
        {{-- ============================================================ FINALIZED, READ ONLY ============================================================ --}}

        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Overall Rating</h2>
            @if ($evaluation->overallRatingOption)
                <p class="text-lg font-semibold text-brand-navy">{{ $evaluation->overallRatingOption->label }}</p>
            @else
                <p class="text-sm text-slate-500">No overall rating recorded.</p>
            @endif
        </div>

        @foreach ($finalizedItems->groupBy('section_name_snapshot') as $sectionName => $sectionItems)
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">{{ $sectionName }}</h2>
                <div class="space-y-4">
                    @foreach ($sectionItems as $item)
                        <div class="border-b border-slate-100 pb-4 last:border-0 last:pb-0">
                            <p class="font-medium text-slate-900">{{ $item->indicator_name_snapshot }}</p>
                            @if ($item->indicator_description_snapshot)
                                <p class="text-xs text-slate-500">{{ $item->indicator_description_snapshot }}</p>
                            @endif

                            <div class="mt-2 rounded-md bg-slate-50 p-3 text-sm">
                                @if ($item->indicator_type_snapshot === 'rubric')
                                    <span class="font-medium text-slate-800">{{ $item->rating_label_snapshot }}</span>
                                @elseif ($item->indicator_type_snapshot === 'numeric')
                                    <span class="font-medium text-slate-800">{{ $item->numeric_value }}</span>
                                @elseif ($item->indicator_type_snapshot === 'boolean')
                                    <span class="font-medium text-slate-800">{{ $item->boolean_value ? 'Yes' : 'No' }}</span>
                                @else
                                    <p class="whitespace-pre-line text-slate-800">{{ $item->narrative_response }}</p>
                                @endif
                            </div>
                            @if ($item->evaluator_comment)
                                <p class="mt-2 text-sm italic text-slate-600">&ldquo;{{ $item->evaluator_comment }}&rdquo;</p>
                            @endif

                            @if ($item->evidence->isNotEmpty())
                                <div class="mt-2 space-y-1">
                                    @foreach ($item->evidence as $evidence)
                                        <div class="rounded-md border border-slate-200 px-3 py-2 text-xs text-slate-600">
                                            <span class="font-medium">{{ $evidence->source_label }}</span>
                                            @if ($evidence->isSystem())
                                                @if ($evidence->isAvailable())
                                                    &mdash; {{ $evidence->numeric_value ?? $evidence->text_value ?? ($evidence->boolean_value ? 'Yes' : 'No') }}
                                                @else
                                                    &mdash; unavailable ({{ $evidence->note }})
                                                @endif
                                            @else
                                                &mdash; {{ $evidence->note }}
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        @if ($evaluation->summary || $evaluation->strengths || $evaluation->development_priorities || $evaluation->action_plan)
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">Development Plan</h2>
                <dl class="space-y-3 text-sm">
                    @if ($evaluation->summary)
                        <div><dt class="font-medium text-slate-600">Summary</dt><dd class="whitespace-pre-line text-slate-800">{{ $evaluation->summary }}</dd></div>
                    @endif
                    @if ($evaluation->strengths)
                        <div><dt class="font-medium text-slate-600">Strengths</dt><dd class="whitespace-pre-line text-slate-800">{{ $evaluation->strengths }}</dd></div>
                    @endif
                    @if ($evaluation->development_priorities)
                        <div><dt class="font-medium text-slate-600">Development priorities</dt><dd class="whitespace-pre-line text-slate-800">{{ $evaluation->development_priorities }}</dd></div>
                    @endif
                    @if ($evaluation->action_plan)
                        <div><dt class="font-medium text-slate-600">Action plan</dt><dd class="whitespace-pre-line text-slate-800">{{ $evaluation->action_plan }}</dd></div>
                    @endif
                    @if ($evaluation->review_date)
                        <div><dt class="font-medium text-slate-600">Review date</dt><dd class="text-slate-800">{{ $evaluation->review_date->format('d M Y') }}</dd></div>
                    @endif
                </dl>
            </div>
        @endif
    @else
        {{-- ============================================================ DRAFT ============================================================ --}}

        @foreach ($preview->groupBy(fn ($row) => $row['item']->indicator->section->name) as $sectionName => $rows)
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">{{ $sectionName }}</h2>
                <div class="space-y-5">
                    @foreach ($rows as $row)
                        @php $item = $row['item']; $indicator = $item->indicator; $evidence = $row['evidence']; @endphp
                        <div class="border-b border-slate-100 pb-5 last:border-0 last:pb-0">
                            <p class="font-medium text-slate-900">{{ $indicator->name }}</p>
                            @if ($indicator->description)
                                <p class="text-xs text-slate-500">{{ $indicator->description }}</p>
                            @endif

                            {{-- System evidence: context only, visually subordinate, never a suggested rating. --}}
                            @if ($evidence)
                                <div class="mt-2 rounded-md border border-sky-100 bg-sky-50 px-3 py-2 text-xs text-sky-800">
                                    <span class="font-medium">{{ $evidence->label }}:</span>
                                    @if ($evidence->isAvailable())
                                        {{ $evidence->numericValue ?? $evidence->textValue ?? ($evidence->booleanValue ? 'Yes' : 'No') }}
                                    @else
                                        unavailable &mdash; {{ $evidence->note }}
                                    @endif
                                    <p class="mt-0.5 text-sky-700/80">System record for context. It does not set or suggest the rating.</p>
                                </div>
                            @endif

                            {{-- Manual evidence --}}
                            @if ($item->evidence->isNotEmpty())
                                <div class="mt-2 space-y-1">
                                    @foreach ($item->evidence as $manual)
                                        <div class="flex items-start justify-between gap-2 rounded-md border border-slate-200 px-3 py-2 text-xs text-slate-600">
                                            <span><span class="font-medium">{{ $manual->source_label }}:</span> {{ $manual->note }}</span>
                                            @if ($canEdit)
                                                <button type="button" wire:click="removeEvidence({{ $manual->id }})" wire:confirm="Remove this evidence?" class="flex-shrink-0 text-red-500 hover:text-red-700">Remove</button>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if ($canEdit)
                                @if ($addEvidenceItemId === $item->id)
                                    <form wire:submit="saveEvidence" class="mt-2 space-y-2 rounded-md bg-slate-50 p-3">
                                        <input type="text" wire:model="evidence_label" placeholder="Label" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy" />
                                        @error('evidence_label') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                        <textarea wire:model="evidence_note" rows="2" placeholder="What was observed" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
                                        @error('evidence_note') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                        <div class="flex gap-2">
                                            <button type="submit" class="rounded-md bg-brand-navy px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-navy-light">Add evidence</button>
                                            <button type="button" wire:click="cancelAddEvidence" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-50">Cancel</button>
                                        </div>
                                    </form>
                                @else
                                    <button type="button" wire:click="startAddEvidence({{ $item->id }})" class="mt-2 text-xs font-medium text-brand-navy hover:underline">+ Add manual evidence</button>
                                @endif
                            @endif

                            {{-- Response --}}
                            <div class="mt-3">
                                @if ($canEdit)
                                    <form wire:submit="respond({{ $item->id }})" class="space-y-2">
                                        @if ($indicator->indicator_type === 'rubric')
                                            <select wire:model="responses.{{ $item->id }}.value" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                                                <option value="">Select a rating&hellip;</option>
                                                @foreach ($ratingOptions as $option)
                                                    <option value="{{ $option->id }}">{{ $option->label }}</option>
                                                @endforeach
                                            </select>
                                        @elseif ($indicator->indicator_type === 'numeric')
                                            <input type="number" step="0.01" wire:model="responses.{{ $item->id }}.value"
                                                placeholder="{{ $indicator->unit_label ? 'Value ('.$indicator->unit_label.')' : 'Value' }}"
                                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy" />
                                        @elseif ($indicator->indicator_type === 'boolean')
                                            <select wire:model="responses.{{ $item->id }}.value" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                                                <option value="">Not yet answered</option>
                                                <option value="1">Yes</option>
                                                <option value="0">No</option>
                                            </select>
                                        @else
                                            <textarea wire:model="responses.{{ $item->id }}.value" rows="3" placeholder="Narrative response"
                                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
                                        @endif
                                        @error("responses.{$item->id}.value") <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                        @error('rating_option_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                        @error('numeric_value') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                        @error('boolean_value') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                        @error('narrative_response') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                                        <input type="text" wire:model="responses.{{ $item->id }}.evaluator_comment" placeholder="Comment (optional)"
                                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy" />

                                        <button type="submit" class="rounded-md bg-brand-navy px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-navy-light">Save response</button>
                                    </form>
                                @else
                                    <p class="text-sm text-slate-600">
                                        @if ($indicator->indicator_type === 'rubric')
                                            {{ $item->ratingOption?->label ?? 'No response yet.' }}
                                        @elseif ($indicator->indicator_type === 'numeric')
                                            {{ $item->numeric_value ?? 'No response yet.' }}
                                        @elseif ($indicator->indicator_type === 'boolean')
                                            {{ $item->boolean_value === null ? 'No response yet.' : ($item->boolean_value ? 'Yes' : 'No') }}
                                        @else
                                            {{ $item->narrative_response ?? 'No response yet.' }}
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        @if ($canEdit)
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">Overall Rating</h2>
                <form wire:submit="setOverallRating" class="flex flex-wrap items-end gap-2">
                    <select wire:model="overall_rating_option_id" class="min-w-0 flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        <option value="">Select&hellip;</option>
                        @foreach ($ratingOptions as $option)
                            <option value="{{ $option->id }}">{{ $option->label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Save</button>
                </form>
                @error('overall_rating_option_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <p class="mt-2 text-xs text-slate-500">
                    This is your overall professional judgement, not a calculation from the responses above.
                </p>
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">Development Plan</h2>
                <form wire:submit="saveDevelopmentPlan" class="space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Summary</label>
                        <textarea wire:model="summary" rows="2" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Strengths</label>
                        <textarea wire:model="strengths" rows="2" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Development priorities</label>
                        <textarea wire:model="development_priorities" rows="2" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Action plan</label>
                        <textarea wire:model="action_plan" rows="2" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Review date</label>
                        <input type="date" wire:model="review_date" class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy" />
                    </div>
                    <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Save development plan</button>
                </form>
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="mb-2 text-sm font-semibold text-slate-700">Finalize</h2>
                @error('status') <p class="mb-2 text-xs text-red-600">{{ $message }}</p> @enderror
                @error('overall_rating_option_id') <p class="mb-2 text-xs text-red-600">{{ $message }}</p> @enderror
                <p class="mb-3 text-xs text-slate-500">
                    Finalizing snapshots everything as it stands right now and freezes this record permanently.
                    There is no correction, replacement or supersession workflow in this version &mdash; check the
                    responses and rating carefully first.
                </p>
                <div class="flex flex-wrap gap-3">
                    @can('finalize', $evaluation)
                        <button type="button" wire:click="finalize" wire:confirm="Finalize this evaluation? It cannot be edited or undone afterwards."
                            class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Finalize</button>
                    @endcan
                    @can('delete', $evaluation)
                        <button type="button" wire:click="deleteDraft" wire:confirm="Delete this draft evaluation? This cannot be undone."
                            class="rounded-md px-4 py-2 text-sm text-red-600 hover:bg-red-50">Delete draft</button>
                    @endcan
                </div>
            </div>
        @endif
    @endif
</div>
