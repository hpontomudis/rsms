<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('students.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Students</a>

    <div class="flex flex-col gap-3 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="font-serif text-xl font-bold text-brand-navy">{{ $student->fullName() }}</h1>
            <p class="text-sm text-slate-500">{{ $student->student_number }}</p>
            <div class="mt-2"><x-status-badge :status="$student->status" /></div>
        </div>
        <div class="flex gap-2">
            @can('update', $student)
                <a href="{{ route('students.edit', $student) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Edit</a>
            @endcan
            @can('delete', $student)
                <button
                    type="button"
                    wire:click="archive"
                    wire:confirm="Archive {{ $student->fullName() }}? They will no longer appear in active lists, but their history is preserved."
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
                <dt class="text-slate-500">Date of Birth</dt>
                <dd class="text-slate-900">{{ $student->date_of_birth->format('d M Y') }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Gender</dt>
                <dd class="capitalize text-slate-900">{{ $student->gender }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Enrollment Date</dt>
                <dd class="text-slate-900">{{ $student->enrollment_date->format('d M Y') }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Current Class</dt>
                <dd class="text-slate-900">{{ $currentClass?->name ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700">Guardians</h2>
            @can('update', $student)
                <button type="button" wire:click="$toggle('showAttachGuardian')" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                    + Link Guardian
                </button>
            @endcan
        </div>

        @if ($showAttachGuardian)
            <form wire:submit="attachGuardian" class="mb-4 space-y-3 rounded-lg bg-slate-50 p-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Guardian</label>
                    <select wire:model="guardian_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        <option value="">Select an existing guardian&hellip;</option>
                        @foreach ($availableGuardians as $guardian)
                            <option value="{{ $guardian->id }}">{{ $guardian->fullName() }} &mdash; {{ $guardian->phone }}</option>
                        @endforeach
                    </select>
                    @error('guardian_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    <a href="{{ route('guardians.create') }}" class="mt-1 inline-block text-xs text-slate-500 hover:text-slate-700">+ create a new guardian</a>
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
                <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Link Guardian</button>
            </form>
        @endif

        <ul class="divide-y divide-slate-100 text-sm">
            @forelse ($student->guardians as $guardian)
                <li class="flex items-center justify-between py-2">
                    <div>
                        <a href="{{ route('guardians.show', $guardian) }}" class="font-medium text-slate-900 hover:underline">{{ $guardian->fullName() }}</a>
                        <span class="ml-1 capitalize text-slate-500">({{ str_replace('_', ' ', $guardian->pivot->relationship_type) }})</span>
                        @if ($guardian->pivot->is_primary_contact)
                            <span class="ml-1 rounded-full bg-brand-navy px-2 py-0.5 text-xs text-white">Primary</span>
                        @endif
                    </div>
                    @can('update', $student)
                        <button
                            type="button"
                            wire:click="detachGuardian({{ $guardian->id }})"
                            wire:confirm="Remove this guardian link?"
                            class="text-xs text-red-500 hover:text-red-700"
                        >
                            Unlink
                        </button>
                    @endcan
                </li>
            @empty
                <li class="py-2 text-slate-500">No guardians linked yet.</li>
            @endforelse
        </ul>
    </div>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700">Class Enrollment</h2>
            @can('update', $student)
                <div class="flex items-center gap-3">
                    <button type="button" wire:click="$toggle('showEnroll')" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                        {{ $currentClass ? '+ Transfer to Class' : '+ Enroll in Class' }}
                    </button>
                    @if ($currentClass)
                        <button
                            type="button"
                            wire:click="withdrawFromClass"
                            wire:confirm="Withdraw {{ $student->fullName() }} from {{ $currentClass->name }} (leaving the school, not moving classes)?"
                            class="text-sm font-medium text-red-500 hover:text-red-700"
                        >
                            Withdraw
                        </button>
                    @endif
                </div>
            @endcan
        </div>

        @if ($showEnroll)
            <form wire:submit="enrollInClass" class="mb-4 space-y-3 rounded-lg bg-slate-50 p-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Class</label>
                    <select wire:model="class_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        <option value="">Select a class&hellip;</option>
                        @foreach ($availableClasses as $class)
                            <option value="{{ $class->id }}">{{ $class->name }} ({{ $class->grade->name }})</option>
                        @endforeach
                    </select>
                    @error('class_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Effective From</label>
                    <input type="date" wire:model="enrolled_at" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                </div>
                @error('class_student') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">
                    {{ $currentClass ? 'Confirm Transfer' : 'Enroll' }}
                </button>
            </form>
        @endif

        <ul class="divide-y divide-slate-100 text-sm">
            @forelse ($student->classes as $class)
                <li class="flex items-center justify-between py-2">
                    <a href="{{ route('classes.show', $class) }}" class="font-medium text-slate-900 hover:underline">{{ $class->name }}</a>
                    <x-status-badge :status="$class->pivot->status" />
                </li>
            @empty
                <li class="py-2 text-slate-500">Not enrolled in any class.</li>
            @endforelse
        </ul>
    </div>

    @if ($canViewAttendance)
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Attendance History</h2>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($recentAttendance as $record)
                    <li class="flex items-center justify-between py-2">
                        <div>
                            <span class="text-slate-900">{{ $record->attendance->date->format('d M Y') }}</span>
                            <span class="ml-1 text-slate-500">{{ $record->attendance->schoolClass->name }}</span>
                        </div>
                        <x-status-badge :status="$record->status" />
                    </li>
                @empty
                    <li class="py-2 text-slate-500">No attendance recorded yet.</li>
                @endforelse
            </ul>
        </div>
    @endif

    @if ($canViewFinance)
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-700">Finance</h2>
                @can('create', \App\Models\Invoice::class)
                    <a href="{{ route('invoices.create') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">+ New Invoice</a>
                @endcan
            </div>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($invoices as $invoice)
                    <li class="flex items-center justify-between py-2">
                        <div>
                            <a href="{{ route('invoices.show', $invoice) }}" class="font-medium text-slate-900 hover:underline">{{ $invoice->invoice_number }}</a>
                            <span class="ml-1 text-slate-500">Rp {{ number_format($invoice->balance(), 0, ',', '.') }} outstanding</span>
                        </div>
                        <x-status-badge :status="$invoice->status" />
                    </li>
                @empty
                    <li class="py-2 text-slate-500">No invoices yet.</li>
                @endforelse
            </ul>
            @if ($invoices->isNotEmpty())
                <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3 text-sm font-semibold">
                    <span class="text-slate-700">Total Outstanding</span>
                    <span class="{{ $student->outstandingBalance() > 0 ? 'text-red-600' : 'text-emerald-600' }}">
                        Rp {{ number_format($student->outstandingBalance(), 0, ',', '.') }}
                    </span>
                </div>
            @endif
        </div>
    @endif

    @if ($canViewAcademics)
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-700">Academics</h2>
                <div class="flex items-center gap-4">
                    {{-- Placement is academic administration, so it follows academics.manage
                         rather than the student-view permission that opened this page. --}}
                    @can('viewAny', \App\Models\StudentEnglishLevelPlacement::class)
                        <a href="{{ route('students.english-placement', $student) }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">English Proficiency</a>
                    @endcan
                    <a href="{{ route('students.report-card', $student) }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">View Report Card</a>
                </div>
            </div>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($recentAssessmentResults as $result)
                    <li class="flex items-center justify-between py-2">
                        <div>
                            <span class="text-slate-900">{{ $result->assessment->name }}</span>
                            <span class="ml-1 text-slate-500">
                                ({{ $result->assessment->classSubject->subject->name }} &middot; {{ $result->assessment->academicPeriod->name }})
                            </span>
                        </div>
                        <span class="font-medium text-slate-900">{{ (float) $result->score }} / {{ (int) $result->assessment->max_score }}</span>
                    </li>
                @empty
                    <li class="py-2 text-slate-500">No assessment scores recorded yet.</li>
                @endforelse
            </ul>
        </div>
    @endif
</div>
