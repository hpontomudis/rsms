@php
    $user = auth()->user();

    $navItem = fn (string $route, string $label, string $icon, string|array|null $activePatterns = null) => [
        'href' => route($route),
        'label' => $label,
        'icon' => $icon,
        'active' => request()->routeIs(...(array) ($activePatterns ?? $route.'*')),
    ];

    $groups = [
        [
            'label' => null,
            'items' => [
                $navItem('dashboard', 'Dashboard', 'dashboard'),
            ],
        ],
        [
            'label' => 'People',
            'items' => array_values(array_filter([
                Route::has('students.index') && $user->can('students.view') ? $navItem('students.index', 'Students', 'students') : null,
                Route::has('guardians.index') && $user->can('guardians.view') ? $navItem('guardians.index', 'Guardians', 'guardians') : null,
                Route::has('staff.index') && $user->can('staff.view') ? $navItem('staff.index', 'Staff', 'staff') : null,
            ])),
        ],
        [
            'label' => 'Academics',
            'items' => array_values(array_filter([
                Route::has('classes.index') && $user->can('classes.view') ? $navItem('classes.index', 'Classes', 'classes') : null,
                Route::has('subjects.index') && $user->can('academics.view') ? $navItem('subjects.index', 'Subjects', 'subjects') : null,
                Route::has('attendance.take') && $user->can('attendance.record')
                    ? $navItem('attendance.take', 'Attendance', 'attendance', 'attendance.*')
                    : (Route::has('attendance.report') && $user->can('attendance.view')
                        ? $navItem('attendance.report', 'Attendance', 'attendance', 'attendance.*')
                        : null),
            ])),
        ],
        [
            'label' => 'Finance',
            'items' => array_values(array_filter([
                Route::has('invoices.index') && $user->can('finance.view') ? $navItem('invoices.index', 'Invoices', 'finance') : null,
                Route::has('fee-structures.index') && $user->can('finance.view') ? $navItem('fee-structures.index', 'Fee Structures', 'fee-structures') : null,
            ])),
        ],
    ];
@endphp

<div class="space-y-1">
    @foreach ($groups as $group)
        @if (count($group['items']) > 0)
            <div>
                @if ($group['label'])
                    <p class="mb-1 mt-5 px-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
                        {{ $group['label'] }}
                    </p>
                @endif

                @foreach ($group['items'] as $item)
                    <a
                        href="{{ $item['href'] }}"
                        @if (($closeOnNavigate ?? false)) @click="mobileNavOpen = false" @endif
                        class="group flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition
                            {{ $item['active']
                                ? 'bg-white/10 text-white'
                                : 'text-slate-300 hover:bg-white/5 hover:text-white' }}"
                    >
                        <x-icon :name="$item['icon']" class="h-5 w-5 flex-shrink-0 {{ $item['active'] ? 'text-brand-gold' : 'text-slate-400 group-hover:text-white' }}" />
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
        @endif
    @endforeach
</div>
