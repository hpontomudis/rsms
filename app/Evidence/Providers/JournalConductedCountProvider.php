<?php

namespace App\Evidence\Providers;

use App\Evidence\Contracts\EvidenceProvider;
use App\Evidence\EvidenceAvailability;
use App\Evidence\SystemEvidence;
use App\Models\DailyJournal;
use App\Models\Staff;
use Illuminate\Support\Carbon;

/**
 * Sessions this staff member actually taught, via conducted_by_staff_id --
 * independent of whose assignment the journal was written under. A
 * substitute's teaching activity belongs to the substitute.
 */
class JournalConductedCountProvider implements EvidenceProvider
{
    public function compute(Staff $staff, Carbon $start, Carbon $end): SystemEvidence
    {
        $count = DailyJournal::where('conducted_by_staff_id', $staff->id)
            ->whereBetween('journal_date', [$start->toDateString(), $end->toDateString()])
            ->count();

        return new SystemEvidence(
            key: 'journal_conducted_count',
            label: 'Sessions actually conducted',
            availability: EvidenceAvailability::Available,
            numericValue: (float) $count,
            rangeStart: $start,
            rangeEnd: $end,
            note: 'Counts sessions this person actually taught, including as a substitute on another teacher\'s assignment.',
        );
    }
}
