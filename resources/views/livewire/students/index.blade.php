<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="font-serif text-xl font-bold text-brand-navy">Students</h1>
        <div class="flex gap-2">
            @can('import', \App\Models\Student::class)
                <a href="{{ route('students.import') }}" class="inline-flex justify-center rounded-md border border-slate-300 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50">
                    Import Students
                </a>
            @endcan
            @can('create', \App\Models\Student::class)
                <a href="{{ route('students.create') }}" class="inline-flex justify-center rounded-md bg-brand-navy px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-navy-light">
                    + Add Student
                </a>
            @endcan
        </div>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <input
            type="search"
            wire:model.live.debounce.400ms="search"
            placeholder="Search by name or student number&hellip;"
            class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-slate-500 focus:ring-slate-500 sm:max-w-xs"
        >
        <select wire:model.live="status" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-slate-500 focus:ring-slate-500 sm:w-48">
            <option value="active">Active</option>
            <option value="graduated">Graduated</option>
            <option value="withdrawn">Withdrawn</option>
            <option value="transferred">Transferred</option>
            <option value="all">All statuses</option>
        </select>
    </div>

    {{-- Mobile: stacked cards --}}
    <div class="space-y-2 md:hidden">
        @forelse ($students as $student)
            <a href="{{ route('students.show', $student) }}" class="block rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center justify-between">
                    <span class="font-medium text-slate-900">{{ $student->fullName() }}</span>
                    <x-status-badge :status="$student->status" />
                </div>
                <div class="mt-1 text-sm text-slate-500">{{ $student->student_number }}</div>
            </a>
        @empty
            <p class="rounded-lg bg-white p-4 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">No students found.</p>
        @endforelse
    </div>

    {{-- Desktop: table --}}
    <div class="hidden overflow-x-auto rounded-lg bg-white shadow-sm ring-1 ring-slate-200 md:block">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Student No.</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Name</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Gender</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($students as $student)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-slate-600">{{ $student->student_number }}</td>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $student->fullName() }}</td>
                        <td class="px-4 py-3 capitalize text-slate-600">{{ $student->gender }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$student->status" /></td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('students.show', $student) }}" class="text-slate-600 hover:text-slate-900">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">No students found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $students->links() }}</div>
</div>
