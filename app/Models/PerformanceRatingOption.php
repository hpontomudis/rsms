<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['performance_framework_id', 'value', 'label', 'description', 'position'])]
class PerformanceRatingOption extends Model
{
    use Auditable;

    protected static function booted(): void
    {
        static::saving(function (self $option) {
            if ($option->framework && ! $option->framework->isStructureEditable()) {
                throw new LogicException(
                    "This framework's structure is frozen and its rating options can no longer be edited."
                );
            }
        });

        static::deleting(function (self $option) {
            if ($option->framework && ! $option->framework->isStructureEditable()) {
                throw new LogicException(
                    "This framework's structure is frozen and its rating options can no longer be removed."
                );
            }
        });
    }

    public function framework(): BelongsTo
    {
        return $this->belongsTo(PerformanceFramework::class, 'performance_framework_id');
    }
}
