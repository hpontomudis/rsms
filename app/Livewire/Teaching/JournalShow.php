<?php

namespace App\Livewire\Teaching;

use App\Ai\AiGenerationService;
use App\Ai\DailyJournalAssistant;
use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\DailyJournal;
use App\Models\LearningObjective;
use App\Models\SemesterProgrammeItem;
use App\Models\Staff;
use App\Models\TeachingModule;
use App\Services\DailyJournalService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * One session: what happened, what it was meant to be, and what it used.
 *
 * The attendance panel is READ-ONLY context for class-backed sessions and
 * nothing at all for teaching groups, which have no attendance engine. Nothing
 * is stored about it -- Daily Journal is not attendance.
 */
#[Layout('layouts.app')]
class JournalShow extends Component
{
    public DailyJournal $dailyJournal;

    public array $record = [];

    public string $learning_objective_id = '';

    public string $assessment_id = '';

    public string $teaching_module_id = '';

    public string $semester_programme_item_id = '';

    public string $conducted_by_staff_id = '';

    public bool $showAiPanel = false;

    public string $ai_language = 'id';

    public string $teacherNotesForAi = '';

    public ?string $aiReflection = null;

    public ?string $aiFollowUp = null;

    public ?int $aiGenerationId = null;

    public ?string $aiError = null;

    public function mount(DailyJournal $dailyJournal): void
    {
        $this->authorize('view', $dailyJournal);
        $this->dailyJournal = $dailyJournal;
        $this->record = $dailyJournal->only([
            'topic', 'actual_activity', 'reflection', 'follow_up',
            'meeting_number', 'actual_lesson_periods',
        ]);
        $this->teaching_module_id = (string) ($dailyJournal->teaching_module_id ?? '');
        $this->semester_programme_item_id = (string) ($dailyJournal->semester_programme_item_id ?? '');
        $this->conducted_by_staff_id = (string) $dailyJournal->conducted_by_staff_id;
    }

    public function save(DailyJournalService $journals): void
    {
        $this->authorize('update', $this->dailyJournal);

        $this->validate([
            'record.topic' => ['required', 'string', 'max:200'],
            'record.actual_activity' => ['required', 'string'],
            'record.reflection' => ['nullable', 'string'],
            'record.follow_up' => ['nullable', 'string'],
            'record.meeting_number' => ['nullable', 'integer', 'min:1'],
            'record.actual_lesson_periods' => ['nullable', 'integer', 'min:1'],
        ]);

        $journals->update($this->dailyJournal, array_map(
            fn ($value) => $value === '' ? null : $value,
            $this->record,
        ));

        $this->dailyJournal->refresh();
    }

    // ------------------------------------------------------------------ AI
    //
    // Generate never touches $this->dailyJournal or calls any
    // DailyJournalService method -- it only ever sets the transient aiReflection
    // /aiFollowUp properties. Only applyReflection()/applyFollowUp()/applyBoth()
    // copy text into the (still unsaved) $this->record array; save() above
    // remains the only path that writes to the database, completely unaware
    // AI was involved.

    public function toggleAiPanel(): void
    {
        $this->authorize('update', $this->dailyJournal);
        $this->showAiPanel = ! $this->showAiPanel;
        $this->reset(['aiReflection', 'aiFollowUp', 'aiGenerationId', 'aiError']);
    }

    public function generateAiSuggestion(DailyJournalAssistant $assistant): void
    {
        $this->authorize('update', $this->dailyJournal);
        $this->reset(['aiReflection', 'aiFollowUp', 'aiGenerationId', 'aiError']);

        try {
            $result = $assistant->suggest(
                Auth::user(),
                $this->dailyJournal,
                $this->ai_language,
                $this->teacherNotesForAi,
            );
        } catch (AuthorizationException) {
            $this->aiError = 'AI assistance is not available for this journal entry.';

            return;
        }

        if ($result->isUsable()) {
            $this->aiReflection = $result->suggestion->reflection;
            $this->aiFollowUp = $result->suggestion->followUp;
            $this->aiGenerationId = $result->generationId;

            return;
        }

        $this->aiError = match ($result->status) {
            'rate_limited' => 'You have reached the AI assistance limit for now. You can continue editing manually.',
            'unusable' => "AI assistance couldn't produce a usable suggestion this time. You can try again or continue editing manually.",
            default => 'AI assistance is temporarily unavailable. You can continue editing manually.',
        };
    }

    public function applyReflection(AiGenerationService $generations): void
    {
        $this->authorize('update', $this->dailyJournal);

        if ($this->aiReflection === null) {
            return;
        }

        $this->record['reflection'] = $this->aiReflection;
        $this->markAiAccepted($generations);
    }

    public function applyFollowUp(AiGenerationService $generations): void
    {
        $this->authorize('update', $this->dailyJournal);

        if ($this->aiFollowUp === null) {
            return;
        }

        $this->record['follow_up'] = $this->aiFollowUp;
        $this->markAiAccepted($generations);
    }

    public function applyBoth(AiGenerationService $generations): void
    {
        $this->authorize('update', $this->dailyJournal);

        if ($this->aiReflection === null && $this->aiFollowUp === null) {
            return;
        }

        if ($this->aiReflection !== null) {
            $this->record['reflection'] = $this->aiReflection;
        }

        if ($this->aiFollowUp !== null) {
            $this->record['follow_up'] = $this->aiFollowUp;
        }

        $this->markAiAccepted($generations);
    }

    public function dismissAiSuggestion(): void
    {
        $this->reset(['aiReflection', 'aiFollowUp', 'aiGenerationId', 'aiError']);
    }

