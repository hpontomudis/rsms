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
