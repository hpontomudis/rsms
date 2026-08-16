<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A class/homeroom-adjacent teacher assignment: one Staff member holding one
 * role (homeroom/assistant/subject_teacher) on one Class, effective-dated
 * the same shape as `class_subject`/`teaching_group_student` (Foundation F2).
 *
 * `subject_teacher` is DEPRECATED for new writes -- `ClassSubject` is the
 * canonical, effective-dated subject-teaching authority. Existing
 * `subject_teacher` rows are preserved as historical legacy data only; see
 * `ClassTeacherService`, the sole write path, which refuses new ones.
 *
 * Stays a Pivot (not a full Model like `TeachingGroupStudent`) so
 * `SchoolClass::teachers()`/`Staff::classes()` keep working for reads.
 * Writes MUST go through `ClassTeacherService` using direct model calls
 * (`ClassTeacher::create()`/`update()`), never the BelongsToMany
 * `attach()`/`detach()`/`sync()` helpers -- those bypass Eloquent model
 * events entirely, which would silently skip this model's Auditable trail
 * (the exact gap `TeachingGroupStudent`'s docblock already documents).
 */
#[Fillable(['class_id', 'staff_id', 'role', 'started_on', 'ended_on'])]
class ClassTeacher extends Pivot
{
    use Auditable;

    protected $table = 'class_teacher';

    public $incrementing = true;

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

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function isOpen(): bool
    {
        return $this->ended_on === null;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('ended_on');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereNotNull('ended_on');
    }
}
