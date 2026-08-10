<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A TEACHING ASSIGNMENT: one subject, taught to one roster, by one staff
 * member, for a bounded period of time.
 *
 * The roster is EITHER an administrative class OR a teaching group -- exactly
 * one, enforced by a CHECK constraint. The table and model keep the older
 * `class_subject`/`ClassSubject` names because renaming them would churn every
 * reference (`assessments.class_subject_id` above all) for no behavioural gain.
 *
 * Effective-dated on purpose. Reassigning a subject to a different teacher
 * CLOSES the current row (`ended_on`) and OPENS a new one -- it never mutates
 * `staff_id` in place, because records that point here (assessments today,
 * Phase 5 planning records later) must keep identifying the teacher who was
 * actually in force when they were created.
 */
#[Fillable(['class_id', 'teaching_group_id', 'subject_id', 'staff_id', 'started_on', 'ended_on'])]
class ClassSubject extends Model
{
    use Auditable;

    protected $table = 'class_subject';

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'ended_on' => 'date',
        ];
    }

    /**
     * The roster source is immutable once the row exists.
     *
     * Repointing an assignment from one class to another, from a class to a
     * group, or from Green A to Blue A would rewrite what every assessment
     * hanging off it was actually about. The operational answer is to end this
     * assignment and open the correct one -- same reasoning as never mutating
     * staff_id. A LogicException rather than a validation error: no user input
     * should ever reach here, so this is a programming mistake, not a form
     * mistake.
     */
    protected static function booted(): void
    {
        static::updating(function (self $assignment) {
            foreach (['class_id', 'teaching_group_id'] as $column) {
                if ($assignment->isDirty($column)) {
                    throw new \LogicException(
                        "A teaching assignment's roster source cannot be changed ({$column}). ".
                        'End this assignment and create a new one instead.'
                    );
                }
            }
        });
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function teachingGroup(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class);
    }

    public function isClassBacked(): bool
    {
        return $this->class_id !== null;
    }

    public function isTeachingGroupBacked(): bool
    {
        return $this->teaching_group_id !== null;
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /**
     * The assignment currently in force (the only one writable by its teacher).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_on');
    }

    /**
     * Superseded assignments. Retained for historical attribution; read-only
     * to teachers.
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereNotNull('ended_on');
    }

    public function isActive(): bool
    {
        return $this->ended_on === null;
    }

    /**
     * Assignments backed by an administrative class. Consumers that still
     * assume a class -- assessment creation and the report card, both
     * untouched in this step -- scope through this so a group-backed
     * assignment never reaches code that is not ready for it.
     */
    public function scopeClassBacked(Builder $query): Builder
    {
        return $query->whereNotNull('class_id');
    }

    public function scopeTeachingGroupBacked(Builder $query): Builder
    {
        return $query->whereNotNull('teaching_group_id');
    }
}
