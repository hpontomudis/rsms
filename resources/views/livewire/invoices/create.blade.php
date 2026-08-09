<div class="mx-auto max-w-xl space-y-4">
    <a href="{{ route('invoices.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Invoices</a>
    <h1 class="font-serif text-xl font-bold text-brand-navy">New Invoice</h1>

    <form wire:submit="save" class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Student</label>
            <select wire:model="student_id" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                <option value="">Select a student&hellip;</option>
                @foreach ($students as $student)
                    <option value="{{ $student->id }}">{{ $student->fullName() }} &mdash; {{ $student->student_number }}</option>
                @endforeach
            </select>
            @error('student_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Academic Year</label>
            <select wire:model.live="academic_year_id" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                <option value="">Select&hellip;</option>
                @foreach ($academicYears as $year)
                    <option value="{{ $year->id }}">{{ $year->name }}{{ $year->is_current ? ' (current)' : '' }}</option>
                @endforeach
            </select>
            @error('academic_year_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Fee Structure</label>
            <select wire:model="fee_structure_id" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                <option value="">Select&hellip;</option>
                @foreach ($feeStructures as $structure)
                    <option value="{{ $structure->id }}">{{ $structure->name }} ({{ $structure->grade->name }}) &mdash; Rp {{ number_format($structure->total(), 0, ',', '.') }}</option>
                @endforeach
            </select>
            @error('fee_structure_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            <p class="mt-1 text-xs text-slate-500">The fee structure's items are copied onto the invoice and can be adjusted afterward.</p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Issue Date</label>
                <input type="date" wire:model="issue_date" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                @error('issue_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Due Date</label>
                <input type="date" wire:model="due_date" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                @error('due_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('invoices.index') }}" class="rounded-md border border-slate-300 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50">Cancel</a>
            <button type="submit" class="rounded-md bg-brand-navy px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-navy-light" wire:loading.attr="disabled">
                Create Invoice
            </button>
        </div>
    </form>
</div>
