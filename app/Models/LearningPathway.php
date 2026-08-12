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
 * An ordered route through one curriculum scope and subject: Alur Tujuan
 * Pembelajaran nationally, Learning Path on a Rahai English curriculum.
 *
 * Several may be ACTIVE at once for the same anchor -- they are approved
 * alternative routes, not competing versions -- so the one-active rule that
 * governs learning objectives deliberately does NOT apply here.
 */
#[Fillable(['curriculum_scope_id', 'subject_id', 'code', 'title', 'description', 'status'])]
class LearningPathway extends Model
{
    use Auditable;

    private const ANCHOR = ['curriculum_scope_id', 'subject_id'];

    protected static function booted(): void
    {
        static::creating(fn (self $pathway) => $pathway->assertCurriculumNotArchived('created'));

        static::updating(function (self $pathway) {
            $pathway->assertCurriculumNotArchived('changed');

            foreach (self::ANCHOR as $column) {
                if ($pathway->isDirty($column)) {
                    throw new LogicException(
                        "A learning pathway's curriculum scope and subject are fixed at creation ({$column}). ".
                        'Delete the unused draft and create it under the correct scope.'
                    );
                }
            }

            if ($pathway->getOriginal('status') === 'archived') {
                throw new LogicException('An archived learning pathway is read-only.');
            }

            // Once in force, only the status may still move -- that is how it
            // gets archived. NOTE: isDirty([]) means "is anything dirty" and
            // returns true, so the diff is compared directly.
            $contentChanges = array_diff(array_keys($pathway->getDirty()), ['status', 'updated_at']);

            if ($pathway->getOriginal('status') !== 'draft' && $contentChanges !== []) {
                throw new LogicException(
                    'An active learning pathway cannot be edited. Prepare a replacement draft and archive this one.'
                );
            }
        });

        static::deleting(function (self $pathway) {
            $pathway->assertCurriculumNotArchived('removed');

            if ($pathway->status !== 'draft') {
                throw new LogicException('Only an unused draft learning pathway can be deleted. Archive it instead.');
            }
        });
    }

    private function assertCurriculumNotArchived(string $verb): void
    {
        $curriculum = $this->curriculumScope?->curriculum
            ?? CurriculumScope::with('curriculum')->find($this->curriculum_scope_id)?->curriculum;

        if ($curriculum && $curriculum->isArchived()) {
            throw new LogicException(
                "Learning pathways cannot be {$verb} under an archived curriculum version ".
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

    /** Ordered by instructional position, which is the whole point. */
    public function items(): HasMany
    {
        return $this->hasMany(LearningPathwayItem::class)->orderBy('position');
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
