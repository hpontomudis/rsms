<?php

namespace App\Livewire\Teaching;

use App\Ai\AiGenerationService;
use App\Ai\TeachingModuleAssistant;
use App\Models\LearningObjective;
use App\Models\SemesterProgrammeItem;
use App\Models\TeachingModule;
use App\Services\TeachingModuleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * One module: its plan, the objectives it teaches, and the scheduled slots it
 * is meant to serve.
 *
 * Everything freezes at "ready" except teacher_notes, so the screen keeps that
 * one field editable and greys the rest rather than hiding them.
 */
#[Layout('layouts.app')]
class ModuleShow extends Component
{
    public TeachingModule $teachingModule;

    public array $plan = [];

    public string $teacher_notes = '';

    public string $learning_objective_id = '';

    public string $semester_programme_item_id = '';

    public bool $showAiPanel = false;

    public string $ai_language = 'id';

    public string $teacherNotesForAi = '';

    public ?string $aiPlannedActivity = null;

    public ?string $aiTeachingStrategy = null;

    public ?string $aiResources = null;

    public ?string $aiDifferentiation = null;

    public ?string $aiPlannedAssessment = null;

    public ?int $aiGenerationId = null;

    public ?string $aiError = null;

    public function mount(TeachingModule $teachingModule): void
    {
        $this->authorize('view', $teachingModule);
        $this->teachingModule = $teachingModule;
        $this->plan = $teachingModule->only(TeachingModule::PLAN_FIELDS);
        $this->teacher_notes = $teachingModule->teacher_notes ?? '';
    }

    public function savePlan(TeachingModuleService $modules): void
    {
        $this->authorize('update', $this->teachingModule);

        $this->validate([
            'plan.title' => ['required', 'string', 'max:200'],
            'plan.topic' => ['nullable', 'string', 'max:200'],
            'plan.planned_activity' => ['required', 'string'],
            'plan.teaching_strategy' => ['nullable', 'string'],
            'plan.resources' => ['nullable', 'string'],
            'plan.differentiation' => ['nullable', 'string'],
            'plan.planned_assessment' => ['nullable', 'string'],
        ]);

        $modules->update($this->teachingModule, $this->plan);

        $this->teachingModule->refresh();
    }

    // ------------------------------------------------------------------ AI
    //
    // Generate never touches $this->teachingModule or calls any
    // TeachingModuleService method -- it only ever sets the transient
    // aiPlannedActivity/aiTeachingStrategy/aiResources/aiDifferentiation/
    // aiPlannedAssessment properties. Only the applyX()/applyAll() methods
    // copy text into the (still unsaved) $this->plan array; savePlan() above
    // remains the only path that writes to the database, completely unaware
    // AI was involved.

    private const AI_SUGGESTION_STATE = [
        'aiPlannedActivity', 'aiTeachingStrategy', 'aiResources',
        'aiDifferentiation', 'aiPlannedAssessment', 'aiGenerationId', 'aiError',
    ];

    public function toggleAiPanel(): void
    {
        $this->authorize('update', $this->teachingModule);
        $this->showAiPanel = ! $this->showAiPanel;
        $this->reset(self::AI_SUGGESTION_STATE);
    }

    public function generateAiSuggestion(TeachingModuleAssistant $assistant): void
    {
        $this->authorize('update', $this->teachingModule);
        $this->reset(self::AI_SUGGESTION_STATE);

        try {
            $result = $assistant->suggest(
                Auth::user(),
                $this->teachingModule,
                $this->ai_language,
                $this->teacherNotesForAi,
            );
        } catch (AuthorizationException $e) {
            $this->aiError = $e->getMessage();

            return;
        }

        if ($result->isUsable()) {
            $this->aiPlannedActivity = $result->suggestion->plannedActivity;
            $this->aiTeachingStrategy = $result->suggestion->teachingStrategy;
            $this->aiResources = $result->suggestion->resources;
            $this->aiDifferentiation = $result->suggestion->differentiation;
            $this->aiPlannedAssessment = $result->suggestion->plannedAssessment;
            $this->aiGenerationId = $result->generationId;

            return;
        }

        $this->aiError = match ($result->status) {
            'rate_limited' => 'You have reached the AI assistance limit for now. You can continue editing manually.',
            'unusable' => "AI assistance couldn't produce a usable suggestion this time. You can try again or continue editing manually.",
            default => 'AI assistance is temporarily unavailable. You can continue editing manually.',
        };
    }

    public function applyPlannedActivity(AiGenerationService $generations): void
    {
        $this->applyField('planned_activity', $this->aiPlannedActivity, $generations);
    }

    public function applyTeachingStrategy(AiGenerationService $generations): void
    {
        $this->applyField('teaching_strategy', $this->aiTeachingStrategy, $generations);
    }

    public function applyResources(AiGenerationService $generations): void
    {
        $this->applyField('resources', $this->aiResources, $generations);
    }

    public function applyDifferentiation(AiGenerationService $generations): void
    {
        $this->applyField('differentiation', $this->aiDifferentiation, $generations);
    }

    public function applyPlannedAssessment(AiGenerationService $generations): void
    {
        $this->applyField('planned_assessment', $this->aiPlannedAssessment, $generations);
    }

    public function applyAll(AiGenerationService $generations): void
    {
        $this->authorize('update', $this->teachingModule);

        $suggested = [
            'planned_activity' => $this->aiPlannedActivity,
            'teaching_strategy' => $this->aiTeachingStrategy,
            'resources' => $this->aiResources,
            'differentiation' => $this->aiDifferentiation,
            'planned_assessment' => $this->aiPlannedAssessment,
        ];

        $applied = false;

        foreach ($suggested as $field => $value) {
            if ($value !== null) {
                $this->plan[$field] = $value;
                $applied = true;
            }
        }

        if ($applied) {
            $this->markAiAccepted($generations);
        }
    }

