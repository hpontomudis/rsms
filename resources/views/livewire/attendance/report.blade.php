<div class="space-y-4">
    <h1 class="font-serif text-xl font-bold text-brand-navy">Attendance Report</h1>

    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Class</label>
                <select wire:model.live="class_id" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    <option value="">Select a class&hellip;</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}">{{ $class->name }} ({{ $class->grade->name }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">From</label>
                <input type="date" wire:model.live="start_date" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">To</label>
                <input type="date" wire:model.live="end_date" max="{{ now()->toDateString() }}" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
            </div>
        </div>
    </div>

    @if ($schoolClass)
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <div class="text-2xl font-semibold text-slate-900">{{ $classAverage !== null ? $classAverage.'%' : '—' }}</div>
                <div class="text-sm text-slate-500">Class attendance rate</div>
            </div>
        </div>

        {{-- Mobile: stacked cards --}}
        <div class="space-y-2 md:hidden">
            @forelse ($rows as $row)
                <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-slate-900">{{ $row->student->fullName() }}</span>
                        <span class="text-sm font-semibold text-slate-700">{{ $row->rate !== null ? $row->rate.'%' : '—' }}</span>
                    </div>
                    <div class="mt-1 text-xs text-slate-500">
                        {{ $row->counts['present'] }} present &middot; {{ $row->counts['late'] }} late &middot;
                        {{ $row->counts['excused'] }} excused &middot; {{ $row->counts['absent'] }} absent
                    </div>
                </div>
            @empty
                <p class="rounded-lg bg-white p-4 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">No students enrolled in this class.</p>
            @endforelse
        </div>

        {{-- Desktop: table --}}
        <div class="hidden overflow-x-auto rounded-lg bg-white shadow-sm ring-1 ring-slate-200 md:block">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-slate-500">Student</th>
                        <th class="px-4 py-3 text-center font-medium text-emerald-600">Present</th>
                        <th class="px-4 py-3 text-center font-medium text-amber-600">Late</th>
                        <th class="px-4 py-3 text-center font-medium text-sky-600">Excused</th>
                        <th class="px-4 py-3 text-center font-medium text-red-600">Absent</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-500">Rate</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $row->student->fullName() }}</td>
                            <td class="px-4 py-3 text-center text-slate-600">{{ $row->counts['present'] }}</td>
                            <td class="px-4 py-3 text-center text-slate-600">{{ $row->counts['late'] }}</td>
                            <td class="px-4 py-3 text-center text-slate-600">{{ $row->counts['excused'] }}</td>
                            <td class="px-4 py-3 text-center text-slate-600">{{ $row->counts['absent'] }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ $row->rate !== null ? $row->rate.'%' : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-500">No students enrolled in this class.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <p class="text-sm text-slate-500">Select a class to see its attendance report.</p>
    @endif
</div>
