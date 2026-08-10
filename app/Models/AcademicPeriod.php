<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reporting period within an academic year (e.g. "Semester 1").
 *
 * The canonical replacement for the deprecated free-text `assessments.term`.
 * Neither the number of periods nor their names are hardcoded anywhere --
 * every consumer reads these rows and orders by `sequence`.
 */
#[Fillable(['academic_year_id', 'name', 'sequence', 'start_date', 'end_date'])]
class AcademicPeriod extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }
}
