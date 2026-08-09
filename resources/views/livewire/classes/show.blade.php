<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('classes.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Classes</a>

    <div class="flex flex-col gap-3 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="font-serif text-xl font-bold text-brand-navy">{{ $schoolClass->name }}</h1>
            <p class="text-sm text-slate-500">{{ $schoolClass->grade->name }} &middot; {{ $schoolClass->academicYear->name }}</p>
            @if ($schoolClass->capacity)
                <p class="mt-1 text-xs text-slate-400">{{ $schoolClass->students()->count() }} / {{ $schoolClass->capacity }} students</p>
            @endif
        </div>
        @can('update', $schoolClass)
            <a href="{{ route('classes.edit', $schoolClass) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Edit</a>
        @endcan
    </div>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700">Teachers</h2>
            @can('update', $schoolClass)
                <button type="button" wire:click="$toggle('showAssignTeacher')" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                    + Assign Teacher
                </button>
            @endcan
        </div>

        @if ($showAssignTeacher)
            <form wire:submit="assignTeacher" class="mb-4 space-y-3 rounded-lg bg-slate-50 p-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Staff</label>
                    <select wire:model="staff_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        <option value="">Select a staff member&hellip;</option>
                        @foreach ($availableStaff as $member)
                            <option value="{{ $member->id }}">{{ $member->fullName() }} &mdash; {{ $member->position->title }}</option>
                        @endforeach
                    </select>
                    @error('staff_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Role</label>
                    <select wire:model="role" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        <option value="">Select&hellip;</option>
                        <option value="homeroom">Homeroom</option>
                        <option value="assistant">Assistant</option>
                        <option value="subject_teacher">Subject Teacher</option>
                    </select>
                    @error('role') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Assign</button>
            </form>
        @endif

        <ul class="divide-y divide-slate-100 text-sm">
            @forelse ($schoolClass->teachers as $teacher)
                <li class="flex items-center justify-between py-2">
                    <div>
                        <a href="{{ route('staff.show', $teacher) }}" class="font-medium text-slate-900 hover:underline">{{ $teacher->fullName() }}</a>
                        <span class="ml-1 capitalize text-slate-500">({{ str_replace('_', ' ', $teacher->pivot->role) }})</span>
                    </div>
                    @can('update', $schoolClass)
                        <button
                            type="button"
                            wire:click="removeTeacher({{ $teacher->id }}, '{{ $teacher->pivot->role }}')"
                            wire:confirm="Remove this teacher assignment?"
                            class="text-xs text-red-500 hover:text-red-700"
                        >
                            Remove
                        </button>
                    @endcan
                </li>
            @empty
                <li class="py-2 text-slate-500">No teachers assigned yet.</li>
            @endforelse
        </ul>
    </div>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700">Roster</h2>
            @can('update', $schoolClass)
                <button type="button" wire:click="$toggle('showEnrollStudent')" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                    + Enroll Student
                </button>
            @endcan
        </div>

        @if ($showEnrollStudent)
            <form wire:submit="enrollStudent" class="mb-4 space-y-3 rounded-lg bg-slate-50 p-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Student</label>
                    <select wire:model="student_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        <option value="">Select a student&hellip;</option>
                        @foreach ($availableStudents as $student)
                            <option value="{{ $student->id }}">{{ $student->fullName() }} &mdash; {{ $student->student_number }}</option>
                        @endforeach
                    </select>
                    @error('student_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Enrolled From</label>
                    <input type="date" wire:model="enrolled_at" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                </div>
                <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Enroll</button>
            </form>
        @endif

        <ul class="divide-y divide-slate-100 text-sm">
            @forelse ($schoolClass->students as $student)
                <li class="flex items-center justify-between py-2">
                    <a href="{{ route('students.show', $student) }}" class="font-medium text-slate-900 hover:underline">{{ $student->fullName() }}</a>
                    @can('update', $schoolClass)
                        <button
                            type="button"
                            wire:click="unenrollStudent({{ $student->id }})"
                            wire:confirm="Remove {{ $student->fullName() }} from this class?"
                            class="text-xs text-red-500 hover:text-red-700"
                        >
                            Remove
                        </button>
                    @endcan
                </li>
            @empty
                <li class="py-2 text-slate-500">No students enrolled yet.</li>
            @endforelse
        </ul>
    </div>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700">Subjects</h2>
            @can('manageAcademics', $schoolClass)
                <button type="button" wire:click="$toggle('showAssignSubject')" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                    + Assign Subject
                </button>
            @endcan
        </div>

        @if ($showAssignSubject)
            <form wire:submit="assignSubject" class="mb-4 space-y-3 rounded-lg bg-slate-50 p-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Subject</label>
                    <select wire:model="subject_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        <option value="">Select a subject&hellip;</option>
                        @foreach ($availableSubjects as $subject)
                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                        @endforeach
                    </select>
                    @error('subject_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Teacher</label>
                    <select wire:model="subject_teacher_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                        <option value="">Select a staff member&hellip;</option>
                        @foreach ($availableStaff as $member)
                            <option value="{{ $member->id }}">{{ $member->fullName() }}</option>
                        @endforeach
                    </select>
                    @error('subject_teacher_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Assign</button>
            </form>
        @endif

        <ul class="divide-y divide-slate-100 text-sm">
            @forelse ($classSubjects as $classSubject)
                <li class="flex items-center justify-between py-2">
                    <div>
                        <span class="font-medium text-slate-900">{{ $classSubject->subject->name }}</span>
                        <span class="ml-1 text-slate-500">taught by {{ $classSubject->teacher->fullName() }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        @if (Route::has('assessments.index'))
                            <a href="{{ route('assessments.index', ['class_subject_id' => $classSubject->id]) }}" class="text-xs text-brand-navy hover:underline">Assessments</a>
                        @endif
                        @can('manageAcademics', $schoolClass)
                            @if ($classSubject->assessments_count === 0)
                                <button
                                    type="button"
                                    wire:click="removeSubject({{ $classSubject->id }})"
                                    wire:confirm="Remove this subject from the class?"
                                    class="text-xs text-red-500 hover:text-red-700"
                                >
                                    Remove
                                </button>
                            @endif
                        @endcan
                    </div>
                </li>
            @empty
                <li class="py-2 text-slate-500">No subjects assigned yet.</li>
            @endforelse
        </ul>
    </div>
</div>
