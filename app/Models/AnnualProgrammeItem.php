<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One pathway item allocated to one reporting period.
 *
 * planned_lesson_periods is the TOTAL JP budget for this item in this period,
 * not a per-week slot. The semester programme spends that budget across one or
 * more scheduling slots.
 */
#[Fillable([
    'annual_programme_id', 'learning_pathway_item_id', 'learning_pathway_id',
    'academic_year_id', 'academic_period_id', 'planned_lesson_periods', 'notes',
])]
class AnnualProgrammeItem extends Model
{
    use Auditable;

    public function annualProgramme(): BelongsTo
    {
        return $this->belongsTo(AnnualProgramme::class);
    }

    public function learningPathwayItem(): BelongsTo
    {
        return $this->belongsTo(LearningPathwayItem::class);
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    /** Scheduling slots that spend this item's budget. */
    public function semesterItems(): HasMany
    {
        return $this->hasMany(SemesterProgrammeItem::class);
    }

    /** Derived, never copied. */
    public function learningObjective(): ?LearningObjective
    {
        return $this->learningPathwayItem?->learningObjective;
    }
}
