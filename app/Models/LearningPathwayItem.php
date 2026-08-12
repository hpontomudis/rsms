<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One learning objective's place in a pathway.
 *
 * A real model, written explicitly -- never attach()/detach()/sync(), which
 * fire no Eloquent events and would leave sequence changes unaudited. Proven
 * four times in this project now.
 */
#[Fillable([
    'learning_pathway_id', 'learning_objective_id',
    'curriculum_scope_id', 'subject_id', 'position', 'notes',
])]
class LearningPathwayItem extends Model
{
    use Auditable;

    public function learningPathway(): BelongsTo
    {
        return $this->belongsTo(LearningPathway::class);
    }

    public function learningObjective(): BelongsTo
    {
        return $this->belongsTo(LearningObjective::class);
    }
}
