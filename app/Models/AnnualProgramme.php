<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Program Tahunan -- the annual operating plan for one roster and subject.
 *
 * Anchored to the ROSTER, never to a teaching assignment, so that a mid-year
 * handover leaves the plan exactly where it is. Sarah starts the year, Eka
 * continues it: same row, same id, same allocations. Who may edit follows the
 * current assignment; the plan does not move.
 *
 * Unlike the standards layer, an ACTIVE programme stays editable. A school
 * year genuinely shifts -- a lost week, a delayed topic -- and freezing the
 * plan would mean rebuilding the year every time January moved. What is frozen
 * is identity, not allocation, and every allocation change is audited.
 */
#[Fillable([
    'class_id', 'teaching_group_id', 'subject_id', 'academic_year_id',
    'curriculum_scope_id', 'learning_pathway_id', 'title', 'notes', 'status',
])]
class AnnualProgramme extends Model
{
    use Auditable;

    /** What this plan is FOR. None of it may move once the row exists. */
    private const IDENTITY = [
        'class_id', 'teaching_group_id', 'subject_id',
        'academic_year_id', 'curriculum_scope_id', 'learning_pathway_id',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $programme) {
            foreach (self::IDENTITY as $column) {
                if ($programme->isDirty($column)) {
                    throw new LogicException(
                        "An annual programme's roster, subject, year and pathway are fixed at creation ({$column}). ".
                        'Create a new programme instead.'
                    );
                }
            }

            if ($programme->getOriginal('status') === 'archived') {
                throw new LogicException('An archived annual programme is read-only.');
            }
        });

        static::deleting(function (self $programme) {
            if ($programme->status !== 'draft') {
                throw new LogicException('Only an unused draft annual programme can be deleted. Archive it instead.');
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

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function curriculumScope(): BelongsTo
    {
        return $this->belongsTo(CurriculumScope::class);
    }

    public function learningPathway(): BelongsTo
    {
        return $this->belongsTo(LearningPathway::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AnnualProgrammeItem::class);
    }

    public function semesterProgrammes(): HasMany
    {
        return $this->hasMany(SemesterProgramme::class);
    }

    public function isClassBacked(): bool
    {
        return $this->class_id !== null;
    }

    /** "Year 5A" or "Green A" -- the same shape ClassSubject uses. */
    public function rosterName(): string
    {
        return $this->isClassBacked()
            ? ($this->schoolClass?->name ?? 'Unknown class')
            : ($this->teachingGroup?->name ?? 'Unknown group');
    }

    public function rosterLabel(): string
    {
        return $this->isClassBacked() ? 'Class' : 'Teaching Group';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    /** Editable while it is not archived -- an operating plan, not a standard. */
    public function isEditable(): bool
    {
        return ! $this->isArchived();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
