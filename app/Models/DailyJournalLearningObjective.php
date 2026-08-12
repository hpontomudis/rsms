<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which objective was ACTUALLY addressed in a session.
 *
 * Independent of the module's planned objectives on purpose -- a lesson may
 * cover fewer, or something unplanned. Inferring these from the plan would
 * throw away the only thing this layer records.
 */
#[Fillable(['daily_journal_id', 'learning_objective_id', 'curriculum_scope_id', 'subject_id'])]
class DailyJournalLearningObjective extends Model
{
    use Auditable;

    protected $table = 'daily_journal_learning_objective';

    public function dailyJournal(): BelongsTo
    {
        return $this->belongsTo(DailyJournal::class);
    }

    public function learningObjective(): BelongsTo
    {
        return $this->belongsTo(LearningObjective::class);
    }
}
