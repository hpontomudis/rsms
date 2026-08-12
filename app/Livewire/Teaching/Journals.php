<?php

namespace App\Livewire\Teaching;

use App\Models\AcademicPeriod;
use App\Models\ClassSubject;
use App\Models\CurriculumScope;
use App\Models\DailyJournal;
use App\Models\Staff;
use App\Services\DailyJournalService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The teaching record for ONE assignment, newest first.
 *
 * The create form is the interesting part: the period is offered from the date
 * rather than assumed, and zero or several candidates are both reported instead
 * of resolved.
 */
#[Layout('layouts.app')]
class Journals extends Component
{
    public ClassSubject $classSubject;

    public bool $showCreate = false;

    public string $journal_date = '';

    public string $academic_period_id = '';

    public string $curriculum_scope_id = '';

    public string $conducted_by_staff_id = '';

    public string $topic = '';

    public string $actual_activity = '';

    public function mount(ClassSubject $classSubject): void
    {
        abort_unless(Auth::user()->can('academics.view'), 403);
        $this->classSubject = $classSubject;
        $this->conducted_by_staff_id = (string) $classSubject->staff_id;
    }

    /** Re-offer the period whenever the date moves. */
    public function updatedJournalDate(): void
    {
        $this->academic_period_id = '';
    }

    public function create(DailyJournalService $journals)
    {
        $this->authorize('createFor', [DailyJournal::class, $this->classSubject]);

        $validated = $this->validate([
            'journal_date' => ['required', 'date'],
            'academic_period_id' => ['required', 'exists:academic_periods,id'],
            'curriculum_scope_id' => ['required', 'exists:curriculum_scopes,id'],
            'conducted_by_staff_id' => ['required', 'exists:staff,id'],
            'topic' => ['required', 'string', 'max:200'],
            'actual_activity' => ['required', 'string'],
        ]);

        $journal = $journals->create(
            $this->classSubject,
            CurriculumScope::findOrFail($validated['curriculum_scope_id']),
            AcademicPeriod::findOrFail($validated['academic_period_id']),
            $validated['journal_date'],
            Staff::findOrFail($validated['conducted_by_staff_id']),
            $validated,
            historicalBackfill: ! $this->classSubject->isActive(),
        );

        return $this->redirect(route('teaching.journal.show', $journal), navigate: true);
    }

    public function render(DailyJournalService $journals)
    {
        $scopes = $journals->eligibleScopes($this->classSubject);

        if ($this->curriculum_scope_id === '' && $scopes->count() === 1) {
            $this->curriculum_scope_id = (string) $scopes->first()->id;
        }

        $periods = $this->journal_date !== ''
            ? $journals->periodsFor($this->classSubject, $this->journal_date)
            : collect();

        if ($this->academic_period_id === '' && $periods->count() === 1) {
            $this->academic_period_id = (string) $periods->first()->id;
        }

        return view('livewire.teaching.journals', [
            'vocabulary' => $scopes->first()?->curriculum?->vocabulary()
                ?? ['journal' => 'Daily Teaching Journal', 'journals' => 'Daily Teaching Journal'],
            'scopes' => $scopes,
            'periods' => $periods,
            'staff' => Staff::orderBy('first_name')->get(),
            'journals' => DailyJournal::where('class_subject_id', $this->classSubject->id)
                ->with(['conductedBy', 'academicPeriod'])
                ->orderByDesc('journal_date')->orderByDesc('id')->get(),
            'canCreate' => Auth::user()->can('createFor', [DailyJournal::class, $this->classSubject]),
            'isBackfill' => ! $this->classSubject->isActive(),
        ]);
    }
}
