<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Tujuan Pembelajaran on the national curriculum; Learning Objective on a
 * Rahai English one. One table, wording derived from the curriculum.
 *
 * Unlike a learning outcome, an objective carries its OWN lifecycle. An
 * outcome is published curriculum content and simply inherits the curriculum's
 * state; an objective is the school's own formulation, written and revised
 * while a curriculum is in force, so a draft successor has to be able to sit
 * beside the active TP it will replace.
 */
#[Fillable([
    'curriculum_scope_id', 'subject_id', 'code', 'title',
    'objective_text', 'reference_order', 'status',
])]
class LearningObjective extends Model
{
    use Auditable;

    /** The anchor is identity: it says which standard this objective serves. */
    private const ANCHOR = ['curriculum_scope_id', 'subject_id'];

    protected static function booted(): void
    {
        static::creating(function (self $objective) {
            $objective->assertCurriculumNotArchived('created');
        });

        static::updating(function (self $objective) {
            $objective->assertCurriculumNotArchived('changed');

            // The anchor never moves -- not even on a draft. Moving a Phase C
            // objective to Phase D would silently re-point what it serves, and
            // the composite keys that guarantee its CP links share that anchor
            // would have to be unpicked. An unused draft in the wrong place is
            // deleted and rewritten.
            foreach (self::ANCHOR as $column) {
                if ($objective->isDirty($column)) {
                    throw new LogicException(
                        "A learning objective's curriculum scope and subject are fixed at creation ({$column}). ".
                        'Delete the unused draft and create it under the correct scope.'
                    );
                }
            }

            $wasDraft = $objective->getOriginal('status') === 'draft';
            $wasArchived = $objective->getOriginal('status') === 'archived';

            if ($wasArchived) {
                throw new LogicException('An archived learning objective is read-only.');
            }

            // Active content is frozen; only the status itself may still move,
            // which is how it gets archived. NOTE: isDirty([]) means "is
            // anything dirty" and returns true, so the diff is compared
            // directly rather than passed back into isDirty().
            $contentChanges = array_diff(array_keys($objective->getDirty()), ['status', 'updated_at']);

            if (! $wasDraft && $contentChanges !== []) {
                throw new LogicException(
                    'An active learning objective cannot be edited. Prepare a replacement draft and archive this one.'
                );
            }
        });

        static::deleting(function (self $objective) {
            $objective->assertCurriculumNotArchived('removed');

            if ($objective->status !== 'draft') {
                throw new LogicException(
                    'Only an unused draft learning objective can be deleted. Archive it instead.'
                );
            }
        });
    }

    private function assertCurriculumNotArchived(string $verb): void
    {
        $curriculum = $this->curriculumScope?->curriculum
            ?? CurriculumScope::with('curriculum')->find($this->curriculum_scope_id)?->curriculum;

        if ($curriculum && $curriculum->isArchived()) {
            throw new LogicException(
                "Learning objectives cannot be {$verb} under an archived curriculum version ".
                "({$curriculum->code} v{$curriculum->version}). Plan against the current version instead."
            );
        }
    }

    public function curriculumScope(): BelongsTo
    {
        return $this->belongsTo(CurriculumScope::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * CP links. Written through this relation -- never attach()/detach()/
     * sync(), which fire no model events and would leave the audit trail
     * silently empty.
     */
    public function outcomeLinks(): HasMany
    {
        return $this->hasMany(LearningObjectiveLearningOutcome::class);
    }

    /** READ-ONLY convenience. Never write through this. */
    public function learningOutcomes(): BelongsToMany
    {
        return $this->belongsToMany(
            LearningOutcome::class,
            'learning_objective_learning_outcome',
            'learning_objective_id',
            'learning_outcome_id',
        )->withTimestamps();
    }

    /** Which pathways sequence this objective. */
    public function pathwayItems(): HasMany
    {
        return $this->hasMany(LearningPathwayItem::class);
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
