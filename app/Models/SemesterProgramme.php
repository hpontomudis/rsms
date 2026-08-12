<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Program Semester -- when, inside one reporting period, the items this
 * annual programme allocated to that period will actually be taught.
 *
 * It elaborates the annual plan rather than standing alone, so there is no
 * second planning universe that could disagree about which pathway is in use.
 */
#[Fillable(['annual_programme_id', 'academic_period_id', 'academic_year_id', 'notes', 'status'])]
class SemesterProgramme extends Model
{
    use Auditable;

    private const IDENTITY = ['annual_programme_id', 'academic_period_id', 'academic_year_id'];

    protected static function booted(): void
    {
        static::updating(function (self $programme) {
            foreach (self::IDENTITY as $column) {
                if ($programme->isDirty($column)) {
                    throw new LogicException(
                        "A semester programme's annual programme and period are fixed at creation ({$column})."
                    );
                }
            }

            if ($programme->getOriginal('status') === 'archived') {
                throw new LogicException('An archived semester programme is read-only.');
            }
        });

        static::deleting(function (self $programme) {
            if ($programme->status !== 'draft') {
                throw new LogicException('Only an unused draft semester programme can be deleted. Archive it instead.');
            }
        });
    }

    public function annualProgramme(): BelongsTo
    {
        return $this->belongsTo(AnnualProgramme::class);
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    /** Slots in schedule order. */
    public function items(): HasMany
    {
        return $this->hasMany(SemesterProgrammeItem::class)->orderBy('position');
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

    public function isEditable(): bool
    {
        return ! $this->isArchived();
    }
}
