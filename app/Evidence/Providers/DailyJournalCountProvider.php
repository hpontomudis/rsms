<?php

namespace App\Evidence\Providers;

use App\Evidence\Contracts\EvidenceProvider;
use App\Evidence\EvidenceAvailability;
use App\Evidence\SystemEvidence;
use App\Models\DailyJournal;
use App\Models\Staff;
use Illuminate\Support\Carbon;

/**
 * Administrative completeness -- writing the record is the ASSIGNMENT
 * holder's responsibility, whoever actually taught the session. See
 * JournalConductedCountProvider for the distinct "who actually taught"
 * question. Keeping these separate is the whole point: collapsing them would
 * hide a substitute's contribution or misattribute it to the assignment's
 * teacher.
 */
class DailyJournalCountProvider implements EvidenceProvider
{
    public function compute(Staff $staff, Carbon $start, Carbon $end): SystemEvidence
    {
        $count = DailyJournal::whereHas('classSubject', fn ($q) => $q->where('staff_id', $staff->id))
            ->whereBetween('journal_date', [$start->toDateString(), $end->toDateString()])
            ->count();

        return new SystemEvidence(
            key: 'daily_journal_count',
            label: 'Daily Journals recorded (assignment responsibility)',
            availability: EvidenceAvailability::Available,
            numericValue: (float) $count,
            rangeStart: $start,
            rangeEnd: $end,
            note: 'Administrative completeness for this staff member\'s assignments, regardless of who actually conducted each session.',
        );
    }
}
