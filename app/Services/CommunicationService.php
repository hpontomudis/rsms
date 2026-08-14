<?php

namespace App\Services;

use App\Communications\AudienceCandidate;
use App\Communications\AudienceResolver;
use App\Communications\TeacherAudienceScope;
use App\Models\Communication;
use App\Models\CommunicationAudienceRule;
use App\Models\CommunicationAudienceRuleGuardian;
use App\Models\CommunicationAudienceRuleStaff;
use App\Models\CommunicationAudienceRuleStudent;
use App\Models\CommunicationAudienceRuleUser;
use App\Models\CommunicationRecipient;
use App\Models\Concerns\ResolvesUnambiguousUser;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\StaffCategory;
use App\Models\Student;
use App\Models\TeachingGroup;
use App\Models\User;
use App\Notifications\NewCommunicationPublished;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Authoring, targeting and publishing a Communication.
 *
 * TEACHER SCOPE IS VALIDATED HERE TOO, not only in CommunicationPolicy --
 * once when a rule is added to the draft, and again, fresh, at publish
 * (membership can change in between). Neither layer trusts the other alone,
 * per the V8A architecture review.
 *
 * PUBLISHING != EXTERNAL DELIVERY. publish() only ever writes canonical rows
 * and creates in-app Notifications; there is no external provider call to
 * fail, so the publish transaction either fully commits or fully rolls back
 * with nothing left half-done.
 */
class CommunicationService
{
    use ResolvesUnambiguousUser;

    private const TEACHER_ALLOWED_RULE_TYPES = [
        'school_class_students', 'school_class_guardians',
        'teaching_group_students', 'teaching_group_guardians',
        'selected_students', 'selected_guardians',
    ];

    public function __construct(
        private readonly AudienceResolver $resolver,
        private readonly TeacherAudienceScope $teacherScope,
    ) {}

    public function createDraft(User $author, array $attributes): Communication
    {
        $validated = $this->validateContent($attributes);

        return Communication::create([
            'created_by_user_id' => $author->id,
            'display_sender' => $validated['display_sender'],
            'title' => $validated['title'],
            'body' => $validated['body'],
            'priority' => $validated['priority'],
            'expires_at' => $validated['expires_at'],
            'status' => 'draft',
        ]);
    }

    public function updateDraft(Communication $communication, array $attributes): Communication
    {
        $this->assertDraft($communication);

        $validated = $this->validateContent($attributes);
        $communication->update($validated);

        return $communication->refresh();
    }

    public function deleteDraft(Communication $communication): void
    {
        $this->assertDraft($communication);

        DB::transaction(function () use ($communication) {
            foreach ($communication->audienceRules()->get() as $rule) {
                $this->deleteRuleAndChildren($rule);
            }
            $communication->delete();
        });
    }

    /**
     * $attributes: rule_type (required), plus whichever of
     * staff_category_id / school_class_id / teaching_group_id / role_name /
     * ids (array, for the four selected_* types) applies.
     */
    public function addAudienceRule(Communication $communication, User $actor, array $attributes): CommunicationAudienceRule
    {
        $this->assertDraft($communication);

        $ruleType = $attributes['rule_type'] ?? null;

        if (! in_array($ruleType, CommunicationAudienceRule::RULE_TYPES, true)) {
            $this->fail('rule_type', "\"{$ruleType}\" is not a recognised audience rule type.");
        }

        $this->assertActorMayUseRuleType($actor, $ruleType);

        $columns = $this->extractRuleColumns($ruleType, $attributes);
        $ids = $this->extractSelectedIds($ruleType, $attributes);

        $this->assertTeacherScopeForRule($actor, $ruleType, $columns, $ids);

        return DB::transaction(function () use ($communication, $ruleType, $columns, $ids) {
            $rule = CommunicationAudienceRule::create([
                'communication_id' => $communication->id,
                'rule_type' => $ruleType,
                ...$columns,
            ]);

            $this->attachSelectedEntities($rule, $ruleType, $ids);

            return $rule->refresh();
        });
    }

    public function removeAudienceRule(CommunicationAudienceRule $rule): void
    {
        $this->assertDraft($rule->communication);

        DB::transaction(fn () => $this->deleteRuleAndChildren($rule));
    }

    /**
     * Live, re-resolved every call -- never cached, never trusted stale.
     * Publish re-resolves fresh again inside its own transaction; this exists
     * so the compose screen can show the same numbers before that.
     *
     * @return array{resolved: int, reachable: int, unreachable: int, byType: array<string,int>}
     */
    public function previewAudience(Communication $communication): array
    {
        return $this->summarize($this->resolveDedupedCandidates($communication));
    }