    public function dismissAiSuggestion(): void
    {
        $this->reset(self::AI_SUGGESTION_STATE);
    }

    private function applyField(string $field, ?string $suggested, AiGenerationService $generations): void
    {
        $this->authorize('update', $this->teachingModule);

        if ($suggested === null) {
            return;
        }

        $this->plan[$field] = $suggested;
        $this->markAiAccepted($generations);
    }

    /**
     * accepted_at means only that at least one suggested field from this
     * generation was explicitly applied to unsaved form state -- not that
     * every field was used, and not that the Module itself was saved.
     */
    private function markAiAccepted(AiGenerationService $generations): void
    {
        if ($this->aiGenerationId !== null) {
            $generations->markAccepted($this->aiGenerationId);
        }
    }

    public function saveNotes(TeachingModuleService $modules): void
    {
        $this->authorize('update', $this->teachingModule);

        $modules->update($this->teachingModule, ['teacher_notes' => $this->teacher_notes]);

        $this->teachingModule->refresh();
    }

    public function addObjective(TeachingModuleService $modules): void
    {
        $this->authorize('update', $this->teachingModule);

        $this->validate(['learning_objective_id' => ['required', 'exists:learning_objectives,id']]);

        $modules->linkObjective($this->teachingModule, LearningObjective::findOrFail($this->learning_objective_id));

        $this->reset('learning_objective_id');
        $this->teachingModule->refresh();
    }

    public function removeObjective(int $objectiveId, TeachingModuleService $modules): void
    {
        $this->authorize('update', $this->teachingModule);

        $modules->unlinkObjective($this->teachingModule, LearningObjective::findOrFail($objectiveId));

        $this->teachingModule->refresh();
    }

    public function addSlot(TeachingModuleService $modules): void
    {
        $this->authorize('update', $this->teachingModule);

        $this->validate(['semester_programme_item_id' => ['required', 'exists:semester_programme_items,id']]);

        $modules->linkSlot($this->teachingModule, SemesterProgrammeItem::findOrFail($this->semester_programme_item_id));

        $this->reset('semester_programme_item_id');
        $this->teachingModule->refresh();
    }

    public function removeSlot(int $slotId, TeachingModuleService $modules): void
    {
        $this->authorize('update', $this->teachingModule);

        $modules->unlinkSlot($this->teachingModule, SemesterProgrammeItem::findOrFail($slotId));

        $this->teachingModule->refresh();
    }

    public function markReady(TeachingModuleService $modules): void
    {
        $this->authorize('transition', $this->teachingModule);

        $modules->markReady($this->teachingModule);

        $this->teachingModule->refresh();
    }

    public function returnToDraft(TeachingModuleService $modules): void
    {
        $this->authorize('transition', $this->teachingModule);

        $modules->returnToDraft($this->teachingModule);

        $this->teachingModule->refresh();
    }

    public function archive(TeachingModuleService $modules): void
    {
        $this->authorize('transition', $this->teachingModule);

        $modules->archive($this->teachingModule);

        $this->teachingModule->refresh();
    }

    public function render()
    {
        $module = $this->teachingModule;
        $linked = $module->objectiveLinks()->pluck('learning_objective_id');

        // Slots this module could still serve: the same roster, subject and
        // scope, through an annual programme that is not archived.
        $candidateSlots = SemesterProgrammeItem::query()
            ->whereHas('semesterProgramme', fn ($q) => $q->where('status', '!=', 'archived')
                ->whereHas('annualProgramme', fn ($a) => $a
                    ->where('subject_id', $module->subject_id)
                    ->where('curriculum_scope_id', $module->curriculum_scope_id)
                    ->when($module->isClassBacked(),
                        fn ($x) => $x->where('class_id', $module->class_id),
                        fn ($x) => $x->where('teaching_group_id', $module->teaching_group_id))))
            ->whereNotIn('id', $module->slotLinks()->pluck('semester_programme_item_id'))
            ->with('semesterProgramme.academicPeriod')
            ->get();

        return view('livewire.teaching.module-show', [
            'vocabulary' => $module->curriculumScope->curriculum->vocabulary(),
            'objectives' => $module->objectives(),
            'available' => $module->isDraft()
                ? LearningObjective::where('curriculum_scope_id', $module->curriculum_scope_id)
                    ->where('subject_id', $module->subject_id)
                    ->where('status', '!=', 'archived')
                    ->whereNotIn('id', $linked)
                    ->orderBy('reference_order')->get()
                : collect(),
            'slotLinks' => $module->slotLinks()->with('semesterProgrammeItem.semesterProgramme.academicPeriod')->get(),
            'candidateSlots' => $module->isDraft() ? $candidateSlots : collect(),
            'journalCount' => $module->journals()->count(),
            'canEdit' => Auth::user()->can('update', $module),
            'canTransition' => Auth::user()->can('transition', $module),
            // isDraft() is explicit here, not implied by canEdit -- TeachingModulePolicy::update()
            // legitimately allows editing a READY module's teacher_notes, but the five
            // AI-suggested fields are exactly what's frozen once ready. See
            // TeachingModuleAssistant's docblock.
            'canUseAi' => config('ai.enabled') && Auth::user()->can('ai.use') && Auth::user()->can('update', $module) && $module->isDraft(),
            'aiLanguages' => TeachingModuleAssistant::LANGUAGES,
        ]);
    }
}
