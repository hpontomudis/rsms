<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('students.show', $student) }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; {{ $student->fullName() }}</a>

    <div class="flex flex-col gap-3 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="font-serif text-xl font-bold text-brand-navy">Report Card</h1>
            <p class="text-sm text-slate-500">{{ $student->fullName() }} &middot; {{ $student->student_number }}</p>
        </div>
        <select wire:model.live="academic_year_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy sm:w-48">
            @foreach ($academicYears as $year)
                <option value="{{ $year->id }}">{{ $year->name }}{{ $year->is_current ? ' (current)' : '' }}</option>
            @endforeach
        </select>
    </div>

    @if ($rows->isEmpty())
        <p class="rounded-lg bg-white p-4 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">
            No subjects with recorded assessments for this academic year.
        </p>
    @else
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div class="text-2xl font-semibold text-slate-900">{{ $overallAverage !== null ? $overallAverage.'%' : '—' }}</div>
            <div class="text-sm text-slate-500">Overall average</div>
        </div>

        <div class="overflow-x-auto rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-slate-500">Subject</th>
                        @foreach ($terms as $term)
                            <th class="px-4 py-3 text-center font-medium text-slate-500">{{ $term }}</th>
                        @endforeach
                        <th class="px-4 py-3 text-center font-medium text-slate-500">Overall</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($rows as $row)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $row->subject->name }}</td>
                            @foreach ($terms as $term)
                                <td class="px-4 py-3 text-center text-slate-600">
                                    {{ $row->termAverages[$term] !== null ? $row->termAverages[$term].'%' : '—' }}
                                </td>
                            @endforeach
                            <td class="px-4 py-3 text-center font-semibold text-slate-900">
                                {{ $row->overall !== null ? $row->overall.'%' : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