    /**
     * accepted_at means only that at least one suggested field from this
     * generation was explicitly applied to unsaved form state -- not that
     * every field was used, and not that the Journal itself was saved.
     */
    private function markAiAccepted(AiGenerationService $generations): void
    {
        if ($this->aiGenerationId !== null) {
            $generations->markAccepted($this->aiGenerationId);
        }
    }

    public function setConductor(DailyJournalService $journals): void
    {
        $this->authorize('update', $this->dailyJournal);

        $journals->setConductor($this->dailyJournal, Staff::findOrFail($this->conducted_by_staff_id));

        $this->dailyJournal->refresh();
    }

    public function setModule(DailyJournalService $journals): void
    {
        $this->authorize('update', $this->dailyJournal);

        $journals->linkModule(
            $this->dailyJournal,
            $this->teaching_module_id !== '' ? TeachingModule::findOrFail($this->teaching_module_id) : null,
        );

        $this->dailyJournal->refresh();
    }

    public function setSlot(DailyJournalService $journals): void
    {
        $this->authorize('update', $this->dailyJournal);

        $journals->linkSlot(
            $this->dailyJournal,
            $this->semester_programme_item_id !== '' ? SemesterProgrammeItem::findOrFail($this->semester_programme_item_id) : null,
        );

        $this->dailyJournal->refresh();
    }

    public function addObjective(DailyJournalService $journals): void
    {
        $this->authorize('update', $this->dailyJournal);

        $this->validate(['learning_objective_id' => ['required', 'exists:learning_objectives,id']]);

        $journals->linkObjective($this->dailyJournal, LearningObjective::findOrFail($this->learning_objective_id));

        $this->reset('learning_objective_id');
        $this->dailyJournal->refresh();
    }

    public function removeObjective(int $objectiveId, DailyJournalService $journals): void
    {
        $this->authorize('update', $this->dailyJournal);

        $journals->unlinkObjective($this->dailyJournal, LearningObjective::findOrFail($objectiveId));

        $this->dailyJournal->refresh();
    }

    public function addAssessment(DailyJournalService $journals): void
    {
        $this->authorize('update', $this->dailyJournal);

        $this->validate(['assessment_id' => ['required', 'exists:assessments,id']]);

        $journals->linkAssessment($this->dailyJournal, Assessment::findOrFail($this->assessment_id));

        $this->reset('assessment_id');
        $this->dailyJournal->refresh();
    }

    public function removeAssessment(int $assessmentId, DailyJournalService $journals): void
    {
        $this->authorize('update', $this->dailyJournal);

        $journals->unlinkAssessment($this->dailyJournal, Assessment::findOrFail($assessmentId));

        $this->dailyJournal->refresh();
    }

    public function finalize(DailyJournalService $journals): void
    {
        $this->authorize('finalize', $this->dailyJournal);

        $journals->finalize($this->dailyJournal);

        $this->dailyJournal->refresh();
    }

    public function render()
    {
        $journal = $this->dailyJournal;
        $editable = Auth::user()->can('update', $journal);

        return view('livewire.teaching.journal-show', [
            'vocabulary' => $journal->curriculumScope->curriculum->vocabulary(),
            'objectives' => $journal->objectives(),
            'available' => $editable
                ? LearningObjective::where('curriculum_scope_id', $journal->curriculum_scope_id)
                    ->where('subject_id', $journal->subject_id)
                    ->whereNotIn('id', $journal->objectiveLinks()->pluck('learning_objective_id'))
                    ->orderBy('reference_order')->get()
                : collect(),
            'assessmentLinks' => $journal->assessmentLinks()->with('assessment')->get(),
            'availableAssessments' => $editable
                ? Assessment::where('class_subject_id', $journal->class_subject_id)
                    ->whereNotIn('id', $journal->assessmentLinks()->pluck('assessment_id'))
                    ->orderByDesc('assessment_date')->get()
                : collect(),
            'modules' => TeachingModule::citable()
                ->where('subject_id', $journal->subject_id)
                ->where('curriculum_scope_id', $journal->curriculum_scope_id)
                ->when($journal->isClassBacked(),
                    fn ($q) => $q->where('class_id', $journal->class_id),
                    fn ($q) => $q->where('teaching_group_id', $journal->teaching_group_id))
                ->with('classSubject.teacher')
                ->get(),
            'slots' => SemesterProgrammeItem::where('academic_period_id', $journal->academic_period_id)
                ->whereHas('semesterProgramme.annualProgramme', fn ($a) => $a
                    ->where('subject_id', $journal->subject_id)
                    ->where('curriculum_scope_id', $journal->curriculum_scope_id)
                    ->when($journal->isClassBacked(),
                        fn ($x) => $x->where('class_id', $journal->class_id),
                        fn ($x) => $x->where('teaching_group_id', $journal->teaching_group_id)))
                ->get(),
            'staff' => Staff::orderBy('first_name')->get(),
            // Read-only context, never stored. Groups have no attendance at all.
            'attendance' => $journal->isClassBacked()
                ? Attendance::where('class_id', $journal->class_id)
                    ->whereDate('date', $journal->journal_date)->with('records')->first()
                : null,
            'canEdit' => $editable,
            'canFinalize' => Auth::user()->can('finalize', $journal),
            // isDraft() is explicit here, not implied by $editable -- DailyJournalPolicy::update()
            // legitimately allows a manager to edit a FINALIZED journal (correction), but AI
            // assistance must never be offered there. See DailyJournalAssistant's docblock.
            'canUseAi' => config('ai.enabled') && Auth::user()->can('ai.use') && $editable && $journal->isDraft(),
            'aiLanguages' => DailyJournalAssistant::LANGUAGES,
        ]);
    }
}
