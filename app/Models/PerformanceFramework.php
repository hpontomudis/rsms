<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * The school's definition of good performance, for ONE staff category.
 *
 * Lifecycle mirrors Curriculum: draft is fully editable and can never be
 * evaluated against; active freezes every section, indicator and rating
 * option beneath it and permits new evaluations; archived stops new
 * evaluations but leaves already-active work untouched. A draft evaluation
 * started while this framework was active may still finalize after this
 * framework is archived -- the STRUCTURE was already frozen at activation,
 * and archiving governs new work, not work already in flight.
 */
#[Fillable(['staff_category_id', 'name', 'code', 'version', 'status', 'effective_from', 'effective_to'])]
class PerformanceFramework extends Model
{
    use Auditable;

    /** Fixed once the framework leaves draft. */
    private const IDENTITY = ['staff_category_id', 'code', 'version'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $framework) {
            if ($framework->getOriginal('status') === 'draft') {
                return;
            }

            foreach (self::IDENTITY as $column) {
                if ($framework->isDirty($column)) {
                    throw new LogicException(
                        "A performance framework's identity cannot be changed once it has left draft ({$column}). ".
                        'Archive this version and create a new one instead.'
                    );
                }
            }
        });

        static::deleting(function (self $framework) {
            if ($framework->status !== 'draft') {
                throw new LogicException('Only an unused draft framework can be deleted. Archive it instead.');
            }
        });
    }

    public function staffCategory(): BelongsTo
    {
        return $this->belongsTo(StaffCategory::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(PerformanceSection::class)->orderBy('position');
    }

    public function ratingOptions(): HasMany
    {
        return $this->hasMany(PerformanceRatingOption::class)->orderBy('position');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(PerformanceEvaluation::class);
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

    /** Sections/indicators/rating options may be edited only while draft. */
    public function isStructureEditable(): bool
    {
        return $this->isDraft();
    }
}
