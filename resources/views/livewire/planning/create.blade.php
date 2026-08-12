<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('planning.annual.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700">&larr; Annual Programmes</a>

    <form wire:submit="save" class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div>
            <h1 class="font-serif text-xl font-bold text-brand-navy">New Annual Programme</h1>
            <p class="text-sm text-slate-500">
                A plan belongs to a class or a teaching group &mdash; not to a teacher. It stays put when the teaching changes hands.
            </p>
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Academic year</label>
            <select wire:model.live="academic_year_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                @foreach ($academicYears as $year)
                    <option value="{{ $year->id }}">{{ $year->name }}{{ $year->is_current ? ' (current)' : '' }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Taught to</label>
            <div class="flex gap-2">
                <label class="flex flex-1 cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm {{ $roster_type === 'class' ? 'border-brand-navy bg-slate-50 text-brand-navy' : 'border-slate-300 text-slate-600' }}">
                    <input type="radio" wire:model.live="roster_type" value="class" class="text-brand-navy focus:ring-brand-navy"> A class
                </label>
                <label class="flex flex-1 cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm {{ $roster_type === 'group' ? 'border-brand-navy bg-slate-50 text-brand-navy' : 'border-slate-300 text-slate-600' }}">
                    <input type="radio" wire:model.live="roster_type" value="group" class="text-brand-navy focus:ring-brand-navy"> A teaching group
                </label>
            </div>
        </div>

        @if ($roster_type === 'class')
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Class</label>
                <select wire:model.live="class_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    <option value="">Select&hellip;</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}">{{ $class->name }}</option>
                    @endforeach
                </select>
                @error('class_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        @else
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Teaching group</label>
                <select wire:model.live="teaching_group_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                    <option value="">Select&hellip;</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}">{{ $group->name }}{{ $group->englishLevel ? ' — '.$group->englishLevel->name : '' }}</option>
                    @endforeach
                </select>
                @error('teaching_group_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        @endif

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Subject</label>
            <select wire:model.live="subject_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                <option value="">Select&hellip;</option>
                @foreach ($subjects as $subject)
                    <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                @endforeach
            </select>
            @error('subject_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Learning pathway</label>
            <select wire:model="learning_pathway_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                <option value="">Select&hellip;</option>
                @foreach ($pathways as $pathway)
                    <option value="{{ $pathway->id }}">{{ $pathway->title }} — {{ $pathway->curriculumScope->displayName() }}</option>
                @endforeach
            </select>
            @error('learning_pathway_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            <p class="mt-1 text-xs text-slate-500">
                Only active pathways this roster can actually follow appear here &mdash; a class must match its grade's learning phase, a group its English level.
            </p>
            @if ($pathways->isEmpty() && $subject_id !== '' && ($class_id !== '' || $teaching_group_id !== ''))
                <p class="mt-1 text-xs text-amber-700">No eligible pathway. Check the pathway is active and covers the right phase or level.</p>
            @endif
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Title (optional)</label>
            <input type="text" wire:model="title" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
            @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Notes (optional)</label>
            <textarea wire:model="notes" rows="2" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
            @error('notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="submit" class="rounded-md bg-brand-navy px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-navy-light">Create draft</button>
            <a href="{{ route('planning.annual.index') }}" wire:navigate class="rounded-md border border-slate-300 px-5 py-2.5 text-sm text-slate-600 hover:bg-slate-50">Cancel</a>
        </div>
    </form>
</div>
