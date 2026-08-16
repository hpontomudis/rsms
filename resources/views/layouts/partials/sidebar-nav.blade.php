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
                // Only for people who actually hold a staff profile -- "my
                // assignments" means nothing without one.
                Route::has('my-teaching') && $user->can('academics.view') && $user->staff()->exists()
                    ? $navItem('my-teaching', 'My Teaching', 'subjects') : null,
                Route::has('classes.index') && $user->can('classes.view') ? $navItem('classes.index', 'Classes', 'classes') : null,
                Route::has('subjects.index') && $user->can('academics.view') ? $navItem('subjects.index', 'Subjects', 'subjects') : null,
                Route::has('english-programmes.index') && $user->can('academics.view') ? $navItem('english-programmes.index', 'English Programmes', 'subjects') : null,
                Route::has('curricula.index') && $user->can('academics.view') ? $navItem('curricula.index', 'Curricula', 'subjects') : null,
                Route::has('planning.annual.index') && $user->can('academics.view') ? $navItem('planning.annual.index', 'Annual Programmes', 'subjects', 'planning.*') : null,
                Route::has('learning-phases') && $user->can('academics.view') ? $navItem('learning-phases', 'Learning Phases', 'subjects') : null,
                // academics.manage, not academics.view: a group roster names students,
                // and there is no teaching assignment yet to scope a teacher through.
                Route::has('teaching-groups.index') && $user->can('academics.manage') ? $navItem('teaching-groups.index', 'Teaching Groups', 'classes') : null,
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
        [
            'label' => 'Performance',
            'items' => array_values(array_filter([
                Route::has('performance.evaluations.index') && ($user->can('performance.view') || $user->can('performance.manage'))
                    ? $navItem('performance.evaluations.index', 'Evaluations', 'staff', 'performance.evaluations.*') : null,
                Route::has('performance.frameworks.index') && ($user->can('performance.view') || $user->can('performance.manage'))
                    ? $navItem('performance.frameworks.index', 'Frameworks', 'staff', 'performance.frameworks.*') : null,
                Route::has('performance.staff-categories.index') && ($user->can('performance.view') || $user->can('performance.manage'))
                    ? $navItem('performance.staff-categories.index', 'Staff Categories', 'staff') : null,
                // No permission required: self-view is a policy carve-out, not
                // a permission -- shown to anyone with a linked staff profile.
                Route::has('performance.my-evaluations') && $user->staff()->exists()
                    ? $navItem('performance.my-evaluations', 'My Evaluations', 'staff') : null,
            ])),
        ],
        [
            'label' => 'Communications',
            'items' => array_values(array_filter([
                Route::has('communications.index') && ($user->can('communications.view') || $user->can('communications.manage'))
                    ? $navItem('communications.index', 'Communications', 'subjects', ['communications.index', 'communications.create', 'communications.show']) : null,
                // No permission required: anyone may hold a materialized
                // CommunicationRecipient row and have something to read.
                Route::has('communications.inbox') ? $navItem('communications.inbox', 'My Inbox', 'subjects') : null,
            ])),
        ],
        [
            'label' => 'Management',
            'items' => array_values(array_filter([
                Route::has('management.insights') && $user->can('management-insights.view')
                    ? $navItem('management.insights', 'Insights', 'dashboard') : null,
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
