<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('performance.evaluations.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Performance Evaluations</a>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h1 class="mb-4 font-serif text-xl font-bold text-brand-navy">New Evaluation</h1>

        <form wire:submit="save" class="space-y-4">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Staff member</label>
                <select wire:model.live="staff_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    <option value="">Select&hellip;</option>
                    @foreach ($staffOptions as $option)
                        <option value="{{ $option->id }}">{{ $option->fullName() }} &mdash; {{ $option->staffCategory?->name }}</option>
                    @endforeach
                </select>
                @error('staff_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-slate-500">Only staff with a category assigned can be evaluated.</p>
            </div>

            @if ($staff_id !== '')
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Framework</label>
                    <select wire:model="performance_framework_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        <option value="">Select&hellip;</option>
                        @foreach ($frameworks as $framework)
                            <option value="{{ $framework->id }}">{{ $framework->name }} v{{ $framework->version }}</option>
                        @endforeach
                    </select>
                    @error('performance_framework_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    @if ($frameworks->isEmpty())
                        <p class="mt-1 text-xs text-amber-700">No active framework for this staff member's category yet.</p>
                    @endif
                </div>
            @endif

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Period start</label>
                    <input type="date" wire:model="period_start" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy" />
                    @error('period_start') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Period end</label>
                    <input type="date" wire:model="period_end" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy" />
                    @error('period_end') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Academic year (optional)</label>
                <select wire:model="academic_year_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    <option value="">None</option>
                    @foreach ($academicYears as $year)
                        <option value="{{ $year->id }}">{{ $year->name }}</option>
                    @endforeach
                </select>
                @error('academic_year_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <p class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
                You are recorded as the evaluator. One item is created per framework indicator; nothing is
                rated automatically &mdash; every rating is your judgement to make.
            </p>

            <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Create draft</button>
        </form>
    </div>
</div>
