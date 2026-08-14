<?php

namespace App\Services;

use App\Evidence\EvidenceService;
use App\Models\AcademicYear;
use App\Models\PerformanceEvaluation;
use App\Models\PerformanceEvaluationItem;
use App\Models\PerformanceEvidence;
use App\Models\PerformanceFramework;
use App\Models\PerformanceRatingOption;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creating and preparing a Performance Evaluation.
 *
 * finalize() lives in this class too (see below) because it is the one method
 * that turns a draft into history; everything above it is ordinary draft
 * housekeeping.
 *
 * NO CORRECTION PATH. Finalized is immutable in V7A -- no manager-edit, no
 * supersession, no "unfinalize", and no replacement workflow for the SAME
 * staff+framework+exact period (the unique index on those four columns
 * already forbids re-evaluating that exact scope). A later evaluation is only
 * legitimate when it represents a genuinely different period, framework or
 * framework version -- not dates picked merely to get around the constraint.
 * The mistaken row is never rewritten or deleted. This is a documented
 * limitation, chosen deliberately as the smaller, safer first version rather
 * than build a correction workflow with no concrete Rahai requirement driving
 * its shape yet. If one is needed later, it is a separate, explicitly
 * designed feature (e.g. manager correction, or void/replacement/
 * supersession) -- not something achieved by juggling this evaluation's
 * dates.
 */
class PerformanceEvaluationService
{
    public function __construct(private EvidenceService $evidence) {}

    /**
     * Start a draft. Provisions one empty response row per indicator on the
     * framework's CURRENT structure -- which is safe precisely because an
     * active framework's structure is frozen, so "every indicator has an
     * item" is true from the moment of creation and needs no later
     * reconciliation.
     */
    public function create(
        Staff $staff,
        PerformanceFramework $framework,
        User $evaluator,
        string $periodStart,
        string $periodEnd,
        ?AcademicYear $academicYear = null,
    ): PerformanceEvaluation {
        if ($staff->staff_category_id === null) {
            $this->fail('staff_id', "{$staff->fullName()} has no staff category assigned. Assign one before starting an evaluation.");
        }

        if (! $framework->isActive()) {
            $this->fail('performance_framework_id', "{$framework->name} is not active, so a new evaluation cannot be created against it.");
        }

        if ($framework->staff_category_id !== $staff->staff_category_id) {
            $this->fail('performance_framework_id', "{$framework->name} applies to a different staff category than {$staff->fullName()}'s.");
        }

        $start = Carbon::parse($periodStart);
        $end = Carbon::parse($periodEnd);

        if ($end->lt($start)) {
            $this->fail('period_end', 'The evaluation period cannot end before it starts.');
        }

        // No is_current fallback: checked against the year's own stored dates.
        if ($academicYear && ($start->lt($academicYear->start_date) || $end->gt($academicYear->end_date))) {
            $this->fail(
                'period_end',
                "The evaluation period must fall within {$academicYear->name} ({$academicYear->start_date->toDateString()} to {$academicYear->end_date->toDateString()})."
            );
        }

        $duplicate = PerformanceEvaluation::where('staff_id', $staff->id)
            ->where('performance_framework_id', $framework->id)
            ->whereDate('period_start', $start)
            ->whereDate('period_end', $end)
            ->exists();

        if ($duplicate) {
            $this->fail('period_end', 'An evaluation already exists for this staff member, framework and exact date range.');
        }

        return DB::transaction(function () use ($staff, $framework, $evaluator, $start, $end, $academicYear) {
            $evaluation = PerformanceEvaluation::create([
                'staff_id' => $staff->id,
                // The category AT CREATION -- never re-derived from the live
                // staff row, not even at finalization. See the model docblock.
                'staff_category_id' => $staff->staff_category_id,
                'performance_framework_id' => $framework->id,
                'evaluator_user_id' => $evaluator->id,
                'academic_year_id' => $academicYear?->id,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'status' => 'draft',
            ]);

            foreach ($framework->sections()->with('indicators')->get() as $section) {
                foreach ($section->indicators as $indicator) {
                    PerformanceEvaluationItem::create([
                        'performance_evaluation_id' => $evaluation->id,
                        'performance_framework_id' => $framework->id,
                        'performance_indicator_id' => $indicator->id,
                    ]);
                }
            }

            return $evaluation->refresh();
        });
    }

    /** Development-plan fields. The only things a draft header carries. */
    public function updateDraft(PerformanceEvaluation $evaluation, array $attributes): PerformanceEvaluation
    {
        $this->assertDraft($evaluation);

        $evaluation->update([
            'summary' => $this->orNull($attributes['summary'] ?? null),
            'strengths' => $this->orNull($attributes['strengths'] ?? null),
            'development_priorities' => $this->orNull($attributes['development_priorities'] ?? null),
            'action_plan' => $this->orNull($attributes['action_plan'] ?? null),
            'review_date' => $attributes['review_date'] ?? null,
        ]);

        return $evaluation->refresh();
    }

    public function setOverallRating(PerformanceEvaluation $evaluation, ?PerformanceRatingOption $option): PerformanceEvaluation
    {
        $this->assertDraft($evaluation);

        if ($option && $option->performance_framework_id !== $evaluation->performance_framework_id) {
            $this->fail('overall_rating_option_id', 'That rating option does not belong to this evaluation\'s framework.');
        }

        $evaluation->update(['overall_rating_option_id' => $option?->id]);

        return $evaluation->refresh();
    }

    public function deleteDraft(PerformanceEvaluation $evaluation): void
    {
        $this->assertDraft($evaluation);

        DB::transaction(function () use ($evaluation) {
            foreach ($evaluation->items()->get() as $item) {
                $item->evidence()->get()->each->delete();
                $item->delete();
            }
            $evaluation->delete();
        });
    }

