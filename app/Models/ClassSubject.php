<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

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

    // ------------------------------------------------ unified roster accessors
    //
    // Everything downstream of a teaching assignment -- assessments today,
    // planning records later -- goes through these rather than branching on
    // class_id. The point is that `Year 5A + Mathematics` and
    // `Green A + English` expose one interface.

    /**
     * The academic year this assignment belongs to, from whichever roster
     * source backs it.
     *
     * Never falls back to the year flagged current: an assignment whose
     * roster source has no year is malformed, and quietly substituting
     * today's year would attach assessments to the wrong reporting periods.
     */
    public function academicYear(): AcademicYear
    {
        $year = $this->isClassBacked()
            ? $this->schoolClass?->academicYear
            : $this->teachingGroup?->academicYear;

        if (! $year) {
            throw new RuntimeException(
                "Teaching assignment #{$this->id} has no resolvable academic year; its roster source is missing or malformed."
            );
        }

        return $year;
    }

    /**
     * What this assignment is taught to -- "Year 5A" or "Green A".
     */
    public function displayName(): string
    {
        return $this->isClassBacked()
            ? ($this->schoolClass?->name ?? 'Unknown class')
            : ($this->teachingGroup?->name ?? 'Unknown group');
    }

    /**
     * What KIND of thing that is, so the UI can say "Teaching Group" rather
     * than calling Green A a class.
     */
    public function rosterLabel(): string
    {
        return $this->isClassBacked() ? 'Class' : 'Teaching Group';
    }

    /**
     * Where to go to see the roster itself.
     */
    public function rosterUrl(): string
    {
        return $this->isClassBacked()
            ? route('classes.show', $this->class_id)
            : route('teaching-groups.show', $this->teaching_group_id);
    }

    /**
     * The students on this assignment's roster as at $on.
     *
     * DATE SEMANTICS, which differ by source because the underlying data does:
     *
     *  - Teaching group: genuinely date-aware. teaching_group_student is
     *    effective-dated, so membership counts when started_on <= $on and the
     *    membership had not yet ended on $on. A student who left in December is
     *    on the November roster and off the January one.
     *
     *  - Administrative class: NOT date-aware, because class_student is not
     *    effective-dated -- it carries a status enum and no end date (recorded
     *    as technical debt in Step 2a-ii). The roster is therefore the current
     *    `active` membership, exactly as before this step. $on is accepted and
     *    ignored rather than faked: inventing a date filter from enrolled_at
     *    alone would silently change every existing class assessment.
     */
    public function rosterOn(Carbon $on): Collection
    {
        if ($this->isClassBacked()) {
            return $this->schoolClass
                ? $this->schoolClass->students()->wherePivot('status', 'active')->get()
                : collect();
        }

        if (! $this->teachingGroup) {
            return collect();
        }

        $studentIds = $this->teachingGroup->memberships()
            ->whereDate('started_on', '<=', $on)
            ->where(fn ($q) => $q->whereNull('ended_on')->orWhereDate('ended_on', '>=', $on))
            ->pluck('student_id');

        return Student::whereIn('id', $studentIds)->get();
    }

    /**
     * Roster ids as at $on. Deliberately date-taking rather than a bare
     * "current roster" helper -- an ambiguous accessor is exactly how a
     * historical assessment ends up silently losing students.
     */
    public function rosterStudentIdsOn(Carbon $on): Collection
    {
        return $this->rosterOn($on)->pluck('id');
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
