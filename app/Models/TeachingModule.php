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
 * Modul Ajar / Teaching Module -- HOW a set of objectives will be taught.
 *
 * Anchored to a teaching assignment, unlike Prota and Prosem, which are
 * anchored to the roster. That difference is the whole design: a plan for the
 * year belongs to the class and survives a handover; instructional design
 * belongs to the teacher who wrote it and does not change hands. Sarah's
 * modules stay Sarah's; Eka reads them and writes her own.
 *
 * The plan freezes at `ready` -- that is what "ready to teach" means -- except
 * teacher_notes, which is margin annotation rather than the plan itself.
 */
#[Fillable([
    'class_subject_id', 'class_id', 'teaching_group_id', 'subject_id', 'curriculum_scope_id',
    'title', 'topic', 'planned_activity', 'teaching_strategy', 'resources',
    'differentiation', 'planned_assessment', 'teacher_notes', 'status',
])]
class TeachingModule extends Model
{
    use Auditable;

    /** What this module is FOR. None of it may move once the row exists. */
    private const IDENTITY = ['class_subject_id', 'class_id', 'teaching_group_id', 'subject_id'];

    /** The plan of record. Frozen the moment the module is ready to teach. */
    public const PLAN_FIELDS = [
        'title', 'topic', 'planned_activity', 'teaching_strategy',
        'resources', 'differentiation', 'planned_assessment',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $module) {
            foreach (self::IDENTITY as $column) {
                if ($module->isDirty($column)) {
                    throw new LogicException(
                        "A teaching module's assignment, roster and subject are fixed at creation ({$column}). ".
                        'Create a new module instead.'
                    );
                }
            }

            if ($module->getOriginal('status') === 'archived') {
                throw new LogicException('An archived teaching module is read-only.');
            }
        });

        static::deleting(function (self $module) {
            if ($module->status !== 'draft') {
                throw new LogicException('Only an unused draft teaching module can be deleted. Archive it instead.');
            }
        });
    }

    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class);
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

    public function curriculumScope(): BelongsTo
    {
        return $this->belongsTo(CurriculumScope::class);
    }

    public function objectiveLinks(): HasMany
    {
        return $this->hasMany(TeachingModuleLearningObjective::class);
    }

    public function slotLinks(): HasMany
    {
        return $this->hasMany(TeachingModuleSemesterProgrammeItem::class);
    }

    public function journals(): HasMany
    {
        return $this->hasMany(DailyJournal::class);
    }

    /** The objectives themselves, in library order. */
    public function objectives()
    {
        return LearningObjective::whereIn('id', $this->objectiveLinks()->pluck('learning_objective_id'))
            ->orderBy('reference_order')->get();
    }

    public function isClassBacked(): bool
    {
        return $this->class_id !== null;
    }

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

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    /** Only a draft may have its plan or its links changed. */
    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    /** A journal may cite a module that is ready or archived -- never a draft. */
    public function isCitable(): bool
    {
        return $this->isReady() || $this->isArchived();
    }

    public function scopeReady(Builder $query): Builder
    {
        return $query->where('status', 'ready');
    }

    public function scopeCitable(Builder $query): Builder
    {
        return $query->whereIn('status', ['ready', 'archived']);
    }
}
