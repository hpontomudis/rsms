<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('communications.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Communications</a>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="mb-2 flex flex-wrap items-center gap-2">
            <x-status-badge :status="$communication->status" />
            @if ($communication->priority !== 'normal')
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ring-1 ring-inset
                    {{ $communication->priority === 'urgent' ? 'bg-red-50 text-red-700 ring-red-200' : 'bg-amber-50 text-amber-700 ring-amber-200' }}">
                    {{ $communication->priority }}
                </span>
            @endif
        </div>

        @if ($communication->isDraft())
            <form wire:submit="saveContent" class="space-y-4">
                @if ($canManageAudience)
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Display sender</label>
                        <input type="text" wire:model="display_sender" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy" />
                        @error('display_sender') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Title</label>
                        <input type="text" wire:model="title" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy" />
                        @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Body</label>
                        <textarea wire:model="body" rows="6" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy"></textarea>
                        @error('body') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Priority</label>
                            <select wire:model="priority" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                                <option value="normal">Normal</option>
                                <option value="important">Important</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Expires (optional)</label>
                            <input type="date" wire:model="expires_at" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy" />
                        </div>
                    </div>
                    <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Save changes</button>
                @else
                    <h1 class="font-serif text-xl font-bold text-brand-navy">{{ $communication->title }}</h1>
                    <p class="text-xs text-slate-500">From {{ $communication->display_sender }}</p>
                    <p class="whitespace-pre-line text-sm text-slate-700">{{ $communication->body }}</p>
                @endif
            </form>
        @else
            <h1 class="font-serif text-xl font-bold text-brand-navy">{{ $communication->title }}</h1>
            <p class="text-xs text-slate-500">
                From {{ $communication->display_sender }} &middot; {{ $communication->published_at->format('d M Y, H:i') }}
            </p>
            <p class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ $communication->body }}</p>
        @endif
    </div>

    @if ($communication->isDraft() && $canUseAi)
        {{-- --------------------------------------------------- AI assist --}}
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-700">AI assistance</h2>
                @unless ($showAiPanel)
                    <button type="button" wire:click="toggleAiPanel" class="text-xs font-medium text-brand-navy hover:underline">Improve wording&hellip;</button>
                @else
                    <button type="button" wire:click="toggleAiPanel" class="text-xs text-slate-500 hover:underline">Close</button>
                @endunless
            </div>

            @if ($showAiPanel)
                <p class="mb-3 rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600">
                    AI assistance sends this draft's title and body to an external AI provider to suggest a rewrite.
                    Nothing is saved until you review the suggestion and click Apply, then Save changes yourself.
                </p>

                <div class="mb-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Rewrite for</label>
                        <select wire:model="ai_mode" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($aiModes as $mode)
                                <option value="{{ $mode }}">{{ ucfirst(str_replace('_', ' ', $mode)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Language</label>
                        <select wire:model="ai_language" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="id">Indonesian</option>
                            <option value="en">English</option>
                            <option value="bilingual">Bilingual (ID + EN)</option>
                        </select>
                    </div>
                </div>

                <button type="button" wire:click="generateAiSuggestion" wire:loading.attr="disabled"
                    class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light disabled:opacity-50">
                    <span wire:loading.remove wire:target="generateAiSuggestion">Generate suggestion</span>
                    <span wire:loading wire:target="generateAiSuggestion">Generating&hellip;</span>
                </button>

                @if ($aiError)
                    <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">{{ $aiError }}</p>
                @endif

                @if ($aiSuggestion)
                    <div class="mt-4 rounded-lg bg-slate-50 p-3">
                        <p class="mb-2 text-xs font-medium text-slate-500">AI-generated suggestion &mdash; review for accuracy before applying.</p>
                        <p class="whitespace-pre-line rounded-md bg-white p-3 text-sm text-slate-800 ring-1 ring-slate-200">{{ $aiSuggestion }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" wire:click="applyAiSuggestion" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Apply</button>
                            <button type="button" wire:click="generateAiSuggestion" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Regenerate</button>
                            <button type="button" wire:click="dismissAiSuggestion" class="rounded-md px-4 py-2 text-sm text-slate-500 hover:bg-slate-100">Dismiss</button>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    @endif

    @if ($communication->isDraft() && $canManageAudience)
        {{-- ---------------------------------------------------- audience --}}
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-700">Audience</h2>
                @unless ($showAddRule)
                    <button type="button" wire:click="startAddingRule" class="text-xs font-medium text-brand-navy hover:underline">+ Add rule</button>
                @endunless
            </div>

            @if ($showAddRule)
                <form wire:submit="addRule" class="mb-4 space-y-3 rounded-lg bg-slate-50 p-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Audience type</label>
                        <select wire:model.live="rule_type" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
                            <option value="">Select&hellip;</option>
                            @unless ($isTeacher)
                                <option value="everyone">Everyone</option>
                                <option value="all_staff">All staff</option>
                                <option value="staff_category">Staff category</option>
                                <option value="role">Role</option>
                            @endunless
                            <option value="school_class_students">Class &mdash; students</option>
                            <option value="school_class_guardians">Class &mdash; guardians</option>
                            <option value="teaching_group_students">Teaching group &mdash; students</option>
                            <option value="teaching_group_guardians">Teaching group &mdash; guardians</option>
                            @unless ($isTeacher)
                                <option value="selected_staff">Selected staff</option>
                                <option value="selected_users">Selected users</option>
                            @endunless
                            <option value="selected_students">Selected students</option>
                            <option value="selected_guardians">Selected guardians</option>
                        </select>
                        @error('rule_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    @if ($rule_type === 'staff_category')
                        <select wire:model="staff_category_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Select category&hellip;</option>
                            @foreach ($staffCategories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('staff_category_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    @elseif ($rule_type === 'role')
                        <select wire:model="role_name" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Select role&hellip;</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role }}">{{ $role }}</option>
                            @endforeach
                        </select>
                        @error('role_name') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    @elseif (in_array($rule_type, ['school_class_students', 'school_class_guardians']))
                        <select wire:model="school_class_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Select class&hellip;</option>
                            @foreach ($availableClasses as $class)
                                <option value="{{ $class->id }}">{{ $class->name }}</option>
                            @endforeach
                        </select>
                        @if ($availableClasses->isEmpty())
                            <p class="text-xs text-amber-700">You have no current class assignment.</p>
                        @endif
                        @error('school_class_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    @elseif (in_array($rule_type, ['teaching_group_students', 'teaching_group_guardians']))
                        <select wire:model="teaching_group_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Select teaching group&hellip;</option>
                            @foreach ($availableGroups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                        @if ($availableGroups->isEmpty())
                            <p class="text-xs text-amber-700">You have no current teaching group assignment.</p>
                        @endif
                        @error('teaching_group_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    @elseif ($rule_type === 'selected_staff')
                        <select wire:model="selected_ids" multiple size="6" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($availableStaff as $staff)
                                <option value="{{ $staff->id }}">{{ $staff->fullName() }}</option>
                            @endforeach
                        </select>
                        @error('ids') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    @elseif ($rule_type === 'selected_guardians')
                        <select wire:model="selected_ids" multiple size="6" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($availableGuardians as $guardian)
                                <option value="{{ $guardian->id }}">{{ $guardian->fullName() }}</option>
                            @endforeach
                        </select>
                        @if ($availableGuardians->isEmpty())
                            <p class="text-xs text-amber-700">No guardians are within your current teaching scope.</p>
                        @endif
                        @error('ids') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    @elseif ($rule_type === 'selected_students')
                        <select wire:model="selected_ids" multiple size="6" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($availableStudents as $student)
                                <option value="{{ $student->id }}">{{ $student->fullName() }}</option>
                            @endforeach
                        </select>
                        @if ($availableStudents->isEmpty())
                            <p class="text-xs text-amber-700">No students are within your current teaching scope.</p>
                        @endif
                        @error('ids') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    @elseif ($rule_type === 'selected_users')
                        <select wire:model="selected_ids" multiple size="6" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($availableUsers as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                        @error('ids') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    @endif

                    <div class="flex gap-2">
                        <button type="submit" class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Add</button>
                        <button type="button" wire:click="$set('showAddRule', false)" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
                    </div>
                </form>
            @endif

            @forelse ($communication->audienceRules as $rule)
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 py-2 last:border-0">
                    <span class="text-sm text-slate-800">{{ $rule->displayLabel() }}</span>
                    <button type="button" wire:click="removeRule({{ $rule->id }})" wire:confirm="Remove this audience rule?" class="text-xs text-red-500 hover:text-red-700">Remove</button>
                </div>
            @empty
                <p class="text-sm text-slate-500">No audience rules yet. Add at least one before publishing.</p>
            @endforelse
        </div>

        {{-- ------------------------------------------------- live preview --}}
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Audience preview (live)</h2>
            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="rounded-lg bg-slate-50 p-3">
                    <div class="text-2xl font-bold text-brand-navy">{{ $preview['resolved'] }}</div>
                    <div class="text-xs text-slate-500">Resolved recipients</div>
                </div>
                <div class="rounded-lg bg-emerald-50 p-3">
                    <div class="text-2xl font-bold text-emerald-700">{{ $preview['reachable'] }}</div>
                    <div class="text-xs text-slate-500">Reachable in RSMS</div>
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <div class="text-2xl font-bold text-slate-500">{{ $preview['unreachable'] }}</div>
                    <div class="text-xs text-slate-500">No RSMS login</div>
                </div>
            </div>

            @if ($preview['resolved'] > 0)
                <p class="mt-3 text-xs text-slate-500">
                    By type:
                    @foreach ($preview['byType'] as $type => $count)
                        {{ $count }} {{ \Illuminate\Support\Str::plural($type, $count) }}@if (! $loop->last), @endif
                    @endforeach
                </p>
            @endif

            @if ($preview['resolved'] > 0 && $preview['reachable'] === 0)
                <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-xs font-medium text-red-800">
                    {{ $preview['resolved'] }} {{ Illuminate\Support\Str::plural('recipient', $preview['resolved']) }} will be recorded, but none currently have an RSMS login.
                    This communication will not reach them outside RSMS.
                </p>
            @elseif ($preview['unreachable'] > 0)
                <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    {{ $preview['unreachable'] }} of {{ $preview['resolved'] }} resolved recipients have no RSMS login and will be
                    recorded as recipients only -- not reachable in-app.
                </p>
            @endif

            <p class="mt-3 rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600">
                This preview is live and will be re-resolved fresh at the moment you publish.
                V8A is in-app only: no email or WhatsApp is sent, regardless of these numbers.
            </p>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            @error('status') <p class="mb-2 text-xs text-red-600">{{ $message }}</p> @enderror
            <div class="flex flex-wrap gap-3">
                @if ($canPublish)
                    <button type="button" wire:click="publish" wire:confirm="Publish this communication? Content and audience freeze permanently and cannot be edited or undone."
                        class="rounded-md bg-brand-navy px-4 py-2 text-sm font-medium text-white hover:bg-brand-navy-light">Publish</button>
                @endif
                @if ($canDelete)
                    <button type="button" wire:click="deleteDraft" wire:confirm="Delete this draft?" class="rounded-md px-4 py-2 text-sm text-red-600 hover:bg-red-50">Delete draft</button>
                @endif
            </div>
        </div>
    @endif

    @unless ($communication->isDraft())
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Audience</h2>
            @if ($communication->audience_summary_snapshot)
                <p class="mb-3 text-sm text-slate-700">{{ $communication->audience_summary_snapshot }}</p>
            @endif
            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="rounded-lg bg-slate-50 p-3">
                    <div class="text-2xl font-bold text-brand-navy">{{ $recipientStats['resolved'] }}</div>
                    <div class="text-xs text-slate-500">Recipients</div>
                </div>
                <div class="rounded-lg bg-emerald-50 p-3">
                    <div class="text-2xl font-bold text-emerald-700">{{ $recipientStats['reachable'] }}</div>
                    <div class="text-xs text-slate-500">Reachable in RSMS</div>
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <div class="text-2xl font-bold text-slate-500">{{ $recipientStats['read'] }}</div>
                    <div class="text-xs text-slate-500">Opened</div>
                </div>
            </div>
            @if ($recipientStats['reachable'] === 0)
                <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-xs font-medium text-red-800">
                    Recorded as recipients &mdash; no RSMS login available for any of them. This communication was not delivered outside RSMS.
                </p>
            @endif
            <p class="mt-3 text-xs text-slate-500">
                This audience is historical: later changes to class, guardian or role membership do not alter who this was sent to.
            </p>
        </div>

        @if ($canArchive || $canDelete)
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex flex-wrap gap-3">
                    @if ($communication->isPublished() && $canArchive)
                        <button type="button" wire:click="archive" wire:confirm="Archive this communication? It stays in recipient history."
                            class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Archive</button>
                    @endif
                </div>
            </div>
        @endif
    @endunless
</div>
