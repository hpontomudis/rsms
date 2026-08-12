<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which objective a module sets out to teach.
 *
 * A real model rather than a Laravel pivot, because attach()/detach()/sync()
 * bypass Eloquent events and would leave link changes unaudited -- something
 * this project has verified empirically rather than assumed.
 *
 * curriculum_scope_id and subject_id are mirrored so the database can force
 * both sides to share them; the service sets them, never a caller.
 */
#[Fillable(['teaching_module_id', 'learning_objective_id', 'curriculum_scope_id', 'subject_id'])]
class TeachingModuleLearningObjective extends Model
{
    use Auditable;

    protected $table = 'teaching_module_learning_objective';

    public function teachingModule(): BelongsTo
    {
        return $this->belongsTo(TeachingModule::class);
    }

    public function learningObjective(): BelongsTo
    {
        return $this->belongsTo(LearningObjective::class);
    }
}
