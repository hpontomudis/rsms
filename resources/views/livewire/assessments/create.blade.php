<div class="mx-auto max-w-xl space-y-4">
    <a href="{{ route('assessments.index', ['class_subject_id' => $classSubject->id]) }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; {{ $classSubject->subject->name }}</a>
    <h1 class="font-serif text-xl font-bold text-brand-navy">New Assessment</h1>

    <form wire:submit="save" class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
            <input type="text" wire:model="name" placeholder="e.g. Midterm Test" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Academic Period</label>
                <select wire:model="academic_period_id" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    @forelse ($periods as $period)
                        <option value="{{ $period->id }}">{{ $period->name }}</option>
                    @empty
                        <option value="">No periods defined for {{ $classSubject->schoolClass->academicYear->name }}</option>
                    @endforelse
                </select>
                @error('academic_period_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Max Score</label>
                <input type="number" wire:model="max_score" min="1" step="1" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                @error('max_score') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Date</label>
            <input type="date" wire:model="assessment_date" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
            @error('assessment_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('assessments.index', ['class_subject_id' => $classSubject->id]) }}" class="rounded-md border border-slate-300 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50">Cancel</a>
            <button type="submit" class="rounded-md bg-brand-navy px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-navy-light" wire:loading.attr="disabled">
                Create Assessment
            </button>
        </div>
    </form>
</div>
