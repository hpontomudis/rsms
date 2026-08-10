<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A teaching assignment: one subject, taught in one class, by one staff member,
 * for a bounded period of time.
 *
 * Effective-dated on purpose. Reassigning a subject to a different teacher
 * CLOSES the current row (`ended_on`) and OPENS a new one -- it never mutates
 * `staff_id` in place, because records that point here (assessments today,
 * Phase 5 planning records later) must keep identifying the teacher who was
 * actually in force when they were created.
 */
#[Fillable(['class_id', 'subject_id', 'staff_id', 'started_on', 'ended_on'])]
class ClassSubject extends Model
{
    use Auditable;

    protected $table = 'class_subject';

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'ended_on' => 'date',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /**
     * The assignment currently in force (the only one writable by its teacher).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_on');
    }

    /**
     * Superseded assignments. Retained for historical attribution; read-only
     * to teachers.
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereNotNull('ended_on');
    }

    public function isActive(): bool
    {
        return $this->ended_on === null;
    }
}
