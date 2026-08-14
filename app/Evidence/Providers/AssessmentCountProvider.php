<?php

namespace App\Evidence\Providers;

use App\Evidence\Contracts\EvidenceProvider;
use App\Evidence\EvidenceAvailability;
use App\Evidence\SystemEvidence;
use App\Models\Assessment;
use App\Models\Staff;
use Illuminate\Support\Carbon;

/**
 * Assessments are already assignment-scoped; no ambiguity.
 */
class AssessmentCountProvider implements EvidenceProvider
{
    public function compute(Staff $staff, Carbon $start, Carbon $end): SystemEvidence
    {
        $count = Assessment::whereHas('classSubject', fn ($q) => $q->where('staff_id', $staff->id))
            ->whereBetween('assessment_date', [$start->toDateString(), $end->toDateString()])
            ->count();

        return new SystemEvidence(
            key: 'assessment_count',
            label: 'Assessments created',
            availability: EvidenceAvailability::Available,
            numericValue: (float) $count,
            rangeStart: $start,
            rangeEnd: $end,
        );
    }
}
