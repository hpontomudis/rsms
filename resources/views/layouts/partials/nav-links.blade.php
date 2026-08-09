@php
    $navItem = fn (string $route, string $label) => [
        'href' => route($route),
        'label' => $label,
        'active' => request()->routeIs($route.'*'),
    ];
@endphp

@foreach ([
    $navItem('dashboard', 'Dashboard'),
    ...(Route::has('students.index') && auth()->user()->can('students.view') ? [$navItem('students.index', 'Students')] : []),
    ...(Route::has('guardians.index') && auth()->user()->can('guardians.view') ? [$navItem('guardians.index', 'Guardians')] : []),
    ...(Route::has('staff.index') && auth()->user()->can('staff.view') ? [$navItem('staff.index', 'Staff')] : []),
    ...(Route::has('classes.index') && auth()->user()->can('classes.view') ? [$navItem('classes.index', 'Classes')] : []),
] as $item)
    <a
        href="{{ $item['href'] }}"
        class="rounded-md px-3 py-2 text-sm font-medium {{ $item['active'] ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}"
    >
        {{ $item['label'] }}
    </a>
@endforeach
