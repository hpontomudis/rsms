@props(['status'])

@php
    $colors = [
        'active' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'present' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'graduated' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'excused' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'on_leave' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'late' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'withdrawn' => 'bg-slate-100 text-slate-600 ring-slate-200',
        'transferred' => 'bg-slate-100 text-slate-600 ring-slate-200',
        'terminated' => 'bg-red-50 text-red-700 ring-red-200',
        'absent' => 'bg-red-50 text-red-700 ring-red-200',
    ];
    $classes = $colors[$status] ?? 'bg-slate-100 text-slate-600 ring-slate-200';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ring-1 ring-inset {$classes}"]) }}>
    {{ str_replace('_', ' ', $status) }}
</span>
