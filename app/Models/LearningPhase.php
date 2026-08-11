<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A national Learning Phase (Fase) -- Foundation, A through F.
 *
 * Capaian Pembelajaran will hang off a phase, not off a grade: Phase C covers
 * Year 5 and Year 6 with one set of outcomes, and the grades are resolved
 * through the mapping when needed.
 */
#[Fillable(['code', 'name', 'sequence', 'description', 'status'])]
class LearningPhase extends Model
{
    use Auditable;

    /**
     * Grade mappings. Writes go through this relation, never through
     * gradeLinks()' belongsToMany counterpart: attach()/detach()/sync() fire
     * no Eloquent events, so an Auditable mapping written that way records
     * nothing -- established empirically in Step 2a-i.
     */
    public function gradeLinks(): HasMany
    {
        return $this->hasMany(LearningPhaseGrade::class);
    }

    /**
     * READ-ONLY convenience. Never attach()/detach()/sync() through this.
     */
    public function grades(): BelongsToMany
    {
        return $this->belongsToMany(Grade::class, 'learning_phase_grade')->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
