<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One grade's membership of one learning phase.
 *
 * A full Model rather than a Pivot, for the reason proved in Step 2a-i:
 * BelongsToMany::attach()/detach()/sync() go through the query builder and
 * fire no Eloquent events, so an Auditable pivot written that way records
 * nothing at all. Every write here goes through the model.
 */
#[Fillable(['learning_phase_id', 'grade_id'])]
class LearningPhaseGrade extends Model
{
    use Auditable;

    protected $table = 'learning_phase_grade';

    public function learningPhase(): BelongsTo
    {
        return $this->belongsTo(LearningPhase::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }
}
