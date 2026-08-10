<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's assessed English proficiency for a period of time.
 *
 * Changing proficiency means closing the open row and opening a new one --
 * never editing the level in place, which would erase the fact that the
 * student was ever at the earlier level.
 */
#[Fillable([
    'student_id', 'english_level_id', 'started_on', 'ended_on',
    'assessed_on', 'placement_reason', 'notes',
])]
class StudentEnglishLevelPlacement extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'ended_on' => 'date',
            'assessed_on' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function englishLevel(): BelongsTo
    {
        return $this->belongsTo(EnglishLevel::class);
    }

    public function isOpen(): bool
    {
        return $this->ended_on === null;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('ended_on');
    }
}
