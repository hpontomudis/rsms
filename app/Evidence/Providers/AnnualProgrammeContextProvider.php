<?php

namespace App\Evidence\Providers;

use App\Evidence\Concerns\ResolvesOverlappingAssignments;
use App\Evidence\Contracts\EvidenceProvider;
use App\Evidence\EvidenceAvailability;
use App\Evidence\SystemEvidence;
use App\Models\AnnualProgramme;
use App\Models\Staff;
use Illuminate\Support\Carbon;

/**
 * CONTEXT, not authorship. Prota is anchored to the roster and survives
 * teacher succession, so its existence says nothing about who wrote it --
 * only that the roster this staff member taught has (or lacks) a plan.
 * See AnnualProgrammeContributionProvider for individually attributable
 * evidence, and keep the two apart: never phrase this as "this teacher
 * created the Prota."
 */
class AnnualProgrammeContextProvider implements EvidenceProvider
{
    use ResolvesOverlappingAssignments;

    public function compute(Staff $staff, Carbon $start, Carbon $end): SystemEvidence
    {
        $assignments = $this->assignmentsOverlapping($staff, $start, $end);

        if ($assignments->isEmpty()) {
            return SystemEvidence::unavailable(
                'annual_programme_context',
                'Annual Programme context (roster, not authorship)',
                'No teaching assignment overlaps this evaluation period.',
            );
        }

        $lines = [];
        $activeCount = 0;

        foreach ($assignments as $assignment) {
            $programme = AnnualProgramme::where('subject_id', $assignment->subject_id)
                ->where('academic_year_id', $assignment->academicYear()->id)
                ->when($assignment->isClassBacked(),
                    fn ($q) => $q->where('class_id', $assignment->class_id),
                    fn ($q) => $q->where('teaching_group_id', $assignment->teaching_group_id))
                ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")
                ->first();

            $label = "{$assignment->displayName()} {$assignment->subject->name}";

            if ($programme) {
                $lines[] = "{$label}: {$programme->status} Annual Programme";

                if ($programme->isActive()) {
                    $activeCount++;
                }
            } else {
                $lines[] = "{$label}: no Annual Programme";
            }
        }

        return new SystemEvidence(
            key: 'annual_programme_context',
            label: 'Annual Programme context for assigned rosters',
            availability: EvidenceAvailability::Available,
            numericValue: (float) $activeCount,
            textValue: implode('; ', $lines),
            rangeStart: $start,
            rangeEnd: $end,
            note: 'Roster planning context, not individual authorship -- the plan survives a teacher handover.',
        );
    }
}
