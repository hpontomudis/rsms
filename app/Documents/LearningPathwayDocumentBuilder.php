<?php

namespace App\Documents;

use App\Models\LearningPathway;

/**
 * Alur Tujuan Pembelajaran / Learning Path.
 *
 * The ordered sequence, in `position` order -- the pathway's own teaching
 * sequence, which is deliberately independent of the objective library's
 * reference order. Both numbers print, so the distinction is visible rather
 * than assumed.
 */
class LearningPathwayDocumentBuilder
{
    public function build(LearningPathway $pathway): PlanningDocument
    {
        $scope = $pathway->curriculumScope;
        $vocabulary = $scope->curriculum->vocabulary();

        $rows = $pathway->items()
            ->with('learningObjective')
            ->get()
            ->sortBy('position')
            ->map(fn ($item) => [
                (string) $item->position,
                $item->learningObjective->code,
                $item->learningObjective->objective_text,
                (string) $item->learningObjective->reference_order,
                $item->notes,
            ])->values()->all();

        return new PlanningDocument(
            title: $vocabulary['pathway'],
            subtitle: $pathway->title,
            meta: array_filter([
                'Kurikulum' => $scope->curriculum->name,
                $vocabulary['basis'] => $scope->displayName(),
                'Mata Pelajaran' => $pathway->subject->name,
                'Kode' => $pathway->code,
                'Status' => ucfirst($pathway->status),
            ], fn ($v) => $v !== null && $v !== ''),
            sections: collect([
                new PlanningDocumentSection(
                    heading: 'Urutan / Sequence',
                    columns: ['Urutan', 'Kode', $vocabulary['objective'], 'No. Referensi', 'Catatan'],
                    rows: $rows,
                    emptyMessage: 'Belum ada tujuan pembelajaran / no objectives sequenced.',
                ),
            ]),
        );
    }
}
