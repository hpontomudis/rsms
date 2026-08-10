<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's membership of one teaching group over a date range.
 *
 * A full Model rather than a Pivot, for the reason established in Step 2a-i:
 * BelongsToMany::attach()/detach()/sync() go through the query builder and
 * fire no Eloquent events, so an Auditable pivot written that way records
 * nothing at all. Every write here goes through the model.
 */
#[Fillable(['teaching_group_id', 'student_id', 'started_on', 'ended_on', 'notes'])]
class TeachingGroupStudent extends Model
{
    use Auditable;

    protected $table = 'teaching_group_student';

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'ended_on' => 'date',
        ];
    }

    public function teachingGroup(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function isOpen(): bool
    {
        return $this->ended_on === null;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('ended_on');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereNotNull('ended_on');
    }
}