    /**
     * What the draft screen shows: every item alongside its LIVE system
     * evidence. Nothing here is persisted -- see EvidenceService's docblock
     * for why that is exactly the point.
     *
     * @return Collection<int, array{item: PerformanceEvaluationItem, evidence: ?\App\Evidence\SystemEvidence}>
     */
    public function preview(PerformanceEvaluation $evaluation): Collection
    {
        return $evaluation->items()->with('indicator.section', 'evidence')->get()
            ->sortBy(fn ($item) => [$item->indicator->section->position, $item->indicator->position])
            ->map(fn ($item) => [
                'item' => $item,
                'evidence' => $this->evidence->forIndicator(
                    $item->indicator,
                    $evaluation->staff,
                    $evaluation->period_start,
                    $evaluation->period_end,
                ),
            ])
            ->values();
    }

    /**
     * Draft -> finalized. One transaction; nothing commits on any failure.
     *
     * An archived Framework does NOT block this -- structure was frozen the
     * moment it left draft, so an in-flight Evaluation finalizes against
     * exactly the structure it was created under, active or archived alike.
     *
     * System evidence is recomputed LIVE here (never reused from a prior
     * preview()) and snapshotted into PerformanceEvidence rows BEFORE the
     * status flip below, so PerformanceEvidence's own finalized-guard never
     * fires during this write. Manual evidence already on each item is left
     * untouched. This method never writes rating_option_id, numeric_value,
     * boolean_value or narrative_response -- those are exclusively
     * PerformanceEvaluationItemService::respond()'s, and evidence never
     * feeds back into them. That separation is the evidence/rating firewall.
     */
    public function finalize(PerformanceEvaluation $evaluation, User $finalizer): PerformanceEvaluation
    {
        $this->assertDraft($evaluation);

        $staff = $evaluation->staff;
        $framework = $evaluation->framework;

        $items = $evaluation->items()->with('indicator.section')->get();

        if ($items->isEmpty()) {
            $this->fail('status', 'This evaluation has no indicators and cannot be finalized.');
        }

        foreach ($items as $item) {
            if (! $item->hasResponse()) {
                $this->fail('status', "\"{$item->indicator->name}\" has no response yet.");
            }
        }

        if ($evaluation->overall_rating_option_id === null) {
            $this->fail('overall_rating_option_id', 'Select an overall professional rating before finalizing.');
        }

        $overallOption = $evaluation->overallRatingOption;

        if (! $overallOption || $overallOption->performance_framework_id !== $framework->id) {
            $this->fail('overall_rating_option_id', 'The overall rating no longer belongs to this evaluation\'s framework.');
        }

        return DB::transaction(function () use ($evaluation, $staff, $framework, $finalizer, $items) {
            $evaluation = PerformanceEvaluation::whereKey($evaluation->id)->lockForUpdate()->firstOrFail();

            foreach ($items as $item) {
                $indicator = $item->indicator;
                $section = $indicator->section;

                if ($indicator->hasSystemEvidence()) {
                    $live = $this->evidence->forIndicator($indicator, $staff, $evaluation->period_start, $evaluation->period_end);

                    PerformanceEvidence::create([
                        'performance_evaluation_item_id' => $item->id,
                        'source_type' => 'system',
                        'source_key' => $indicator->system_evidence_key,
                        'source_label' => $live->label,
                        'availability' => $live->availability->value,
                        'numeric_value' => $live->numericValue,
                        'boolean_value' => $live->booleanValue,
                        'text_value' => $live->textValue,
                        'date_range_start' => $live->rangeStart?->toDateString(),
                        'date_range_end' => $live->rangeEnd?->toDateString(),
                        'note' => $live->note,
                        'captured_at' => now(),
                    ]);
                }

                $ratingOption = $item->ratingOption;

                $item->update([
                    'section_name_snapshot' => $section->name,
                    'section_position_snapshot' => $section->position,
                    'indicator_name_snapshot' => $indicator->name,
                    'indicator_description_snapshot' => $indicator->description,
                    'indicator_position_snapshot' => $indicator->position,
                    'indicator_type_snapshot' => $indicator->indicator_type,
                    'rating_label_snapshot' => $ratingOption?->label,
                    'rating_value_snapshot' => $ratingOption?->value,
                ]);
            }

            $evaluation->update([
                'staff_name_snapshot' => $staff->fullName(),
                'staff_number_snapshot' => $staff->staff_number,
                'position_title_snapshot' => $staff->position?->title,
                // The Evaluation's OWN staff_category_id (fixed at creation),
                // never the Staff row's current one -- see the model docblock.
                'staff_category_name_snapshot' => $evaluation->staffCategory->name,
                'framework_name_snapshot' => $framework->name,
                'framework_code_snapshot' => $framework->code,
                'framework_version_snapshot' => $framework->version,
                // ALWAYS User::name, never Staff::fullName() -- the evaluator
                // may have no Staff row at all (e.g. an external evaluator).
                'evaluator_name_snapshot' => $evaluation->evaluator?->name,
                'finalized_at' => now(),
                'finalized_by_user_id' => $finalizer->id,
                'status' => 'finalized',
            ]);

            return $evaluation->refresh();
        });
    }

    private function assertDraft(PerformanceEvaluation $evaluation): void
    {
        if (! $evaluation->isDraft()) {
            $this->fail('status', 'A finalized evaluation is immutable. V7A has no correction or replacement workflow for it.');
        }
    }

    private function orNull(?string $value): ?string
    {
        return ($value === null || trim($value) === '') ? null : $value;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
