<?php

namespace App\Evidence\Providers;

use App\Evidence\Contracts\EvidenceProvider;
use App\Evidence\EvidenceAvailability;
use App\Evidence\SystemEvidence;
use App\Models\AuditLog;
use App\Models\Concerns\ResolvesUnambiguousUser;
use App\Models\SemesterProgramme;
use App\Models\SemesterProgrammeItem;
use App\Models\Staff;
use Illuminate\Support\Carbon;

/**
 * The Semester Programme half of the context/contribution pair. See
 * AnnualProgrammeContributionProvider for the full reasoning.
 */
class SemesterProgrammeContributionProvider implements EvidenceProvider
{
    use ResolvesUnambiguousUser;

    public function compute(Staff $staff, Carbon $start, Carbon $end): SystemEvidence
    {
        $userId = $this->unambiguousUserId($staff);

        if ($userId === null) {
            return SystemEvidence::unavailable(
                'semester_programme_contribution',
                'Semester Programme contribution (audit-derived)',
                $staff->user_id === null
                    ? 'This staff member has no linked login, so contribution cannot be attributed.'
                    : 'This login is shared by more than one staff record, so contribution cannot be unambiguously attributed.',
            );
        }

        $count = AuditLog::whereIn('auditable_type', [SemesterProgramme::class, SemesterProgrammeItem::class])
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->count();

        return new SystemEvidence(
            key: 'semester_programme_contribution',
            label: 'Recorded Semester Programme edits (audit-derived)',
            availability: EvidenceAvailability::Available,
            numericValue: (float) $count,
            rangeStart: $start,
            rangeEnd: $end,
            note: 'Counts audited create/update actions attributed to this staff member specifically, not roster schedule existence.',
        );
    }
}
