<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * One Student's administrative-class enrollment over a date range
 * (Foundation F3). At most one OPEN row per Student system-wide, DB
 * enforced (`class_student_current_enrollment_unique`) -- not scoped by
 * class, since the invariant is "one current class," not "one current
 * enrollment per class."
 *
 * BOUNDARY CONVENTION: half-open `[enrolled_at, ended_on)` -- `enrolled_at`
 * inclusive, `ended_on` EXCLUSIVE. Deliberately different from
 * `class_subject`/`teaching_group_student`'s closed-interval convention;
 * see the migration's docblock for why (same-day transfer close/open would
 * double-count a student under a closed interval). Every date-range query
 * against this table must use `enrolled_at <= $on AND (ended_on IS NULL OR
 * ended_on > $on)`, never the closed-interval `>=`.
 *
 * `status` and `ended_on` are a matched pair: `active` implies `ended_on
 * IS NULL`; `transferred_out`/`withdrawn` imply `ended_on IS NOT NULL`.
 * `ClassStudentService` is the one write path that keeps them in sync;
 * never write via `attach()`/`detach()`/`sync()`, which would bypass this
 * model's `Auditable` trail entirely.
 */
#[Fillable(['class_id', 'student_id', 'enrolled_at', 'status', 'ended_on'])]
class ClassStudent extends Pivot
{
    use Auditable;

    protected $table = 'class_student';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'date',
            'ended_on' => 'date',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
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

    /**
     * Effective on $on, under this table's half-open [enrolled_at, ended_on)
     * convention.
     */
    public function scopeEffectiveOn(Builder $query, \Illuminate\Support\Carbon $on): Builder
    {
        return $query->whereDate('enrolled_at', '<=', $on)
            ->where(fn ($q) => $q->whereNull('ended_on')->orWhereDate('ended_on', '>', $on));
    }
}
