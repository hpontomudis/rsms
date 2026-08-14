<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * One indicator's response within one evaluation.
 *
 * Editable only while the parent evaluation is draft. Response writes go
 * through PerformanceEvaluationItemService, which enforces exactly one
 * type-appropriate field per indicator_type; this model only guards the
 * lifecycle boundary.
 */
#[Fillable([
    'performance_evaluation_id', 'performance_framework_id', 'performance_indicator_id',
    'rating_option_id', 'numeric_value', 'boolean_value', 'narrative_response', 'evaluator_comment',
    'section_name_snapshot', 'section_position_snapshot', 'indicator_name_snapshot',
    'indicator_description_snapshot', 'indicator_position_snapshot', 'indicator_type_snapshot',
    'rating_label_snapshot', 'rating_value_snapshot',
])]
class PerformanceEvaluationItem extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'boolean_value' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $item) {
            if ($item->evaluation && $item->evaluation->isFinalized()) {
                throw new LogicException('A finalized evaluation\'s responses are immutable.');
            }
        });
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(PerformanceEvaluation::class, 'performance_evaluation_id');
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(PerformanceIndicator::class, 'performance_indicator_id');
    }

    public function ratingOption(): BelongsTo
    {
        return $this->belongsTo(PerformanceRatingOption::class, 'rating_option_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(PerformanceEvidence::class);
    }

    public function hasResponse(): bool
    {
        return $this->rating_option_id !== null
            || $this->numeric_value !== null
            || $this->boolean_value !== null
            || ($this->narrative_response !== null && trim($this->narrative_response) !== '');
    }
}
