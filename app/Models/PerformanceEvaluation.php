<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * One staff member, one framework, one period.
 *
 * Finalized is immutable in V7A -- no manager-correction path, no
 * supersession, no replacement workflow for the SAME staff+framework+exact
 * period (the unique index on those four columns forbids re-evaluating that
 * exact scope regardless). This row is never rewritten and never deleted. A
 * later evaluation is only legitimate when it is a genuinely different
 * period, framework or framework version -- never dates chosen merely to
 * dodge the uniqueness constraint. If Rahai ever needs to correct a finalized
 * appraisal, that is a separate, explicitly designed future workflow (e.g.
 * manager correction, or void/replacement/supersession), not something this
 * version provides. Deliberate, documented limitation (see
 * PerformanceEvaluationService), not an oversight.
 */
#[Fillable([
    'staff_id', 'staff_category_id', 'performance_framework_id', 'evaluator_user_id',
    'academic_year_id', 'period_start', 'period_end', 'status', 'overall_rating_option_id',
    'summary', 'strengths', 'development_priorities', 'action_plan', 'review_date',
    'finalized_at', 'finalized_by_user_id',
    'staff_name_snapshot', 'staff_number_snapshot', 'position_title_snapshot',
    'staff_category_name_snapshot', 'framework_name_snapshot', 'framework_code_snapshot',
    'framework_version_snapshot', 'evaluator_name_snapshot',
])]
class PerformanceEvaluation extends Model
{
    use Auditable;

    /** Fixed at creation, in every state. */
    private const IDENTITY = ['staff_id', 'staff_category_id', 'performance_framework_id'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'review_date' => 'date',
            'finalized_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $evaluation) {
            foreach (self::IDENTITY as $column) {
                if ($evaluation->isDirty($column)) {
                    throw new LogicException(
                        "An evaluation's staff, category and framework are fixed at creation ({$column})."
                    );
                }
            }

            if ($evaluation->getOriginal('status') === 'finalized') {
                throw new LogicException(
                    'A finalized evaluation is immutable. V7A has no correction or replacement workflow for it.'
                );
            }
        });

        static::deleting(function (self $evaluation) {
            if ($evaluation->status !== 'draft') {
                throw new LogicException('A finalized evaluation is never deleted.');
            }
        });
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function staffCategory(): BelongsTo
    {
        return $this->belongsTo(StaffCategory::class);
    }

    public function framework(): BelongsTo
    {
        return $this->belongsTo(PerformanceFramework::class, 'performance_framework_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_user_id');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by_user_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function overallRatingOption(): BelongsTo
    {
        return $this->belongsTo(PerformanceRatingOption::class, 'overall_rating_option_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PerformanceEvaluationItem::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isFinalized(): bool
    {
        return $this->status === 'finalized';
    }
}
