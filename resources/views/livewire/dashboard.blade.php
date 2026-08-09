<div class="space-y-6">
    <div>
        <h1 class="font-serif text-xl font-bold text-brand-navy">Dashboard</h1>
        <p class="text-sm text-slate-500">Welcome back, {{ auth()->user()->name }}.</p>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ([
            ['label' => 'Students', 'value' => $studentCount, 'route' => 'students.index'],
            ['label' => 'Guardians', 'value' => $guardianCount, 'route' => 'guardians.index'],
            ['label' => 'Staff', 'value' => $staffCount, 'route' => 'staff.index'],
            ['label' => 'Classes', 'value' => $classCount, 'route' => 'classes.index'],
        ] as $tile)
            @if (! is_null($tile['value']))
                <a href="{{ route($tile['route']) }}" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 hover:ring-slate-300">
                    <div class="text-2xl font-semibold text-slate-900">{{ $tile['value'] }}</div>
                    <div class="text-sm text-slate-500">{{ $tile['label'] }}</div>
                </a>
            @endif
        @endforeach
    </div>

    @if (! is_null($schoolAttendanceToday))
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-700">Attendance today</h2>
                    <p class="text-xs text-slate-500">{{ $schoolAttendanceToday['classesTaken'] }} {{ Str::plural('class', $schoolAttendanceToday['classesTaken']) }} recorded so far</p>
                </div>
                <div class="text-2xl font-semibold text-slate-900">
                    {{ $schoolAttendanceToday['rate'] !== null ? $schoolAttendanceToday['rate'].'%' : '—' }}
                </div>
            </div>
            @can('attendance.view')
                <a href="{{ route('attendance.report') }}" class="mt-3 inline-block text-sm font-medium text-brand-navy hover:underline">
                    View full report &rarr;
                </a>
            @endcan
        </div>
    @endif

    @if (! is_null($todaysClasses))
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Today's classes</h2>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($todaysClasses as $entry)
                    <li class="flex items-center justify-between py-2">
                        <span class="font-medium text-slate-900">{{ $entry->schoolClass->name }}</span>
                        @if ($entry->taken)
                            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-200">
                                Attendance taken
                            </span>
                        @else
                            <a href="{{ route('attendance.take', ['class_id' => $entry->schoolClass->id]) }}" class="rounded-md bg-brand-navy px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-navy-light">
                                Take attendance
                            </a>
                        @endif
                    </li>
                @empty
                    <li class="py-2 text-slate-500">You are not assigned to teach any class this year.</li>
                @endforelse
            </ul>
        </div>
    @endif

    @if (! is_null($financeSummary))
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-700">Finance</h2>
                <a href="{{ route('invoices.index') }}" class="text-sm font-medium text-brand-navy hover:underline">View invoices &rarr;</a>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <div class="text-lg font-semibold {{ $financeSummary['outstanding'] > 0 ? 'text-red-600' : 'text-emerald-600' }}">
                        Rp {{ number_format($financeSummary['outstanding'], 0, ',', '.') }}
                    </div>
                    <div class="text-xs text-slate-500">Outstanding</div>
                </div>
                <div>
                    <div class="text-lg font-semibold text-slate-900">Rp {{ number_format($financeSummary['paidToday'], 0, ',', '.') }}</div>
                    <div class="text-xs text-slate-500">Received today</div>
                </div>
                <div>
                    <div class="text-lg font-semibold {{ $financeSummary['overdueCount'] > 0 ? 'text-red-600' : 'text-slate-900' }}">{{ $financeSummary['overdueCount'] }}</div>
                    <div class="text-xs text-slate-500">Overdue invoices</div>
                </div>
            </div>
        </div>
    @endif

    @if ($recentActivity->isNotEmpty())
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Recent activity</h2>
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($recentActivity as $log)
                    <li class="flex items-center justify-between py-2">
                        <span class="text-slate-600">
                            {{ $log->user?->name ?? 'System' }}
                            {{ str_replace('_', ' ', $log->action) }}
                            {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                        </span>
                        <span class="whitespace-nowrap text-xs text-slate-400">{{ $log->created_at->diffForHumans() }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
