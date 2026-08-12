{{--
    The printed report card. Serves a live preview and an issued record alike,
    because it reads only the ViewModel and never a model.
--}}
<x-document
    :title="$document->title"
    :meta="$document->meta()"
    :preview="$document->isPreview"
    :signatories="$document->signatories"
    :school-name="$document->schoolName"
    :school-line2="$document->schoolLine2"
    :school-address="$document->schoolAddress"
>
    @if ($document->statusLabel)
        <p class="mb-4 rounded-md bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700">
            {{ $document->statusLabel }} &mdash; a later issue of this report card replaced this one.
        </p>
    @endif

    <table class="w-full border-collapse text-sm">
        <thead>
            <tr class="border-y-2 border-slate-300 bg-slate-50">
                <th class="w-10 px-2 py-2 text-left font-semibold text-slate-700">No</th>
                <th class="px-2 py-2 text-left font-semibold text-slate-700">Mata Pelajaran / Subject</th>
                @foreach ($document->columns as $column)
                    <th class="w-24 px-2 py-2 text-center font-semibold text-slate-700">{{ $column }}</th>
                @endforeach
                @if ($document->showOverallColumn)
                    <th class="w-24 px-2 py-2 text-center font-semibold text-slate-700">Rata-rata</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($document->rows as $index => $row)
                <tr class="border-b border-slate-200">
                    <td class="px-2 py-2 text-slate-500">{{ $index + 1 }}</td>
                    <td class="px-2 py-2 text-slate-900">{{ $row->subjectName }}</td>
                    @foreach ($row->scores as $score)
                        <td class="px-2 py-2 text-center text-slate-800">{{ $score !== null ? $score : '—' }}</td>
                    @endforeach
                    @if ($document->showOverallColumn)
                        <td class="px-2 py-2 text-center font-semibold text-slate-900">
                            {{ $row->overall !== null ? $row->overall : '—' }}
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 2 + count($document->columns) + ($document->showOverallColumn ? 1 : 0) }}"
                        class="px-2 py-4 text-center text-slate-500">
                        No subjects recorded for this period.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="border-t-2 border-slate-300">
                <td class="px-2 py-2"></td>
                <td class="px-2 py-2 font-semibold text-slate-700">Rata-rata / Overall</td>
                <td colspan="{{ count($document->columns) + ($document->showOverallColumn ? 1 : 0) }}"
                    class="px-2 py-2 text-center text-base font-bold text-brand-navy">
                    {{ $document->overallAverage !== null ? $document->overallAverage : '—' }}
                </td>
            </tr>
        </tfoot>
    </table>

    @if ($document->homeroomComment)
        <div class="avoid-break mt-6">
            <h3 class="mb-1 text-sm font-semibold text-slate-700">Catatan Wali Kelas / Homeroom Comment</h3>
            <p class="whitespace-pre-line rounded-md border border-slate-200 p-3 text-sm text-slate-800">{{ $document->homeroomComment }}</p>
        </div>
    @endif
</x-document>
