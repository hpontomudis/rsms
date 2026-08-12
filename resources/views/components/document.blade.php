@props([
    'title',
    'subtitle' => null,
    // ['Label' => 'value'] printed under the title.
    'meta' => [],
    // Set on a live preview so nobody mistakes it for an issued record.
    'preview' => false,
    'previewLabel' => 'Preview — not an issued record / Bukan dokumen resmi',
    // ['name' => ..., 'title' => ...] blocks with wet-signature space.
    'signatories' => [],
    'printedOn' => true,
    // A published record overrides the header with its own snapshot, so a later
    // rename of the school cannot rewrite a document already issued.
    'schoolName' => null,
    'schoolLine2' => null,
    'schoolAddress' => null,
])

{{--
    Shared page furniture for every printed document.

    One layout, used by report cards and planning documents alike, so the
    school header, the print stylesheet and the signing blocks exist once.

    Print rendering is browser-native: this is ordinary HTML with @page rules
    and a .no-print toolbar, exactly the pattern the payment receipt has used
    in production since Phase 3. No PDF library, no headless browser, nothing
    that shared hosting forbids. A server-side renderer, if ever needed, would
    consume this same markup rather than requiring a second template.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page {
            size: A4;
            margin: 14mm 14mm 16mm 14mm;
        }

        body { font-size: 11pt; }

        .document-sheet { max-width: 190mm; }

        /* Repeat the table head on every page of a long subject list. */
        thead { display: table-header-group; }
        tfoot { display: table-footer-group; }

        tr, .avoid-break { break-inside: avoid; page-break-inside: avoid; }

        @media print {
            .no-print { display: none !important; }

            body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .document-sheet {
                max-width: none !important;
                box-shadow: none !important;
                border: 0 !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            /* A watermark that survives printing, so a preview cannot be
               passed off as an issued document on paper. */
            .document-preview-mark {
                position: fixed;
                inset: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                transform: rotate(-30deg);
                font-size: 42pt;
                font-weight: 700;
                color: rgba(15, 23, 42, 0.10);
                pointer-events: none;
                z-index: 50;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-100 px-4 py-8 text-slate-900 antialiased">

    <div class="no-print mx-auto mb-4 flex max-w-[190mm] flex-wrap items-center justify-between gap-2">
        <a href="{{ url()->previous() }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Back</a>
        <button type="button" onclick="window.print()"
            class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">
            Print / Save as PDF
        </button>
    </div>

    @if ($preview)
        <div class="document-preview-mark hidden print:flex">{{ $previewLabel }}</div>
    @endif

    <div class="document-sheet mx-auto rounded-xl bg-white p-8 shadow-sm ring-1 ring-slate-200 print:rounded-none print:ring-0">

        {{-- School header. Read from config/school.php, never retyped. --}}
        <header class="avoid-break mb-6 flex items-center gap-4 border-b-2 border-brand-navy pb-4">
            <img src="{{ asset(config('school.logo_path')) }}" alt="" class="h-16 w-16">
            <div class="min-w-0">
                <h1 class="font-serif text-lg font-bold text-brand-navy">{{ $schoolName ?? config('school.name') }}</h1>
                @if ($schoolLine2 ?? config('school.line2'))
                    <p class="text-xs text-slate-600">{{ $schoolLine2 ?? config('school.line2') }}</p>
                @endif
                @if ($schoolAddress ?? config('school.address'))
                    <p class="text-xs text-slate-500">{{ $schoolAddress ?? config('school.address') }}</p>
                @endif
                @if (config('school.contact'))
                    <p class="text-xs text-slate-500">{{ config('school.contact') }}</p>
                @endif
            </div>
        </header>

        @if ($preview)
            <p class="no-print mb-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200">
                {{ $previewLabel }}
            </p>
        @endif

        <div class="avoid-break mb-5">
            <h2 class="font-serif text-xl font-bold text-brand-navy">{{ $title }}</h2>
            @if ($subtitle)<p class="text-sm text-slate-600">{{ $subtitle }}</p>@endif

            @if (! empty($meta))
                <dl class="mt-3 grid grid-cols-1 gap-x-8 gap-y-1 text-sm sm:grid-cols-2">
                    @foreach ($meta as $label => $value)
                        <div class="flex gap-2">
                            <dt class="w-32 flex-shrink-0 text-slate-500">{{ $label }}</dt>
                            <dd class="min-w-0 text-slate-900">{{ $value !== null && $value !== '' ? $value : '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </div>

        {{ $slot }}

        @if (! empty($signatories))
            <div class="avoid-break mt-10 grid grid-cols-2 gap-8 text-sm">
                @foreach ($signatories as $signatory)
                    <div class="text-center">
                        <p class="text-slate-600">{{ $signatory['title'] ?? '' }}</p>
                        {{-- Wet-signature space. No image, no QR, no digital
                             signature: the signed paper copy is the authority. --}}
                        <div class="h-16"></div>
                        <p class="border-t border-slate-400 pt-1 font-medium text-slate-900">
                            {{ ($signatory['name'] ?? '') !== '' ? $signatory['name'] : '(...........................)' }}
                        </p>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($printedOn)
            <p class="mt-8 text-center text-xs text-slate-400">
                Dicetak / Printed {{ now()->format('d M Y H:i') }} &middot; {{ config('app.name') }}
            </p>
        @endif
    </div>
</body>
</html>
