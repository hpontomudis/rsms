<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToDraftCurriculum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What one curriculum version says something about: a Learning Phase for the
 * national curriculum, or an English Level for a Rahai English curriculum --
 * exactly one, enforced by a CHECK constraint.
 *
 * `english_programme_id` mirrors the curriculum's programme and exists solely
 * so composite foreign keys can enforce that a level belongs to the same
 * programme as its curriculum. It is set by the service, never by hand.
 */
#[Fillable(['curriculum_id', 'english_programme_id', 'learning_phase_id', 'english_level_id'])]
class CurriculumScope extends Model
{
    use Auditable, BelongsToDraftCurriculum;

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function learningPhase(): BelongsTo
    {
        return $this->belongsTo(LearningPhase::class);
    }

    public function englishLevel(): BelongsTo
    {
        return $this->belongsTo(EnglishLevel::class);
    }

    public function learningOutcomes(): HasMany
    {
        return $this->hasMany(LearningOutcome::class)->orderBy('sequence');
    }

    public function learningObjectives(): HasMany
    {
        return $this->hasMany(LearningObjective::class)->orderBy('reference_order');
    }

    public function learningPathways(): HasMany
    {
        return $this->hasMany(LearningPathway::class);
    }

    public function isPhaseBased(): bool
    {
        return $this->learning_phase_id !== null;
    }

    /** "Phase C" or "Green". */
    public function displayName(): string
    {
        return $this->isPhaseBased()
            ? ($this->learningPhase?->name ?? 'Unknown phase')
            : ($this->englishLevel?->name ?? 'Unknown level');
    }

    /**
     * The grades this scope reaches, resolved through the phase mapping rather
     * than stored. Empty for an English level scope, whose students are
     * grouped by proficiency rather than by grade.
     */
    public function grades()
    {
        return $this->learningPhase
            ? $this->learningPhase->gradeLinks()->with('grade')->get()
                ->sortBy(fn ($link) => $link->grade->level_order)
                ->map(fn ($link) => $link->grade)->values()
            : collect();
    }

    private function resolveCurriculum(): ?Curriculum
    {
        return $this->curriculum_id ? Curriculum::find($this->curriculum_id) : null;
    }
}
