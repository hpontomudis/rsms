@php
    $navItem = fn (string $route, string $label, string|array|null $activePatterns = null) => [
        'href' => route($route),
        'label' => $label,
        'active' => request()->routeIs(...(array) ($activePatterns ?? $route.'*')),
    ];
@endphp

@foreach ([
    $navItem('dashboard', 'Dashboard'),
    ...(Route::has('students.index') && auth()->user()->can('students.view') ? [$navItem('students.index', 'Students')] : []),
    ...(Route::has('guardians.index') && auth()->user()->can('guardians.view') ? [$navItem('guardians.index', 'Guardians')] : []),
    ...(Route::has('staff.index') && auth()->user()->can('staff.view') ? [$navItem('staff.index', 'Staff')] : []),
    ...(Route::has('classes.index') && auth()->user()->can('classes.view') ? [$navItem('classes.index', 'Classes')] : []),
    ...(Route::has('attendance.take') && auth()->user()->can('attendance.record')
        ? [$navItem('attendance.take', 'Attendance', 'attendance.*')]
        : (Route::has('attendance.report') && auth()->user()->can('attendance.view')
            ? [$navItem('attendance.report', 'Attendance', 'attendance.*')]
            : [])),
    ...(Route::has('invoices.index') && auth()->user()->can('finance.view')
        ? [$navItem('invoices.index', 'Finance', ['invoices.*', 'fee-structures.*'])]
        : []),
] as $item)
    <a
        href="{{ $item['href'] }}"
        class="rounded-md px-3 py-2 text-sm font-medium {{ $item['active'] ? 'bg-brand-navy text-white' : 'text-slate-600 hover:bg-slate-100' }}"
    >
        {{ $item['label'] }}
    </a>
@endforeach
