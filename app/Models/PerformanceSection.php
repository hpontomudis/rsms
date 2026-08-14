<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['performance_framework_id', 'name', 'description', 'position'])]
class PerformanceSection extends Model
{
    use Auditable;

    protected static function booted(): void
    {
        static::saving(function (self $section) {
            if ($section->framework && ! $section->framework->isStructureEditable()) {
                throw new LogicException(
                    "This framework's structure is frozen and its sections can no longer be edited."
                );
            }
        });

        static::deleting(function (self $section) {
            if ($section->framework && ! $section->framework->isStructureEditable()) {
                throw new LogicException(
                    "This framework's structure is frozen and its sections can no longer be removed."
                );
            }
        });
    }

    public function framework(): BelongsTo
    {
        return $this->belongsTo(PerformanceFramework::class, 'performance_framework_id');
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(PerformanceIndicator::class)->orderBy('position');
    }
}
