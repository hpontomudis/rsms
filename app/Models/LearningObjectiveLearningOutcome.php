<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One objective's derivation from one learning outcome.
 *
 * A full Model rather than a Pivot, for the reason proved twice already in
 * this project: attach()/detach()/sync() go through the query builder and fire
 * no Eloquent events, so an Auditable pivot written that way records nothing.
 * A change to what a TP traces back to is exactly the kind of edit that must
 * leave a trail.
 *
 * curriculum_scope_id and subject_id are mirrored from the objective so the
 * composite foreign keys can prove both sides share them. The service sets
 * them; nothing else should.
 */
#[Fillable(['learning_objective_id', 'learning_outcome_id', 'curriculum_scope_id', 'subject_id'])]
class LearningObjectiveLearningOutcome extends Model
{
    use Auditable;

    protected $table = 'learning_objective_learning_outcome';

    public function learningObjective(): BelongsTo
    {
        return $this->belongsTo(LearningObjective::class);
    }

    public function learningOutcome(): BelongsTo
    {
        return $this->belongsTo(LearningOutcome::class);
    }
}
