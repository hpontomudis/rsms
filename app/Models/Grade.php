<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'level_order'])]
class Grade extends Model
{
    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function feeStructures(): HasMany
    {
        return $this->hasMany(FeeStructure::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    /**
     * At most one English programme applies to a grade (enforced by
     * UNIQUE(grade_id) on the pivot); grades outside any programme -- KG and
     * Senior High today -- simply have no row.
     */
    public function englishProgrammeLink(): HasOne
    {
        return $this->hasOne(EnglishProgrammeGrade::class);
    }

    public function englishProgramme(): ?EnglishProgramme
    {
        return $this->englishProgrammeLink?->programme;
    }

    /**
     * At most one learning phase applies to a grade (enforced by
     * UNIQUE(grade_id) on the mapping). Grades outside any phase simply have
     * no row -- the grade table itself stays neutral about curricular
     * frameworks.
     */
    public function learningPhaseLink(): HasOne
    {
        return $this->hasOne(LearningPhaseGrade::class);
    }

    public function learningPhase(): ?LearningPhase
    {
        return $this->learningPhaseLink?->learningPhase;
    }
}
