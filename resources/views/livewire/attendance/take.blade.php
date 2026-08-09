<div class="mx-auto max-w-xl space-y-4">
    <h1 class="font-serif text-xl font-bold text-brand-navy">Take Attendance</h1>

    @if (! $hasStaffProfile)
        <div class="rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-700 ring-1 ring-amber-200">
            Your account isn't linked to a staff profile, so you can't record attendance. Ask an administrator to
            link your user account to a staff record.
        </div>
    @else
    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            @if ($classes->count() > 1)
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Class</label>
                    <select wire:model.live="class_id" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        <option value="">Select a class&hellip;</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}">{{ $class->name }} ({{ $class->grade->name }})</option>
                        @endforeach
                    </select>
                </div>
            @elseif ($classes->count() === 1)
                <div>
                    <span class="mb-1 block text-xs font-medium text-slate-600">Class</span>
                    <p class="rounded-md bg-slate-50 px-3 py-2.5 text-base font-medium text-slate-800">{{ $classes->first()->name }}</p>
                </div>
            @else
                <p class="text-sm text-slate-500 sm:col-span-2">You are not assigned to teach any class this year.</p>
            @endif

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Date</label>
                <input type="date" wire:model.live="date" max="{{ now()->toDateString() }}" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
            </div>
        </div>
    </div>

    @if ($saved)
        <div class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700 ring-1 ring-emerald-200">
            Attendance saved for {{ $schoolClass->name }} on {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}.
        </div>
    @endif

    @if ($schoolClass)
        @if (! $editable)
            <div class="rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-700 ring-1 ring-amber-200">
                This attendance was already taken and can no longer be edited (same-day only).
            </div>
        @endif

        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div class="mb-3 flex items-center justify-between">
                <span class="text-sm font-semibold text-slate-700">{{ $students->count() }} students</span>
                @if ($editable)
                    <button type="button" wire:click="markAllPresent" class="text-sm font-medium text-brand-navy hover:underline">
                        Mark all present
                    </button>
                @endif
            </div>

            <div class="divide-y divide-slate-100">
                @foreach ($students as $student)
                    @php $current = $statuses[$student->id] ?? 'present'; @endphp
                    <div class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <span class="font-medium text-slate-900">{{ $student->fullName() }}</span>

                        <div class="grid grid-cols-4 gap-1.5">
                            @foreach ([
                                'present' => 'Present',
                                'late' => 'Late',
                                'excused' => 'Excused',
                                'absent' => 'Absent',
                            ] as $value => $label)
                                <button
                                    type="button"
                                    wire:click="setStatus({{ $student->id }}, '{{ $value }}')"
                                    @disabled(! $editable)
                                    class="min-h-11 rounded-md px-2 py-2 text-xs font-semibold transition
                                        {{ $current === $value
                                            ? match ($value) {
                                                'present' => 'bg-emerald-600 text-white',
                                                'late' => 'bg-amber-500 text-white',
                                                'excused' => 'bg-sky-600 text-white',
                                                'absent' => 'bg-red-600 text-white',
                                            }
                                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}
                                        {{ ! $editable ? 'opacity-60' : '' }}"
                                >
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($editable)
                <button
                    type="button"
                    wire:click="save"
                    class="mt-4 w-full rounded-md bg-brand-navy px-5 py-3 text-base font-medium text-white hover:bg-brand-navy-light"
                    wire:loading.attr="disabled"
                >
                    Save Attendance
                </button>
            @endif
        </div>
    @endif
    @endif
</div>