    /**
     * 1. lock draft, 2-3. re-validate every rule against the actor's CURRENT
     * scope, 4-5. resolve + dedupe candidates fresh, 6-7. write recipients +
     * resolve logins, 8. build the display summary, 9-11. freeze + publish,
     * 12. create in-app Notifications, 13. commit. All canonical DB state,
     * one transaction; there is no external delivery to happen after it.
     */
    public function publish(Communication $communication, User $actor): Communication
    {
        $this->assertDraft($communication);

        $rules = $communication->audienceRules()->get();

        if ($rules->isEmpty()) {
            $this->fail('status', 'Add at least one audience rule before publishing.');
        }

        foreach ($rules as $rule) {
            $this->assertTeacherScopeForRule(
                $actor,
                $rule->rule_type,
                $this->ruleColumnsFromModel($rule),
                $this->ruleSelectedIdsFromModel($rule),
            );
        }

        $candidates = $this->resolveDedupedCandidates($communication);

        if ($candidates->isEmpty()) {
            $this->fail('status', 'This audience currently resolves to nobody. Publishing would notify no one.');
        }

        return DB::transaction(function () use ($communication, $candidates) {
            $communication = Communication::whereKey($communication->id)->lockForUpdate()->firstOrFail();

            foreach ($candidates as $candidate) {
                $this->materializeRecipient($communication, $candidate);
            }

            $summary = $this->summarize($candidates);

            $communication->update([
                'audience_summary_snapshot' => $this->describeSummary($summary),
                'published_at' => now(),
                'status' => 'published',
            ]);

            foreach ($communication->recipients()->whereNotNull('resolved_user_id')->with('resolvedUser')->get() as $recipient) {
                $recipient->resolvedUser->notify(new NewCommunicationPublished($communication));
            }

            return $communication->refresh();
        });
    }

    public function archive(Communication $communication, User $actor): Communication
    {
        if (! $communication->isPublished()) {
            $this->fail('status', 'Only a published communication can be archived.');
        }

        if ($actor->hasRole('teacher')) {
            foreach ($communication->audienceRules()->get() as $rule) {
                $this->assertTeacherScopeForRule(
                    $actor,
                    $rule->rule_type,
                    $this->ruleColumnsFromModel($rule),
                    $this->ruleSelectedIdsFromModel($rule),
                );
            }
        }

        $communication->update(['status' => 'archived']);

        return $communication->refresh();
    }

    // ------------------------------------------------------------- content

    private function validateContent(array $attributes): array
    {
        $displaySender = trim((string) ($attributes['display_sender'] ?? ''));
        $title = trim((string) ($attributes['title'] ?? ''));
        $body = trim((string) ($attributes['body'] ?? ''));
        $priority = $attributes['priority'] ?? 'normal';

        if ($displaySender === '') {
            $this->fail('display_sender', 'Every communication needs a display sender.');
        }
        if ($title === '') {
            $this->fail('title', 'Every communication needs a title.');
        }
        if ($body === '') {
            $this->fail('body', 'Every communication needs a body.');
        }
        if (! in_array($priority, Communication::PRIORITIES, true)) {
            $this->fail('priority', "\"{$priority}\" is not a recognised priority.");
        }

        return [
            'display_sender' => $displaySender,
            'title' => $title,
            'body' => $body,
            'priority' => $priority,
            'expires_at' => $attributes['expires_at'] ?? null,
        ];
    }

    // ------------------------------------------------------- audience rules

    private function assertActorMayUseRuleType(User $actor, string $ruleType): void
    {
        if (! $actor->hasRole('teacher')) {
            return;
        }

        if (! in_array($ruleType, self::TEACHER_ALLOWED_RULE_TYPES, true)) {
            $this->fail('rule_type', "Teachers may not target \"{$ruleType}\" audiences -- only their own current classes, teaching groups, and the students/guardians within them.");
        }
    }

    private function extractRuleColumns(string $ruleType, array $attributes): array
    {
        return match ($ruleType) {
            'staff_category' => ['staff_category_id' => $this->requireId($attributes, 'staff_category_id', StaffCategory::class)],
            'role' => ['role_name' => $this->requireRoleName($attributes)],
            'school_class_students', 'school_class_guardians' => ['school_class_id' => $this->requireId($attributes, 'school_class_id', SchoolClass::class)],
            'teaching_group_students', 'teaching_group_guardians' => ['teaching_group_id' => $this->requireId($attributes, 'teaching_group_id', TeachingGroup::class)],
            default => [],
        };
    }

