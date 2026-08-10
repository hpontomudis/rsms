<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A proficiency level within a programme. Ordering is `sequence`, scoped to
 * the programme -- "Level A" in one programme is unrelated to a same-named
 * level in another, and no equivalence to national Learning Phases is implied.
 */
#[Fillable(['english_programme_id', 'name', 'code', 'sequence', 'description', 'status'])]
class EnglishLevel extends Model
{
    use Auditable;

    public function programme(): BelongsTo
    {
        return $this->belongsTo(EnglishProgramme::class, 'english_programme_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
