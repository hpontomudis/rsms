<div class="mx-auto max-w-4xl space-y-4">
    <a href="{{ route('students.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Students</a>
    <h1 class="font-serif text-xl font-bold text-brand-navy">Import Students</h1>

    @if (empty($imported))
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-2 text-sm font-semibold text-slate-700">1. Download the template</h2>
            <p class="mb-3 text-sm text-slate-500">Fill it in outside RSMS, then come back and upload it. Creates new Student records only -- rows matching an existing NISN or NIK are rejected, not overwritten. Class enrollment is not part of this import; enroll each student afterward from their Student page.</p>
            <button type="button" wire:click="downloadTemplate" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                Download Import Template
            </button>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-2 text-sm font-semibold text-slate-700">2. Upload the filled-in file</h2>
            <form wire:submit="validateFile" class="space-y-3">
                <input type="file" wire:model="file" accept=".xlsx" class="block w-full text-sm">
                @error('file') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <button type="submit" class="rounded-md bg-brand-navy px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-navy-light" wire:loading.attr="disabled">
                    Validate File
                </button>
            </form>
        </div>

        @if ($validated)
            @php $invalidCount = collect($preview)->filter(fn ($r) => ! $r->isValid())->count(); @endphp
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">3. Review ({{ count($preview) }} rows, {{ $invalidCount }} with errors)</h2>
                <div class="max-h-96 overflow-y-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-slate-500">
                            <tr><th class="py-1 pr-3">Row</th><th class="py-1 pr-3">Name</th><th class="py-1 pr-3">NISN</th><th class="py-1">Status</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($preview as $row)
                                <tr>
                                    <td class="py-1 pr-3">{{ $row->rowNumber }}</td>
                                    <td class="py-1 pr-3">{{ $row->data['first_name'] }} {{ $row->data['last_name'] }}</td>
                                    <td class="py-1 pr-3">{{ $row->data['nisn'] ?? '—' }}</td>
                                    <td class="py-1">
                                        @if ($row->isValid())
                                            <span class="text-green-700">Ready</span>
                                        @else
                                            <span class="text-red-600">{{ implode(' ', $row->errors) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($invalidCount > 0)
                    <p class="mt-3 text-sm text-red-600">Fix the errors above in the file and re-upload. No rows are imported while any row has an error.</p>
                @else
                    <button
                        type="button"
                        wire:click="confirmImport"
                        wire:confirm="Import {{ count($preview) }} Student records?"
                        class="mt-3 rounded-md bg-brand-navy px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-navy-light"
                    >
                        Confirm Import
                    </button>
                @endif
            </div>
        @endif
    @else
        <div class="rounded-xl bg-green-50 p-5 ring-1 ring-green-200">
            <h2 class="mb-1 text-sm font-semibold text-green-900">Import complete</h2>
            <p class="text-sm text-green-800">{{ count($imported) }} Student records created. Enroll each one into a Class from their Student page when ready.</p>
        </div>

        <a href="{{ route('students.index') }}" class="inline-block text-sm text-slate-500 hover:text-slate-700">&larr; Back to Students</a>
    @endif
</div>
