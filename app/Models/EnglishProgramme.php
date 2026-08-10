<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An English proficiency framework (Primary, Junior High). Senior High has
 * none -- English is taught there as an ordinary class-based subject.
 */
#[Fillable(['name', 'code', 'description', 'status'])]
class EnglishProgramme extends Model
{
    use Auditable;

    public function levels(): HasMany
    {
        return $this->hasMany(EnglishLevel::class)->orderBy('sequence');
    }

    /**
     * The applicability rows themselves.
     *
     * IMPORTANT: write through this relation (create/delete on
     * EnglishProgrammeGrade), never via attach()/detach() on grades() below --
     * those bypass Eloquent model events, so no audit_logs row would be
     * written. See EnglishProgrammeGrade.
     */
    public function gradeLinks(): HasMany
    {
        return $this->hasMany(EnglishProgrammeGrade::class);
    }

    /**
     * READ-ONLY convenience. Do not attach()/detach() -- see gradeLinks().
     */
    public function grades(): BelongsToMany
    {
        return $this->belongsToMany(Grade::class, 'english_programme_grade')->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
