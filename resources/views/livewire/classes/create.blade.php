<div class="mx-auto max-w-xl space-y-4">
    <a href="{{ route('classes.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Classes</a>
    <h1 class="text-xl font-semibold text-slate-800">Add Class</h1>

    <form wire:submit="save" class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Class Name</label>
            <input type="text" wire:model="name" placeholder="e.g. Year 5A" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Grade</label>
                <select wire:model="grade_id" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base">
                    <option value="">Select&hellip;</option>
                    @foreach ($grades as $grade)
                        <option value="{{ $grade->id }}">{{ $grade->name }}</option>
                    @endforeach
                </select>
                @error('grade_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Academic Year</label>
                <select wire:model="academic_year_id" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base">
                    <option value="">Select&hellip;</option>
                    @foreach ($academicYears as $year)
                        <option value="{{ $year->id }}">{{ $year->name }}{{ $year->is_current ? ' (current)' : '' }}</option>
                    @endforeach
                </select>
                @error('academic_year_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Capacity (optional)</label>
            <input type="number" wire:model="capacity" min="1" max="100" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base sm:w-40">
            @error('capacity') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('classes.index') }}" class="rounded-md border border-slate-300 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50">Cancel</a>
            <button type="submit" class="rounded-md bg-slate-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-slate-800" wire:loading.attr="disabled">
                Save Class
            </button>
        </div>
    </form>
</div>
