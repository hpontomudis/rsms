{{--
    One template for all five planning documents.

    They differ only in their sections, which the builders supply, so ATP,
    Prota, Prosem, Modul Ajar and Jurnal Harian share this file rather than
    each owning a near-identical copy.
--}}
<x-document
    :title="$document->title"
    :subtitle="$document->subtitle"
    :meta="$document->meta"
    :signatories="$document->signatories"
>
    @foreach ($document->sections as $section)
        <section class="mb-6">
            <h3 class="avoid-break mb-2 border-b border-slate-300 pb-1 text-sm font-semibold uppercase tracking-wide text-slate-700">
                {{ $section->heading }}
            </h3>

            @if ($section->isEmpty())
                <p class="text-sm text-slate-500">{{ $section->emptyMessage ?? '—' }}</p>
            @elseif ($section->isTable())
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-b-2 border-slate-300 bg-slate-50">
                            @foreach ($section->columns as $column)
                                <th class="px-2 py-1.5 text-left font-semibold text-slate-700">{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($section->rows as $row)
                            <tr class="border-b border-slate-200">
                                @foreach ($row as $cell)
                                    <td class="px-2 py-1.5 align-top text-slate-800">{{ $cell !== null && $cell !== '' ? $cell : '—' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="whitespace-pre-line text-sm text-slate-800">{{ $section->body }}</p>
            @endif
        </section>
    @endforeach
</x-document>
