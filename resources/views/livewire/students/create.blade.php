<div class="mx-auto max-w-xl space-y-4">
    <div class="flex items-center gap-2">
        <a href="{{ route('students.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Students</a>
    </div>
    <h1 class="text-xl font-semibold text-slate-800">Add Student</h1>

    <form wire:submit="save" class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Student Number</label>
                <input type="text" wire:model="student_number" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base">
                @error('student_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">First Name</label>
                <input type="text" wire:model="first_name" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base">
                @error('first_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Last Name</label>
                <input type="text" wire:model="last_name" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base">
                @error('last_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Date of Birth</label>
                <input type="date" wire:model="date_of_birth" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base">
                @error('date_of_birth') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Gender</label>
                <select wire:model="gender" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base">
                    <option value="">Select&hellip;</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                </select>
                @error('gender') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Enrollment Date</label>
                <input type="date" wire:model="enrollment_date" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base">
                @error('enrollment_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('students.index') }}" class="rounded-md border border-slate-300 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50">Cancel</a>
            <button type="submit" class="rounded-md bg-slate-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-slate-800" wire:loading.attr="disabled">
                Save Student
            </button>
        </div>
    </form>
</div>
