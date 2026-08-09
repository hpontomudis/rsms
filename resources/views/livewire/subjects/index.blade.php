<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="font-serif text-xl font-bold text-brand-navy">Subjects</h1>
        @can('create', \App\Models\Subject::class)
            <a href="{{ route('subjects.create') }}" class="inline-flex justify-center rounded-md bg-brand-navy px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-navy-light">
                + Add Subject
            </a>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Name</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-500">Grade</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($subjects as $subject)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $subject->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $subject->grade->name ?? 'All grades' }}</td>
                        <td class="px-4 py-3 text-right">
                            @can('update', $subject)
                                <a href="{{ route('subjects.edit', $subject) }}" class="text-slate-600 hover:text-slate-900">Edit</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-6 text-center text-slate-500">No subjects yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $subjects->links() }}</div>
</div>
