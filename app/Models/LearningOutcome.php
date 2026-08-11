<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToDraftCurriculum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One learning outcome -- a Capaian Pembelajaran on the national curriculum,
 * an English Learning Outcome on a Rahai English curriculum. Same table, same
 * engine; only the label differs, and it is derived from the curriculum.
 *
 * Belongs to an ordinary `subjects` row: there is no separate English subject
 * catalogue, and none is wanted.
 */
#[Fillable(['curriculum_scope_id', 'subject_id', 'code', 'title', 'outcome_text', 'sequence'])]
class LearningOutcome extends Model
{
    use Auditable, BelongsToDraftCurriculum;

    public function curriculumScope(): BelongsTo
    {
        return $this->belongsTo(CurriculumScope::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** Reverse traceability: which objectives derive from this outcome. */
    public function objectiveLinks(): HasMany
    {
        return $this->hasMany(LearningObjectiveLearningOutcome::class);
    }

    private function resolveCurriculum(): ?Curriculum
    {
        return CurriculumScope::find($this->curriculum_scope_id)?->curriculum;
    }
}
