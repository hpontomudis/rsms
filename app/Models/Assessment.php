<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['class_subject_id', 'name', 'term', 'max_score', 'assessment_date'])]
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

    public function results(): HasMany
    {
        return $this->hasMany(AssessmentResult::class);
    }
}
