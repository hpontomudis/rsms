<?php

namespace App\Evidence\Providers;

use App\Evidence\Contracts\EvidenceProvider;
use App\Evidence\EvidenceAvailability;
use App\Evidence\SystemEvidence;
use App\Models\Staff;
use App\Models\TeachingModule;
use Illuminate\Support\Carbon;

/**
 * Modules are assignment-owned (class_subject.staff_id), and that ownership
 * never moves at a handover -- Phase 5F's guarantee. Safe individual evidence.
 *
 * This is a count of administrative output, not a measure of quality: a
 * module count says something got written, never how well it was written.
 */
class TeachingModuleCountProvider implements EvidenceProvider
{
    public function compute(Staff $staff, Carbon $start, Carbon $end): SystemEvidence
    {
        $count = TeachingModule::whereHas('classSubject', fn ($q) => $q->where('staff_id', $staff->id))
            ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->count();

        return new SystemEvidence(
            key: 'teaching_module_count',
            label: 'Teaching Modules written',
            availability: EvidenceAvailability::Available,
            numericValue: (float) $count,
            rangeStart: $start,
            rangeEnd: $end,
            note: 'Counts modules written, not their instructional quality.',
        );
    }
}
