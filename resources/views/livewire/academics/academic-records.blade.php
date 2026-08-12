<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('students.show', $student) }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700">&larr; {{ $student->fullName() }}</a>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h1 class="font-serif text-xl font-bold text-brand-navy">Rapor / Academic Records</h1>
        <p class="text-sm text-slate-500">{{ $student->fullName() }} &middot; {{ $student->student_number }}</p>
        <p class="mt-2 text-xs text-slate-500">
            An issued record is a snapshot of the day it was published, and never changes afterwards.
            The <a href="{{ route('students.report-card', $student) }}" wire:navigate class="text-brand-navy hover:underline">year overview</a>
            is always live.
        </p>

        @foreach (['status', 'academic_period_id', 'class_id', 'homeroom_teacher_name_snapshot'] as $field)
            @error($field) <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        @endforeach
    </div>

    @if ($canManage)
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Prepare a report card</h2>
            <form wire:submit="createDraft" class="flex flex-wrap gap-2">
                <select wire:model="academic_period_id" class="min-w-0 flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    <option value="">Reporting period&hellip;</option>
                    @foreach ($periods as $period)
                        <option value="{{ $period->id }}">{{ $period->academicYear->name }} &mdash; {{ $period->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">New draft</button>
            </form>
        </div>
    @endif

    {{-- Draft: previews LIVE data, so what you see is what publishing issues --}}
    @if ($draft && $preview)
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                <div>
                    <div class="mb-1"><x-status-badge :status="$draft->status" /></div>
                    <h2 class="font-serif text-lg font-bold text-brand-navy">{{ $draft->period_name_snapshot }}</h2>
                    <p class="text-xs text-slate-500">{{ $draft->academic_year_name_snapshot }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('documents.report-card.period', [$student, $draft->academicPeriod]) }}" target="_blank"
                        class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Print preview</a>
                    <button type="button" wire:click="publish" wire:confirm="Publish this report card? The numbers are frozen as they are right now."
                        class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Publish</button>
                </div>
            </div>

            <p class="mb-3 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
                This preview reads current data. Publishing freezes whatever it shows at that moment &mdash;
                a correction made after drafting will be issued, not the figures you first saw.
            </p>

            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs text-slate-500">
                        <th class="py-2">Subject</th>
                        <th class="py-2 text-right">Score</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($preview['rows'] as $row)
                        <tr class="border-b border-slate-100">
                            <td class="py-2 text-slate-800">{{ $row->subject->name }}</td>
                            <td class="py-2 text-right text-slate-800">{{ $row->score !== null ? $row->score : '—' }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td class="py-2 font-semibold text-slate-700">Overall</td>
                        <td class="py-2 text-right font-bold text-brand-navy">{{ $preview['overallAverage'] !== null ? $preview['overallAverage'] : '—' }}</td>
                    </tr>
                </tbody>
            </table>

            <form wire:submit="saveComment" class="mt-4 space-y-2">
                <label class="block text-xs font-medium text-slate-600">Catatan Wali Kelas / Homeroom comment</label>
                <textarea wire:model="homeroom_comment" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Save comment</button>
                    <button type="button" wire:click="deleteDraft({{ $draft->id }})" wire:confirm="Delete this draft?"
                        class="rounded-md px-4 py-2 text-sm text-red-600 hover:bg-red-50">Delete draft</button>
                </div>
            </form>
        </div>
    @endif

    <div class="space-y-3">
        @forelse ($records as $record)
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="mb-1 flex flex-wrap items-center gap-2">
                            <x-status-badge :status="$record->status" />
                            <span class="font-medium text-slate-800">{{ $record->period_name_snapshot }}</span>
                        </div>
                        <p class="text-xs text-slate-500">{{ $record->academic_year_name_snapshot }}</p>
                        @if ($record->isIssued())
                            <p class="mt-1 text-xs text-slate-500">
                                Issued {{ $record->published_at?->format('d M Y') }}
                                @if ($record->publishedBy) by {{ $record->publishedBy->name }} @endif
                                @if ($record->overall_average !== null) &middot; overall {{ $record->overall_average }} @endif
                            </p>
                            {{-- Identity as issued, which may no longer be the
                                 student's current recorded name. --}}
                            @if ($record->student_name_snapshot !== $student->fullName())
                                <p class="mt-1 text-xs text-amber-700">
                                    Issued as “{{ $record->student_name_snapshot }}” &mdash; now recorded as “{{ $student->fullName() }}”.
                                </p>
                            @endif
                            @if ($record->isSuperseded())
                                <p class="mt-1 text-xs text-slate-500">Replaced by a later issue.</p>
                            @endif
                        @endif
                    </div>

                    <div class="flex flex-shrink-0 flex-wrap gap-2 text-sm">
                        @if ($record->isIssued())
                            <a href="{{ route('documents.academic-record', $record) }}" target="_blank" class="font-medium text-brand-navy hover:underline">Print</a>
                        @elseif ($canManage)
                            <button type="button" wire:click="startEditing({{ $record->id }})" class="font-medium text-brand-navy hover:underline">Open draft</button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p class="rounded-lg bg-white p-4 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">
                No report cards issued for this student yet.
            </p>
        @endforelse
    </div>
</div>
