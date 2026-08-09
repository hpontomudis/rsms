<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('staff.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Staff</a>

    <div class="flex flex-col gap-3 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-slate-800">{{ $staff->fullName() }}</h1>
            <p class="text-sm text-slate-500">{{ $staff->staff_number }} &middot; {{ $staff->position->title }}</p>
            <div class="mt-2"><x-status-badge :status="$staff->status" /></div>
        </div>
        <div class="flex gap-2">
            @can('update', $staff)
                <a href="{{ route('staff.edit', $staff) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Edit</a>
            @endcan
            @can('delete', $staff)
                <button
                    type="button"
                    wire:click="archive"
                    wire:confirm="Archive {{ $staff->fullName() }}? Their history and class assignments are preserved."
                    class="rounded-md border border-red-200 px-4 py-2 text-sm text-red-600 hover:bg-red-50"
                >
                    Archive
                </button>
            @endcan
        </div>
    </div>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-3 text-sm font-semibold text-slate-700">Details</h2>
        <dl class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-slate-500">Phone</dt>
                <dd class="text-slate-900">{{ $staff->phone }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Email</dt>
                <dd class="text-slate-900">{{ $staff->email ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Hire Date</dt>
                <dd class="text-slate-900">{{ $staff->hire_date->format('d M Y') }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Position</dt>
                <dd class="text-slate-900">{{ $staff->position->title }}</dd>
            </div>
        </dl>
    </div>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-3 text-sm font-semibold text-slate-700">Classes Taught</h2>
        <ul class="divide-y divide-slate-100 text-sm">
            @forelse ($classes as $class)
                <li class="flex items-center justify-between py-2">
                    <a href="{{ route('classes.show', $class) }}" class="font-medium text-slate-900 hover:underline">{{ $class->name }}</a>
                    <span class="capitalize text-slate-500">{{ str_replace('_', ' ', $class->pivot->role) }}</span>
                </li>
            @empty
                <li class="py-2 text-slate-500">Not assigned to any class yet. Assign from the Classes page.</li>
            @endforelse
        </ul>
    </div>
</div>
