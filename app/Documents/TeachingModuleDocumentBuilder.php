<?php

namespace App\Documents;

use App\Models\TeachingModule;

/**
 * Modul Ajar / Teaching Module -- the plan for HOW.
 *
 * Planned objectives and the scheduled slots this design serves. Nothing about
 * the schedule is copied: the week labels are read from the Prosem slots at
 * render time.
 */
class TeachingModuleDocumentBuilder
{
    public function build(TeachingModule $module): PlanningDocument
    {
        $vocabulary = $module->curriculumScope->curriculum->vocabulary();

        $objectives = $module->objectives()->map(fn ($objective, $index) => [
            (string) ($index + 1),
            $objective->code,
            $objective->objective_text,
        ])->values()->all();

        $slots = $module->slotLinks()
            ->with('semesterProgrammeItem.semesterProgramme.academicPeriod')
            ->get()
            ->map(function ($link) {
                $slot = $link->semesterProgrammeItem;

                return [
                    $slot->semesterProgramme->academicPeriod->name,
                    $slot->week_label ?? 'Slot '.$slot->position,
                    $slot->planned_lesson_periods !== null ? $slot->planned_lesson_periods.' JP' : '—',
                ];
            })->all();

        $sections = collect([
            new PlanningDocumentSection(
                heading: $vocabulary['objectives'],
                columns: ['No', 'Kode', $vocabulary['objective']],
                rows: $objectives,
                emptyMessage: 'Belum ada tujuan pembelajaran.',
            ),
        ]);

        foreach ([
            'planned_activity' => 'Kegiatan Pembelajaran / Planned activity',
            'teaching_strategy' => 'Metode dan Pendekatan / Method and approach',
            'resources' => 'Media dan Sumber Belajar / Resources',
            'differentiation' => 'Diferensiasi / Differentiation',
            'planned_assessment' => 'Rencana Asesmen / Planned assessment',
        ] as $field => $heading) {
            if ($module->$field) {
                $sections->push(new PlanningDocumentSection(heading: $heading, body: $module->$field));
            }
        }

        if ($slots !== []) {
            $sections->push(new PlanningDocumentSection(
                heading: 'Direncanakan untuk / Planned for',
                columns: ['Periode', 'Minggu', 'Alokasi'],
                rows: $slots,
            ));
        }

        return new PlanningDocument(
            title: $vocabulary['module'],
            subtitle: $module->title,
            meta: array_filter([
                'Kelas / Roster' => $module->rosterName().' ('.$module->rosterLabel().')',
                'Mata Pelajaran' => $module->subject->name,
                'Kurikulum' => $module->curriculumScope->curriculum->name.' — '.$module->curriculumScope->displayName(),
                'Topik' => $module->topic,
                'Guru / Teacher' => $module->classSubject->teacher?->fullName(),
                'Status' => ucfirst($module->status),
            ], fn ($v) => $v !== null && $v !== ''),
            sections: $sections,
            signatories: [
                ['title' => 'Guru Mata Pelajaran / Subject Teacher', 'name' => $module->classSubject->teacher?->fullName() ?? ''],
                ['title' => (string) config('school.principal_title'), 'name' => (string) config('school.principal_name')],
            ],
        );
    }
}
