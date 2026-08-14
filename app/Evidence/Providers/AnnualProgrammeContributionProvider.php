<?php

namespace App\Evidence\Providers;

use App\Evidence\Contracts\EvidenceProvider;
use App\Evidence\EvidenceAvailability;
use App\Evidence\SystemEvidence;
use App\Models\AnnualProgramme;
use App\Models\AnnualProgrammeItem;
use App\Models\AuditLog;
use App\Models\Concerns\ResolvesUnambiguousUser;
use App\Models\Staff;
use Illuminate\Support\Carbon;

/**
 * INDIVIDUAL contribution, derived from audit_logs.user_id -- the complement
 * to AnnualProgrammeContextProvider's roster-existence fact. "The roster has a
 * plan" and "this staff member edited it N times" are kept as two separate
 * evidence entries because they answer different questions, and merging them
 * would let existence of a shared plan masquerade as one person's authorship.
 */
class AnnualProgrammeContributionProvider implements EvidenceProvider
{
    use ResolvesUnambiguousUser;

    public function compute(Staff $staff, Carbon $start, Carbon $end): SystemEvidence
    {
        $userId = $this->unambiguousUserId($staff);

        if ($userId === null) {
            return SystemEvidence::unavailable(
                'annual_programme_contribution',
                'Annual Programme contribution (audit-derived)',
                $staff->user_id === null
                    ? 'This staff member has no linked login, so contribution cannot be attributed.'
                    : 'This login is shared by more than one staff record, so contribution cannot be unambiguously attributed.',
            );
        }

        $count = AuditLog::whereIn('auditable_type', [AnnualProgramme::class, AnnualProgrammeItem::class])
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->count();

        return new SystemEvidence(
            key: 'annual_programme_contribution',
            label: 'Recorded Annual Programme edits (audit-derived)',
            availability: EvidenceAvailability::Available,
            numericValue: (float) $count,
            rangeStart: $start,
            rangeEnd: $end,
            note: 'Counts audited create/update actions attributed to this staff member specifically, not roster plan existence.',
        );
    }
}
