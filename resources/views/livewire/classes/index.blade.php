<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="font-serif text-xl font-bold text-brand-navy">Classes</h1>
        <div class="flex gap-2">
            @can('viewAny', \App\Models\Subject::class)
                <a href="{{ route('subjects.index') }}" class="inline-flex justify-center rounded-md border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
                    Subjects
                </a>
            @endcan
            @can('create', \App\Models\SchoolClass::class)
                <a href="{{ route('classes.create') }}" class="inline-flex justify-center rounded-md bg-brand-navy px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-navy-light">
                    + Add Class
                </a>
            @endcan
        </div>
    </div>

    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm text-slate-600">
            Current Academic Year:
            <span class="font-medium text-slate-900">{{ $currentAcademicYear?->name ?? 'None set' }}</span>
        </p>

        @if ($canManageAcademicYears)
            <form wire:submit="switchCurrentAcademicYear" class="mt-3 flex flex-wrap items-end gap-2">
                <div>
                    <label for="switch_academic_year_id" class="block text-xs font-medium text-slate-500">Change current Academic Year</label>
                    <select wire:model="switch_academic_year_id" id="switch_academic_year_id" class="mt-1 rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Select a year&hellip;</option>
                        @foreach ($academicYears as $year)
                            <option value="{{ $year->id }}">{{ $year->name }}{{ $currentAcademicYear && $year->id === $currentAcademicYear->id ? ' (current)' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <button
                    type="submit"
                    wire:confirm="Change the current Academic Year to the selected year? This changes the default scope used by parts of RSMS -- it does not promote students, roll over classes, transfer enrollments, or migrate curriculum."
                    class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
                >
                    Switch
                </button>
                @error('switch_academic_year_id') <p class="w-full text-xs text-red-600">{{ $message }}</p> @enderror
            </form>
            <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
                Changing the current Academic Year changes the default scope used by parts of RSMS (attendance, planning, reports). It is not a promotion, class rollover, enrollment transfer, or curriculum migration.
            </p>
        @endif
    </div>

    <input
        type="search"
        wire:model.live.debounce.400ms="search"
        placeholder="Search by class name&hellip;"
        class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-slate-500 focus:ring-slate-500 sm:max-w-xs"
    >

    {{-- Mobile: stacked cards --}}
    <div class="space-y-2 md:hidden">
        @forelse ($classes as $class)
            <a href="{{ route('classes.show', $class) }}" class="block rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center justify-between">
                    <span class="font-medium text-slate-900">{{ $class->name }}</span>
                    @if ($currentAcademicYear && $class->academic_year_id === $currentAcademicYear->id)
                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700 ring-1 ring-inset ring-emerald-200">Current</span>
                    @endif
                </div>
                <div class="mt-1 text-sm text-slate-500">{{ $class->grade->name }} &middot; {{ $class->academicYear->name }}</div>
            </a>
        @empty
            <p class="rounded-lg bg-white p-4 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">No classes found.</p>
        @endforelse
    </div>

    {{-- Desktop: table --}}
    <div class="hidden overflow-x-auto rounded-lg bg-white shadow-sm ring-1 ring-slate-200 md:block">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Class</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Grade</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Academic Year</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Capacity</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($classes as $class)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $class->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $class->grade->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $class->academicYear->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $class->capacity ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('classes.show', $class) }}" class="text-slate-600 hover:text-slate-900">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">No classes found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $classes->links() }}</div>
</div>
