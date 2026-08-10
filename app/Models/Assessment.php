<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * NOTE: the `term` column is DEPRECATED. `academic_period_id` is the single
 * canonical source of an assessment's reporting period. `term` is retained
 * only for rollback safety during this phase and is deliberately absent from
 * $fillable so no code path can write to it. It will be dropped in a later
 * cleanup migration.
 */
#[Fillable(['class_subject_id', 'academic_period_id', 'name', 'max_score', 'assessment_date'])]
class Assessment extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'max_score' => 'decimal:2',
            'assessment_date' => 'date',
        ];
    }

    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class);
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(AssessmentResult::class);
    }
}
