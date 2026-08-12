<?php

namespace App\Documents;

use App\Models\SemesterProgramme;

/**
 * Program Semester / Semester Programme.
 *
 * Slots in schedule order, including several for one objective -- weeks 3, 4
 * and 6 print as three lines, because that is what the plan says.
 */
class SemesterProgrammeDocumentBuilder
{
    public function build(SemesterProgramme $programme): PlanningDocument
    {
        $annual = $programme->annualProgramme;
        $vocabulary = $annual->curriculumScope->curriculum->vocabulary();

        $slots = $programme->items()
            ->with('annualProgrammeItem.learningPathwayItem.learningObjective')
            ->get();

        $rows = $slots->map(fn ($slot) => [
            (string) $slot->position,
            $slot->week_label,
            $slot->annualProgrammeItem->learningObjective()?->code,
            $slot->annualProgrammeItem->learningObjective()?->objective_text,
            $slot->planned_start_date
                ? $slot->planned_start_date->format('d M').($slot->planned_end_date ? ' – '.$slot->planned_end_date->format('d M') : '')
                : null,
            $slot->planned_lesson_periods !== null ? $slot->planned_lesson_periods.' JP' : '—',
            $slot->notes,
        ])->all();

        return new PlanningDocument(
            title: $vocabulary['semester'],
            subtitle: $annual->title,
            meta: [
                'Kelas / Roster' => $annual->rosterName().' ('.$annual->rosterLabel().')',
                'Mata Pelajaran' => $annual->subject->name,
                'Periode' => $programme->academicPeriod->name,
                'Tahun Ajaran' => $annual->academicYear->name,
                'Rentang' => $programme->academicPeriod->start_date->format('d M Y').' – '.$programme->academicPeriod->end_date->format('d M Y'),
                'Status' => ucfirst($programme->status),
            ],
            sections: collect([
                new PlanningDocumentSection(
                    heading: 'Jadwal / Schedule',
                    columns: ['No', 'Minggu', 'Kode', 'Tujuan Pembelajaran', 'Tanggal', 'Alokasi', 'Catatan'],
                    rows: $rows,
                    emptyMessage: 'Belum ada jadwal / nothing scheduled.',
                ),
            ]),
            signatories: [
                ['title' => 'Guru Mata Pelajaran / Subject Teacher', 'name' => ''],
                ['title' => (string) config('school.principal_title'), 'name' => (string) config('school.principal_name')],
            ],
        );
    }
}
