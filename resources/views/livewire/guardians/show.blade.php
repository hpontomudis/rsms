<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('guardians.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Guardians</a>

    <div class="flex flex-col gap-3 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="font-serif text-xl font-bold text-brand-navy">{{ $guardian->fullName() }}</h1>
            <p class="text-sm text-slate-500">{{ $guardian->phone }}</p>
        </div>
        <div class="flex gap-2">
            @can('update', $guardian)
                <a href="{{ route('guardians.edit', $guardian) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Edit</a>
            @endcan
            @can('delete', $guardian)
                <button
                    type="button"
                    wire:click="archive"
                    wire:confirm="Archive {{ $guardian->fullName() }}? Existing student links are preserved in history."
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
                <dd class="text-slate-900">{{ $guardian->phone }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Email</dt>
                <dd class="text-slate-900">{{ $guardian->email ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Occupation</dt>
                <dd class="text-slate-900">{{ $guardian->occupation ?? '—' }}</dd>
            </div>
            <div class="col-span-2">
                <dt class="text-slate-500">Address</dt>
                <dd class="text-slate-900">{{ $guardian->address ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700">Children</h2>
            @can('update', $guardian)
                <button type="button" wire:click="$toggle('showAttachStudent')" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                    + Link Student
                </button>
            @endcan
        </div>

        @if ($showAttachStudent)
            <form wire:submit="attachStudent" class="mb-4 space-y-3 rounded-lg bg-slate-50 p-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Student</label>
                    <select wire:model="student_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        <option value="">Select an existing student&hellip;</option>
                        @foreach ($availableStudents as $student)
                            <option value="{{ $student->id }}">{{ $student->fullName() }} &mdash; {{ $student->student_number }}</option>
                        @endforeach
                    </select>
                    @error('student_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    <a href="{{ route('students.create') }}" class="mt-1 inline-block text-xs text-slate-500 hover:text-slate-700">+ create a new student</a>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Relationship</label>
                    <select wire:model="relationship_type" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        <option value="">Select&hellip;</option>
                        <option value="father">Father</option>
                        <option value="mother">Mother</option>
                        <option value="grandparent">Grandparent</option>
                        <option value="sibling">Sibling</option>
                        <option value="legal_guardian">Legal Guardian</option>
                        <option value="other">Other</option>
                    </select>
                    @error('relationship_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" wire:model="is_primary_contact" class="rounded border-slate-300"> Primary contact
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" wire:model="can_pickup" class="rounded border-slate-300"> Can pick up
                    </label>
                </div>
                <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Link Student</button>
            </form>
        @endif

        <ul class="divide-y divide-slate-100 text-sm">
            @forelse ($guardian->students as $student)
                <li class="flex items-center justify-between py-2">
                    <div>
                        <a href="{{ route('students.show', $student) }}" class="font-medium text-slate-900 hover:underline">{{ $student->fullName() }}</a>
                        <span class="ml-1 capitalize text-slate-500">({{ str_replace('_', ' ', $student->pivot->relationship_type) }})</span>
                        @if ($student->pivot->is_primary_contact)
                            <span class="ml-1 rounded-full bg-brand-navy px-2 py-0.5 text-xs text-white">Primary</span>
                        @endif
                    </div>
                    @can('update', $guardian)
                        <button
                            type="button"
                            wire:click="detachStudent({{ $student->id }})"
                            wire:confirm="Remove this student link?"
                            class="text-xs text-red-500 hover:text-red-700"
                        >
                            Unlink
                        </button>
                    @endcan
                </li>
            @empty
                <li class="py-2 text-slate-500">No students linked yet.</li>
            @endforelse
        </ul>
    </div>
</div>
