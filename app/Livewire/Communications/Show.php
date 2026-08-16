<?php

namespace App\Livewire\Communications;

use App\Ai\AiGenerationService;
use App\Ai\CommunicationAssistant;
use App\Communications\TeacherAudienceScope;
use App\Models\Communication;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\StaffCategory;
use App\Models\Student;
use App\Models\TeachingGroup;
use App\Models\User;
use App\Services\CommunicationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * Draft editor and published/archived read view in one component, branching
 * on status -- same shape as PerformanceEvaluations\Show. Opening a
 * published/archived communication that resolves to the viewer's own
 * CommunicationRecipient row sets read_at, exactly once, in mount().
 */
#[Layout('layouts.app')]
class Show extends Component
{
    public Communication $communication;

    public string $display_sender = '';

    public string $title = '';

    public string $body = '';

    public string $priority = '';

    public string $expires_at = '';

    public bool $showAddRule = false;

    public string $rule_type = '';

    public string $staff_category_id = '';

    public string $role_name = '';

    public string $school_class_id = '';

    public string $teaching_group_id = '';

    /** @var array<int, int> */
    public array $selected_ids = [];

    public bool $showAiPanel = false;

    public string $ai_mode = 'clearer';

    public string $ai_language = 'id';

    public ?string $aiSuggestion = null;

    public ?int $aiGenerationId = null;

    public ?string $aiError = null;

    public function mount(Communication $communication): void
    {
        $this->authorize('view', $communication);
        $this->communication = $communication;

        if ($communication->isDraft()) {
            $this->display_sender = $communication->display_sender;
            $this->title = $communication->title;
            $this->body = $communication->body;
            $this->priority = $communication->priority;
            $this->expires_at = $communication->expires_at?->toDateString() ?? '';

            return;
        }

        $recipient = $communication->recipients()->where('resolved_user_id', Auth::id())->first();

        if ($recipient && ! $recipient->isRead()) {
            $recipient->update(['read_at' => now()]);
            $this->communication->refresh();
        }
    }

