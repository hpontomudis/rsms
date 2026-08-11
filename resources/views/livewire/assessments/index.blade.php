<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ $backUrl }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; {{ $backLabel }}</a>

    <div class="flex flex-col gap-3 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="font-serif text-xl font-bold text-brand-navy">{{ $classSubject->subject->name }}</h1>
            <p class="text-sm text-slate-500">
                {{ $classSubject->displayName() }}
                @unless ($classSubject->isClassBacked())
                    <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600">Teaching Group</span>
                @endunless
                &middot; taught by {{ $classSubject->teacher->fullName() }}
            </p>
        </div>
        @can('createFor', [\App\Models\Assessment::class, $classSubject])
            <a href="{{ route('assessments.create', ['class_subject_id' => $classSubject->id]) }}" class="rounded-md bg-brand-navy px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-navy-light">
                + New Assessment
            </a>
        @endcan
    </div>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-3 text-sm font-semibold text-slate-700">Assessments</h2>
        <ul class="divide-y divide-slate-100 text-sm">
            @forelse ($assessments as $assessment)
                <li class="flex items-center justify-between py-2">
                    <a href="{{ route('assessments.show', $assessment) }}" class="hover:underline">
                        <span class="font-medium text-slate-900">{{ $assessment->name }}</span>
                        <span class="ml-1 text-slate-500">({{ $assessment->academicPeriod->name }})</span>
                    </a>
                    <span class="text-slate-500">{{ $assessment->results_count }} scored &middot; max {{ (int) $assessment->max_score }}</span>
                </li>
            @empty
                <li class="py-2 text-slate-500">No assessments yet.</li>
            @endforelse
        </ul>
    </div>
</div>
