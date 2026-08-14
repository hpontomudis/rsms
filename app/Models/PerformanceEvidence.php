<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One evidence entry -- system-computed or human-authored -- against one
 * evaluation item.
 *
 * Auditable, and genuinely so: manual evidence can be created, edited and
 * deleted independently while the parent evaluation is draft, unlike every
 * other write-once child model in this project. System evidence rows are
 * written only inside PerformanceEvaluationService::finalize() and are never
 * edited afterwards -- the guard below freezes both kinds identically once
 * the parent is finalized.
 */
#[Fillable([
    'performance_evaluation_item_id', 'source_type', 'source_key', 'source_label',
    'availability', 'numeric_value', 'boolean_value', 'text_value',
    'date_range_start', 'date_range_end', 'note', 'captured_at',
])]
class PerformanceEvidence extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'boolean_value' => 'boolean',
            'date_range_start' => 'date',
            'date_range_end' => 'date',
            'captured_at' => 'datetime',
        ];
    }

    /**
     * finalize() writes system-evidence rows BEFORE flipping the parent's
     * status to 'finalized' -- last, in the same transaction -- so these
     * guards never see the parent as finalized during that write. They only
     * ever catch an attempt made after the transaction has already committed.
     */
    protected static function booted(): void
    {
        static::creating(function (self $evidence) {
            if ($evidence->item?->evaluation?->isFinalized()) {
                throw new LogicException('Evidence cannot be added to a finalized evaluation.');
            }
        });

        static::updating(function (self $evidence) {
            if ($evidence->item?->evaluation?->isFinalized()) {
                throw new LogicException('Evidence on a finalized evaluation is immutable.');
            }
        });

        static::deleting(function (self $evidence) {
            if ($evidence->item?->evaluation?->isFinalized()) {
                throw new LogicException('Evidence on a finalized evaluation is never deleted.');
            }
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PerformanceEvaluationItem::class, 'performance_evaluation_item_id');
    }

    public function isSystem(): bool
    {
        return $this->source_type === 'system';
    }

    public function isManual(): bool
    {
        return $this->source_type === 'manual';
    }

    public function isAvailable(): bool
    {
        return $this->availability === 'available';
    }
}