    public function saveContent(CommunicationService $communications): void
    {
        $this->authorize('update', $this->communication);

        $validated = $this->validate([
            'display_sender' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'priority' => ['required', 'in:normal,important,urgent'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $communications->updateDraft($this->communication, [
            ...$validated,
            'expires_at' => $validated['expires_at'] !== '' ? $validated['expires_at'] : null,
        ]);

        $this->communication->refresh();
    }

    // ------------------------------------------------------------------ AI
    //
    // Generate never touches $this->communication or calls any
    // CommunicationService method -- it only ever sets the transient
    // aiSuggestion property. Only applyAiSuggestion() copies text into the
    // (still unsaved) $this->body; saveContent() above remains the only
    // path that writes to the database, completely unaware AI was involved.

    public function toggleAiPanel(): void
    {
        $this->authorize('update', $this->communication);
        $this->showAiPanel = ! $this->showAiPanel;
        $this->reset(['aiSuggestion', 'aiGenerationId', 'aiError']);
    }

    public function generateAiSuggestion(CommunicationAssistant $assistant): void
    {
        $this->authorize('update', $this->communication);
        $this->reset(['aiSuggestion', 'aiGenerationId', 'aiError']);

        try {
            $result = $assistant->suggest(
                Auth::user(),
                $this->communication,
                $this->ai_mode,
                $this->ai_language,
                $this->title,
                $this->body,
            );
        } catch (AuthorizationException) {
            $this->aiError = 'AI assistance is not available for this communication.';

            return;
        }

        if ($result->isSuccess()) {
            $this->aiSuggestion = $result->text;
            $this->aiGenerationId = $result->generationId;

            return;
        }

        $this->aiError = $result->status === 'rate_limited'
            ? 'You have reached the AI assistance limit for now. You can continue editing manually.'
            : 'AI assistance is temporarily unavailable. You can continue editing manually.';
    }

    public function applyAiSuggestion(AiGenerationService $generations): void
    {
        $this->authorize('update', $this->communication);

        if ($this->aiSuggestion === null) {
            return;
        }

        $this->body = $this->aiSuggestion;

        if ($this->aiGenerationId !== null) {
            $generations->markAccepted($this->aiGenerationId);
        }

        $this->reset(['aiSuggestion', 'aiGenerationId', 'showAiPanel']);
    }

    public function dismissAiSuggestion(): void
    {
        $this->reset(['aiSuggestion', 'aiGenerationId', 'aiError']);
    }

    public function startAddingRule(): void
    {
        $this->authorize('manageAudience', $this->communication);
        $this->reset(['rule_type', 'staff_category_id', 'role_name', 'school_class_id', 'teaching_group_id', 'selected_ids']);
        $this->showAddRule = true;
    }

    public function addRule(CommunicationService $communications): void
    {
        $this->authorize('manageAudience', $this->communication);

        $attributes = ['rule_type' => $this->rule_type];

        match ($this->rule_type) {
            'staff_category' => $attributes['staff_category_id'] = $this->staff_category_id,
            'role' => $attributes['role_name'] = $this->role_name,
            'school_class_students', 'school_class_guardians' => $attributes['school_class_id'] = $this->school_class_id,
            'teaching_group_students', 'teaching_group_guardians' => $attributes['teaching_group_id'] = $this->teaching_group_id,
            'selected_staff', 'selected_guardians', 'selected_students', 'selected_users' => $attributes['ids'] = $this->selected_ids,
            default => null,
        };

        $communications->addAudienceRule($this->communication, Auth::user(), $attributes);

        $this->reset(['showAddRule', 'rule_type', 'staff_category_id', 'role_name', 'school_class_id', 'teaching_group_id', 'selected_ids']);
        $this->communication->refresh();
    }

    public function removeRule(int $ruleId, CommunicationService $communications): void
    {
        $this->authorize('manageAudience', $this->communication);

        $rule = $this->communication->audienceRules()->findOrFail($ruleId);
        $communications->removeAudienceRule($rule);

        $this->communication->refresh();
    }

    public function publish(CommunicationService $communications)
    {
        $this->authorize('publish', $this->communication);

        $communications->publish($this->communication, Auth::user());

        $this->communication->refresh();
    }

    public function deleteDraft(CommunicationService $communications)
    {
        $this->authorize('delete', $this->communication);

        $communications->deleteDraft($this->communication);

        return $this->redirect(route('communications.index'), navigate: true);
    }

    public function archive(CommunicationService $communications): void
    {
        $this->authorize('archive', $this->communication);

        $communications->archive($this->communication, Auth::user());

        $this->communication->refresh();
    }

    public function render(CommunicationService $communications)
    {
        $user = Auth::user();
        $isTeacher = $user->hasRole('teacher');
        $teacherStaff = $isTeacher ? $user->staff : null;
        $scope = app(TeacherAudienceScope::class);

        $availableClasses = $isTeacher
            ? ($teacherStaff ? SchoolClass::whereIn('id', $scope->authorizedClassIds($teacherStaff))->orderBy('name')->get() : collect())
            : SchoolClass::orderBy('name')->get();

        $availableGroups = $isTeacher
            ? ($teacherStaff ? TeachingGroup::whereIn('id', $scope->authorizedTeachingGroupIds($teacherStaff))->orderBy('name')->get() : collect())
            : TeachingGroup::where('status', 'active')->orderBy('name')->get();

        $availableStudents = $isTeacher
            ? ($teacherStaff ? Student::whereIn('id', $scope->authorizedStudentIds($teacherStaff))->orderBy('first_name')->get() : collect())
            : Student::where('status', 'active')->orderBy('first_name')->get();

        $availableGuardians = $isTeacher
            ? ($teacherStaff ? Guardian::whereIn('id', $scope->authorizedGuardianIds($teacherStaff))->orderBy('first_name')->get() : collect())
            : Guardian::orderBy('first_name')->get();

        $recipientStats = $this->communication->isDraft() ? null : [
            'resolved' => $this->communication->recipients()->count(),
            'reachable' => $this->communication->recipients()->whereNotNull('resolved_user_id')->count(),
            'read' => $this->communication->recipients()->whereNotNull('read_at')->count(),
        ];

        return view('livewire.communications.show', [
            'preview' => $this->communication->isDraft() ? $communications->previewAudience($this->communication) : null,
            'recipientStats' => $recipientStats,
            'staffCategories' => StaffCategory::orderBy('name')->get(),
            'roles' => Role::orderBy('name')->pluck('name'),
            'availableClasses' => $availableClasses,
            'availableGroups' => $availableGroups,
            'availableStaff' => $isTeacher ? collect() : Staff::where('status', 'active')->orderBy('first_name')->get(),
            'availableStudents' => $availableStudents,
            'availableGuardians' => $availableGuardians,
            'availableUsers' => $isTeacher ? collect() : User::orderBy('name')->get(),
            'canManageAudience' => $user->can('manageAudience', $this->communication),
            'canUseAi' => config('ai.enabled') && $user->can('ai.use') && $user->can('update', $this->communication),
            'aiModes' => CommunicationAssistant::MODES,
            'aiLanguages' => CommunicationAssistant::LANGUAGES,
            'canPublish' => $user->can('publish', $this->communication),
            'canArchive' => $user->can('archive', $this->communication),
            'canDelete' => $user->can('delete', $this->communication),
            'isTeacher' => $isTeacher,
        ]);
    }
}
