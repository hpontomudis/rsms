<?php

namespace App\Evidence\Providers;

use App\Evidence\Concerns\ResolvesOverlappingAssignments;
use App\Evidence\Contracts\EvidenceProvider;
use App\Evidence\EvidenceAvailability;
use App\Evidence\SystemEvidence;
use App\Models\AnnualProgramme;
use App\Models\SemesterProgramme;
use App\Models\Staff;
use Illuminate\Support\Carbon;

/**
 * Context, exactly like AnnualProgrammeContextProvider: whether the roster's
 * semester schedule exists for the periods overlapping this evaluation, not
 * who scheduled it.
 */
class SemesterProgrammeContextProvider implements EvidenceProvider
{
    use ResolvesOverlappingAssignments;

    public function compute(Staff $staff, Carbon $start, Carbon $end): SystemEvidence
    {
        $assignments = $this->assignmentsOverlapping($staff, $start, $end);

        if ($assignments->isEmpty()) {
            return SystemEvidence::unavailable(
                'semester_programme_context',
                'Semester Programme context (roster, not authorship)',
                'No teaching assignment overlaps this evaluation period.',
            );
        }

        $lines = [];
        $activeCount = 0;

        foreach ($assignments as $assignment) {
            $annual = AnnualProgramme::where('subject_id', $assignment->subject_id)
                ->where('academic_year_id', $assignment->academicYear()->id)
                ->when($assignment->isClassBacked(),
                    fn ($q) => $q->where('class_id', $assignment->class_id),
                    fn ($q) => $q->where('teaching_group_id', $assignment->teaching_group_id))
                ->first();

            $label = "{$assignment->displayName()} {$assignment->subject->name}";

            if (! $annual) {
                $lines[] = "{$label}: no Annual Programme to schedule against";

                continue;
            }

            $semesters = SemesterProgramme::where('annual_programme_id', $annual->id)
                ->whereHas('academicPeriod', fn ($q) => $q
                    ->whereDate('start_date', '<=', $end)
                    ->whereDate('end_date', '>=', $start))
                ->get();

            if ($semesters->isEmpty()) {
                $lines[] = "{$label}: no Semester Programme scheduled for this period";

                continue;
            }

            foreach ($semesters as $semester) {
                $lines[] = "{$label}: {$semester->status} Semester Programme";

                if ($semester->isActive()) {
                    $activeCount++;
                }
            }
        }

        return new SystemEvidence(
            key: 'semester_programme_context',
            label: 'Semester Programme context for assigned rosters',
            availability: EvidenceAvailability::Available,
            numericValue: (float) $activeCount,
            textValue: implode('; ', $lines),
            rangeStart: $start,
            rangeEnd: $end,
            note: 'Roster scheduling context, not individual authorship.',
        );
    }
}
