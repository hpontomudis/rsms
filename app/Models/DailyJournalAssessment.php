<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * That an assessment activity was used in a session -- and nothing more.
 *
 * No score lives here. assessment_results remains the only score store, and
 * this link exists so a lesson record can say "we ran the formative quiz"
 * without restating a single mark.
 */
#[Fillable(['daily_journal_id', 'assessment_id', 'class_subject_id'])]
class DailyJournalAssessment extends Model
{
    use Auditable;

    protected $table = 'daily_journal_assessment';

    public function dailyJournal(): BelongsTo
    {
        return $this->belongsTo(DailyJournal::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }
}
