<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One scheduling slot. Several may point at the same annual item -- an 8 JP
 * objective taught across three weeks is three rows and one budget.
 *
 * No learning_pathway_item_id: it is already determined by the annual item,
 * and a second path to the same fact could disagree with the first.
 */
#[Fillable([
    'semester_programme_id', 'annual_programme_item_id', 'annual_programme_id',
    'academic_period_id', 'position', 'week_label',
    'planned_start_date', 'planned_end_date', 'planned_lesson_periods', 'notes',
])]
class SemesterProgrammeItem extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'planned_start_date' => 'date',
            'planned_end_date' => 'date',
        ];
    }

    public function semesterProgramme(): BelongsTo
    {
        return $this->belongsTo(SemesterProgramme::class);
    }

    public function annualProgrammeItem(): BelongsTo
    {
        return $this->belongsTo(AnnualProgrammeItem::class);
    }
}