    private function extractSelectedIds(string $ruleType, array $attributes): array
    {
        if (! in_array($ruleType, ['selected_staff', 'selected_guardians', 'selected_students', 'selected_users'], true)) {
            return [];
        }

        $ids = array_values(array_unique(array_map('intval', $attributes['ids'] ?? [])));

        if (empty($ids)) {
            $this->fail('ids', 'Select at least one entity for this audience rule.');
        }

        return $ids;
    }

    private function requireId(array $attributes, string $field, string $modelClass): int
    {
        $id = $attributes[$field] ?? null;

        if (! $id || ! $modelClass::whereKey($id)->exists()) {
            $this->fail($field, "A valid {$field} is required for this rule type.");
        }

        return (int) $id;
    }

    private function requireRoleName(array $attributes): string
    {
        $role = trim((string) ($attributes['role_name'] ?? ''));

        if ($role === '') {
            $this->fail('role_name', 'A role name is required for this rule type.');
        }

        return $role;
    }

    /**
     * Teacher-only re-check: every concrete target this rule names must fall
     * within the teacher's CURRENT authorized scope. Non-teachers (principal,
     * super_admin) skip this entirely -- they are not scope-limited.
     */
    private function assertTeacherScopeForRule(User $actor, string $ruleType, array $columns, array $ids): void
    {
        if (! $actor->hasRole('teacher')) {
            return;
        }

        $staff = $actor->staff;

        if (! $staff) {
            $this->fail('rule_type', 'This account has no staff profile and cannot author communications.');
        }

        match ($ruleType) {
            'school_class_students', 'school_class_guardians' => $this->assertClassAuthorized($staff, $columns['school_class_id'] ?? null),
            'teaching_group_students', 'teaching_group_guardians' => $this->assertGroupAuthorized($staff, $columns['teaching_group_id'] ?? null),
            'selected_students' => $this->assertEachAuthorized($staff, $ids, fn ($id) => $this->teacherScope->canTargetStudent($staff, $id), 'student'),
            'selected_guardians' => $this->assertEachAuthorized($staff, $ids, fn ($id) => $this->teacherScope->canTargetGuardian($staff, $id), 'guardian'),
            default => null,
        };
    }

    private function assertClassAuthorized(Staff $staff, ?int $classId): void
    {
        if (! $classId || ! $this->teacherScope->canTargetClass($staff, $classId)) {
            $this->fail('school_class_id', 'You may only target a class you currently, actively teach.');
        }
    }

    private function assertGroupAuthorized(Staff $staff, ?int $groupId): void
    {
        if (! $groupId || ! $this->teacherScope->canTargetTeachingGroup($staff, $groupId)) {
            $this->fail('teaching_group_id', 'You may only target a teaching group you currently, actively teach.');
        }
    }

    private function assertEachAuthorized(Staff $staff, array $ids, \Closure $check, string $label): void
    {
        foreach ($ids as $id) {
            if (! $check($id)) {
                $this->fail('ids', "One of the selected {$label}s is outside your current teaching assignments.");
            }
        }
    }

    private function attachSelectedEntities(CommunicationAudienceRule $rule, string $ruleType, array $ids): void
    {
        foreach ($ids as $id) {
            match ($ruleType) {
                'selected_staff' => CommunicationAudienceRuleStaff::create(['communication_audience_rule_id' => $rule->id, 'staff_id' => $id]),
                'selected_guardians' => CommunicationAudienceRuleGuardian::create(['communication_audience_rule_id' => $rule->id, 'guardian_id' => $id]),
                'selected_students' => CommunicationAudienceRuleStudent::create(['communication_audience_rule_id' => $rule->id, 'student_id' => $id]),
                'selected_users' => CommunicationAudienceRuleUser::create(['communication_audience_rule_id' => $rule->id, 'user_id' => $id]),
                default => null,
            };
        }
    }

    private function deleteRuleAndChildren(CommunicationAudienceRule $rule): void
    {
        $rule->selectedStaff()->get()->each->delete();
        $rule->selectedGuardians()->get()->each->delete();
        $rule->selectedStudents()->get()->each->delete();
        $rule->selectedUsers()->get()->each->delete();
        $rule->delete();
    }

