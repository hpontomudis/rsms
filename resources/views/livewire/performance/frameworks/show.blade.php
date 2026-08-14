<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('performance.frameworks.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Performance Frameworks</a>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
                <h1 class="font-serif text-xl font-bold text-brand-navy">{{ $framework->name }}</h1>
                <p class="text-sm text-slate-500">{{ $framework->code }} &middot; version {{ $framework->version }} &middot; {{ $framework->staffCategory->name }}</p>
                <div class="mt-2"><x-status-badge :status="$framework->status" /></div>
            </div>
        </div>

        @foreach (['status'] as $field)
            @error($field) <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        @endforeach
    </div>

    @can('update', $framework)
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-2 text-sm font-semibold text-slate-700">Lifecycle</h2>
            <div class="flex flex-wrap gap-3">
                @can('activate', $framework)
                    <button type="button" wire:click="activate" wire:confirm="Activate this framework? Its structure will be frozen."
                        class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Activate</button>
                @endcan
                @can('archive', $framework)
                    <button type="button" wire:click="archive" wire:confirm="Archive this framework? No new evaluations may start against it; evaluations already in flight are unaffected."
                        class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Archive</button>
                @endcan
                @can('delete', $framework)
                    <button type="button" wire:click="delete" wire:confirm="Delete this draft framework? This cannot be undone."
                        class="rounded-md px-4 py-2 text-sm text-red-600 hover:bg-red-50">Delete draft</button>
                @endcan
            </div>
        </div>
    @endcan

    {{-- Sections + indicators --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-700">Sections & Indicators</h2>
            @can('update', $framework)
                <button type="button" wire:click="$toggle('showAddSection')" class="text-xs font-medium text-brand-navy hover:underline">+ Add section</button>
            @endcan
        </div>

        @if ($showAddSection)
            <form wire:submit="addSection" class="mb-4 space-y-2 rounded-lg bg-slate-50 p-3">
                <input type="text" wire:model="section_name" placeholder="Section name" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy" />
                @error('section_name') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                <textarea wire:model="section_description" rows="2" placeholder="Description (optional)" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
                <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Add</button>
            </form>
        @endif

        @forelse ($sections as $section)
            <div class="mb-4 border-b border-slate-100 pb-4 last:mb-0 last:border-0 last:pb-0">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <div class="min-w-0">
                        <span class="font-medium text-slate-900">{{ $section->name }}</span>
                        @if ($section->description)
                            <p class="text-xs text-slate-500">{{ $section->description }}</p>
                        @endif
                    </div>
                    @can('update', $framework)
                        <div class="flex flex-shrink-0 gap-2 text-xs">
                            <button type="button" wire:click="startAddIndicator({{ $section->id }})" class="font-medium text-brand-navy hover:underline">+ Indicator</button>
                            @if ($section->indicators->isEmpty())
                                <button type="button" wire:click="removeSection({{ $section->id }})" wire:confirm="Remove {{ $section->name }}?" class="text-red-500 hover:text-red-700">Remove</button>
                            @endif
                        </div>
                    @endcan
                </div>

                @if ($addIndicatorSectionId === $section->id)
                    <form wire:submit="addIndicator" class="mb-3 space-y-2 rounded-lg bg-slate-50 p-3">
                        <input type="text" wire:model="indicator_name" placeholder="Indicator name" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy" />
                        @error('indicator_name') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                        <textarea wire:model="indicator_description" rows="2" placeholder="Description (optional)" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>

                        <select wire:model="indicator_type" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                            <option value="">Response type&hellip;</option>
                            <option value="rubric">Rubric (choose a rating)</option>
                            <option value="numeric">Numeric</option>
                            <option value="boolean">Yes / No</option>
                            <option value="narrative">Narrative</option>
                        </select>
                        @error('indicator_type') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                        <select wire:model="indicator_system_evidence_key" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                            <option value="">No system evidence &mdash; human judgement only</option>
                            @foreach ($evidenceDescriptions as $key => $description)
                                <option value="{{ $key }}">{{ $description }}</option>
                            @endforeach
                        </select>
                        @error('indicator_system_evidence_key') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        <p class="text-xs text-slate-500">
                            System evidence is shown alongside this indicator as context. It never sets or
                            suggests the rating &mdash; the evaluator always chooses.
                        </p>

                        <div class="grid grid-cols-2 gap-2">
                            <input type="text" wire:model="indicator_target_value" placeholder="Target value (optional)" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy" />
                            <input type="text" wire:model="indicator_unit_label" placeholder="Unit label (optional)" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy" />
                        </div>

                        <div class="flex gap-2">
                            <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Add indicator</button>
                            <button type="button" wire:click="cancelAddIndicator" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
                        </div>
                    </form>
                @endif

                <ul class="space-y-1 text-sm">
                    @forelse ($section->indicators as $indicator)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-1">
                            <div class="min-w-0">
                                <span class="text-slate-800">{{ $indicator->name }}</span>
                                <span class="ml-1 text-xs capitalize text-slate-500">({{ $indicator->indicator_type }})</span>
                                @if ($indicator->system_evidence_key)
                                    <span class="ml-1 inline-flex items-center rounded-full bg-sky-50 px-2 py-0.5 text-xs text-sky-700 ring-1 ring-inset ring-sky-200">evidence linked</span>
                                @endif
                            </div>
                            @can('update', $framework)
                                <button type="button" wire:click="removeIndicator({{ $indicator->id }})" wire:confirm="Remove {{ $indicator->name }}?" class="flex-shrink-0 text-xs text-red-500 hover:text-red-700">Remove</button>
                            @endcan
                        </li>
                    @empty
                        <li class="text-xs text-slate-500">No indicators in this section yet.</li>
                    @endforelse
                </ul>
            </div>
        @empty
            <p class="text-sm text-slate-500">No sections yet.</p>
        @endforelse
    </div>

    {{-- Rating options --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-700">Rating Scale</h2>
            @can('update', $framework)
                <button type="button" wire:click="$toggle('showAddRatingOption')" class="text-xs font-medium text-brand-navy hover:underline">+ Add option</button>
            @endcan
        </div>

        @if ($showAddRatingOption)
            <form wire:submit="addRatingOption" class="mb-4 flex flex-wrap items-end gap-2 rounded-lg bg-slate-50 p-3">
                <div class="w-24">
                    <label class="mb-1 block text-xs font-medium text-slate-600">Value</label>
                    <input type="number" wire:model="rating_value" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy" />
                </div>
                <div class="min-w-0 flex-1">
                    <label class="mb-1 block text-xs font-medium text-slate-600">Label</label>
                    <input type="text" wire:model="rating_label" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy" />
                </div>
                <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Add</button>
                @error('rating_value') <p class="w-full text-xs text-red-600">{{ $message }}</p> @enderror
                @error('rating_label') <p class="w-full text-xs text-red-600">{{ $message }}</p> @enderror
            </form>
        @endif

        <ul class="divide-y divide-slate-100 text-sm">
            @forelse ($ratingOptions as $option)
                <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <span class="text-slate-800">{{ $option->value }} &mdash; {{ $option->label }}</span>
                    @can('update', $framework)
                        <button type="button" wire:click="removeRatingOption({{ $option->id }})" wire:confirm="Remove this rating option?" class="text-xs text-red-500 hover:text-red-700">Remove</button>
                    @endcan
                </li>
            @empty
                <li class="py-2 text-slate-500">No rating options yet.</li>
            @endforelse
        </ul>
    </div>
</div>
