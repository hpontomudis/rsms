<?php

namespace App\Evidence\Concerns;

use App\Models\ClassSubject;
use App\Models\Staff;
use Illuminate\Support\Carbon;

/**
 * Which teaching assignments this staff member held for any part of the
 * evaluation period -- the same overlap rule Prosem/ReportCard use elsewhere,
 * never merely "assignments still open today".
 */
trait ResolvesOverlappingAssignments
{
    private function assignmentsOverlapping(Staff $staff, Carbon $start, Carbon $end)
    {
        return ClassSubject::where('staff_id', $staff->id)
            ->whereDate('started_on', '<=', $end)
            ->where(fn ($q) => $q->whereNull('ended_on')->orWhereDate('ended_on', '>=', $start))
            ->with('subject', 'schoolClass', 'teachingGroup')
            ->get();
    }
}
