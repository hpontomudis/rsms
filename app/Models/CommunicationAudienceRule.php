<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * One row of DRAFT-time targeting intent. Editable only while the parent
 * Communication is draft -- once published, communication_recipients (the
 * materialized, deduplicated result) is the historical truth, and these rows
 * stop mattering for anything but showing how that audience was originally
 * assembled.
 *
 * Which of staff_category_id / school_class_id / teaching_group_id /
 * role_name is populated for a given rule_type is validated by
 * CommunicationService, the same way PerformanceEvaluationItemService
 * enforces "exactly one field per indicator type" -- see its docblock.
 */
#[Fillable(['communication_id', 'rule_type', 'staff_category_id', 'school_class_id', 'teaching_group_id', 'role_name'])]
class CommunicationAudienceRule extends Model
{
    use Auditable;

    public const RULE_TYPES = [
        'everyone', 'all_staff', 'staff_category', 'role',
        'school_class_students', 'school_class_guardians',
        'teaching_group_students', 'teaching_group_guardians',
        'selected_staff', 'selected_guardians', 'selected_students', 'selected_users',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $rule) {
            if ($rule->communication && ! $rule->communication->isDraft()) {
                throw new LogicException(
                    'Audience rules can only be changed while the communication is still draft.'
                );
            }
        });

        static::deleting(function (self $rule) {
            if ($rule->communication && ! $rule->communication->isDraft()) {
                throw new LogicException(
                    'Audience rules can only be removed while the communication is still draft.'
                );
            }
        });
    }

    public function communication(): BelongsTo
    {
        return $this->belongsTo(Communication::class);
    }

    public function staffCategory(): BelongsTo
    {
        return $this->belongsTo(StaffCategory::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function teachingGroup(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class);
    }

    public function selectedStaff(): HasMany
    {
        return $this->hasMany(CommunicationAudienceRuleStaff::class, 'communication_audience_rule_id');
    }

    public function selectedGuardians(): HasMany
    {
        return $this->hasMany(CommunicationAudienceRuleGuardian::class, 'communication_audience_rule_id');
    }

    public function selectedStudents(): HasMany
    {
        return $this->hasMany(CommunicationAudienceRuleStudent::class, 'communication_audience_rule_id');
    }

    public function selectedUsers(): HasMany
    {
        return $this->hasMany(CommunicationAudienceRuleUser::class, 'communication_audience_rule_id');
    }

    /** Human-readable summary for the draft-editor's rule list. */
    public function displayLabel(): string
    {
        return match ($this->rule_type) {
            'everyone' => 'Everyone',
            'all_staff' => 'All staff',
            'staff_category' => 'Staff category: '.($this->staffCategory?->name ?? 'unknown'),
            'role' => 'Role: '.$this->role_name,
            'school_class_students' => 'Students of '.($this->schoolClass?->name ?? 'unknown class'),
            'school_class_guardians' => 'Guardians of '.($this->schoolClass?->name ?? 'unknown class'),
            'teaching_group_students' => 'Students of '.($this->teachingGroup?->name ?? 'unknown group'),
            'teaching_group_guardians' => 'Guardians of '.($this->teachingGroup?->name ?? 'unknown group'),
            'selected_staff' => 'Selected staff ('.$this->selectedStaff()->count().')',
            'selected_guardians' => 'Selected guardians ('.$this->selectedGuardians()->count().')',
            'selected_students' => 'Selected students ('.$this->selectedStudents()->count().')',
            'selected_users' => 'Selected users ('.$this->selectedUsers()->count().')',
            default => $this->rule_type,
        };
    }
}
