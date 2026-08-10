<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group of students taught together within one academic year.
 *
 * english_level_id present => an English proficiency group, and the
 * programme it belongs to is derived through the level. Absent => a generic
 * group with no eligibility rules of its own.
 */
#[Fillable(['academic_year_id', 'name', 'english_level_id', 'status'])]
class TeachingGroup extends Model
{
    use Auditable;

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function englishLevel(): BelongsTo
    {
        return $this->belongsTo(EnglishLevel::class);
    }

    /**
     * Membership rows, open and closed. Written through this relation rather
     * than a belongsToMany: attach()/detach()/sync() bypass Eloquent events
     * and would leave the audit trail silently empty.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(TeachingGroupStudent::class);
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()->whereNull('ended_on');
    }

    /**
     * Teaching assignments against this group, open and closed. Stored in
     * class_subject -- there is deliberately no separate group-subject table.
     */
    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(ClassSubject::class);
    }

    public function isEnglishGroup(): bool
    {
        return $this->english_level_id !== null;
    }

    /**
     * The English programme this group teaches to, derived rather than stored.
     */
    public function englishProgramme(): ?EnglishProgramme
    {
        return $this->englishLevel?->programme;
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