    private function ruleColumnsFromModel(CommunicationAudienceRule $rule): array
    {
        return array_filter([
            'staff_category_id' => $rule->staff_category_id,
            'school_class_id' => $rule->school_class_id,
            'teaching_group_id' => $rule->teaching_group_id,
        ], fn ($v) => $v !== null);
    }

    private function ruleSelectedIdsFromModel(CommunicationAudienceRule $rule): array
    {
        return match ($rule->rule_type) {
            'selected_students' => $rule->selectedStudents()->pluck('student_id')->all(),
            'selected_guardians' => $rule->selectedGuardians()->pluck('guardian_id')->all(),
            default => [],
        };
    }

    // ---------------------------------------------------------- resolution

    /** @return Collection<int, AudienceCandidate> deduplicated by (type, id) */
    private function resolveDedupedCandidates(Communication $communication): Collection
    {
        return $communication->audienceRules()->get()
            ->flatMap(fn (CommunicationAudienceRule $rule) => $this->resolver->resolve($rule))
            ->unique(fn (AudienceCandidate $c) => $c->key())
            ->values();
    }

    /** @param Collection<int, AudienceCandidate> $candidates */
    private function summarize(Collection $candidates): array
    {
        $byType = $candidates->countBy(fn (AudienceCandidate $c) => $c->type)->all();
        $reachable = $candidates->filter(fn (AudienceCandidate $c) => $this->resolveLogin($c) !== null)->count();

        return [
            'resolved' => $candidates->count(),
            'reachable' => $reachable,
            'unreachable' => $candidates->count() - $reachable,
            'byType' => $byType,
        ];
    }

    private function describeSummary(array $summary): string
    {
        $parts = [];
        foreach ($summary['byType'] as $type => $count) {
            $parts[] = "{$count} " . Str::plural($type, $count);
        }

        return implode(', ', $parts) . " ({$summary['reachable']} reachable in RSMS)";
    }

    private function materializeRecipient(Communication $communication, AudienceCandidate $candidate): void
    {
        [$name, $context, $resolvedUserId, $columns] = match ($candidate->type) {
            'staff' => $this->staffRecipientData($candidate->id),
            'guardian' => $this->guardianRecipientData($candidate->id),
            'student' => $this->studentRecipientData($candidate->id),
            'user' => $this->userRecipientData($candidate->id),
        };

        CommunicationRecipient::create([
            'communication_id' => $communication->id,
            ...$columns,
            'resolved_user_id' => $resolvedUserId,
            'recipient_name_snapshot' => $name,
            'recipient_context_snapshot' => $context,
        ]);
    }

    private function staffRecipientData(int $id): array
    {
        $staff = Staff::with('staffCategory')->findOrFail($id);

        return [
            $staff->fullName(),
            $staff->staffCategory ? "Staff — {$staff->staffCategory->name}" : 'Staff',
            $this->unambiguousUserId($staff),
            ['staff_id' => $staff->id],
        ];
    }

    /**
     * No child-specific context: a guardian may be included via more than one
     * matched student, and naming just one deterministically would be
     * misleading. "Guardian" is the honest, ambiguity-free label.
     */
    private function guardianRecipientData(int $id): array
    {
        $guardian = Guardian::findOrFail($id);

        return [
            $guardian->fullName(),
            'Guardian',
            $this->unambiguousUserId($guardian),
            ['guardian_id' => $guardian->id],
        ];
    }

    private function studentRecipientData(int $id): array
    {
        $student = Student::findOrFail($id);

        return [
            $student->fullName(),
            'Student',
            $this->unambiguousUserId($student),
            ['student_id' => $student->id],
        ];
    }

    private function userRecipientData(int $id): array
    {
        $user = User::findOrFail($id);

        return [
            $user->name,
            null,
            $user->id,
            ['direct_user_id' => $user->id],
        ];
    }

    private function resolveLogin(AudienceCandidate $candidate): ?int
    {
        return match ($candidate->type) {
            'staff' => $this->unambiguousUserId(Staff::findOrFail($candidate->id)),
            'guardian' => $this->unambiguousUserId(Guardian::findOrFail($candidate->id)),
            'student' => $this->unambiguousUserId(Student::findOrFail($candidate->id)),
            'user' => $candidate->id,
        };
    }

    // ------------------------------------------------------------- guards

    private function assertDraft(Communication $communication): void
    {
        if (! $communication->isDraft()) {
            $this->fail('status', 'This communication is no longer a draft.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
