<?php

namespace App\Documents;

use App\Models\AnnualProgramme;

/**
 * Program Tahunan / Annual Programme, rendered from the canonical record.
 *
 * Grouped by academic period, because that is the question a Prota exists to
 * answer. Nothing is copied: the objective text is read from the curriculum at
 * render time, and the JP budgets are the programme's own.
 */
class AnnualProgrammeDocumentBuilder
{
    public function build(AnnualProgramme $programme): PlanningDocument
    {
        $vocabulary = $programme->curriculumScope->curriculum->vocabulary();

        $items = $programme->items()
            ->with(['learningPathwayItem.learningObjective', 'academicPeriod', 'semesterItems'])
            ->get()
            ->sortBy(fn ($item) => [$item->academicPeriod->sequence, $item->learningPathwayItem->position]);

        $sections = $programme->academicYear->periods->map(function ($period) use ($items) {
            $rows = $items->where('academic_period_id', $period->id)->values()
                ->map(fn ($item, $index) => [
                    (string) ($index + 1),
                    $item->learningObjective()?->code,
                    $item->learningObjective()?->objective_text,
                    $item->planned_lesson_periods !== null ? $item->planned_lesson_periods.' JP' : '—',
                    $item->notes,
                ])->all();

            return new PlanningDocumentSection(
                heading: $period->name,
                columns: ['No', 'Kode', 'Tujuan Pembelajaran', 'Alokasi', 'Catatan'],
                rows: $rows,
                emptyMessage: 'Belum ada alokasi / nothing allocated.',
            );
        });

        return new PlanningDocument(
            title: $vocabulary['annual'],
            subtitle: $programme->title,
            meta: [
                'Kelas / Roster' => $programme->rosterName().' ('.$programme->rosterLabel().')',
                'Mata Pelajaran' => $programme->subject->name,
                'Tahun Ajaran' => $programme->academicYear->name,
                'Kurikulum' => $programme->curriculumScope->curriculum->name.' — '.$programme->curriculumScope->displayName(),
                $vocabulary['pathway'] => $programme->learningPathway->title,
                'Status' => ucfirst($programme->status),
            ],
            sections: $sections,
            signatories: [
                ['title' => 'Guru Mata Pelajaran / Subject Teacher', 'name' => ''],
                ['title' => (string) config('school.principal_title'), 'name' => (string) config('school.principal_name')],
            ],
        );
    }
}
