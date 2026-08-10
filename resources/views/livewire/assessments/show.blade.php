<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('assessments.index', ['class_subject_id' => $assessment->classSubject->id]) }}" class="text-sm text-slate-500 hover:text-slate-700">
        &larr; {{ $assessment->classSubject->subject->name }}
    </a>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h1 class="font-serif text-xl font-bold text-brand-navy">{{ $assessment->name }}</h1>
        <p class="text-sm text-slate-500">
            {{ $assessment->classSubject->schoolClass->name }} &middot; {{ $assessment->classSubject->subject->name }}
            &middot; {{ $assessment->academicPeriod->name }} &middot; {{ $assessment->assessment_date->format('d M Y') }}
        </p>
        <p class="mt-1 text-xs text-slate-400">Max score: {{ (int) $assessment->max_score }}</p>
    </div>

    @if ($saved)
        <div class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700 ring-1 ring-emerald-200">
            Scores saved.
        </div>
    @endif

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="divide-y divide-slate-100">
            @foreach ($students as $student)
                <div class="flex items-center justify-between gap-3 py-3">
                    <span class="font-medium text-slate-900">{{ $student->fullName() }}</span>
                    <div class="flex items-center gap-1.5">
                        <input
                            type="number"
                            wire:model="scores.{{ $student->id }}"
                            min="0"
                            max="{{ (int) $assessment->max_score }}"
                            step="0.5"
                            @disabled(! $canRecord)
                            class="w-20 rounded-md border border-slate-300 px-3 py-2 text-right text-base focus:border-brand-navy focus:ring-1 focus:ring-brand-navy {{ ! $canRecord ? 'bg-slate-50' : '' }}"
                        >
                        <span class="text-sm text-slate-400">/ {{ (int) $assessment->max_score }}</span>
                    </div>
                    @error("scores.{$student->id}") <p class="w-full text-right text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>

        @if ($canRecord)
            <button
                type="button"
                wire:click="save"
                class="mt-4 w-full rounded-md bg-brand-navy px-5 py-3 text-base font-medium text-white hover:bg-brand-navy-light"
                wire:loading.attr="disabled"
            >
                Save Scores
            </button>
        @endif
    </div>
</div>
