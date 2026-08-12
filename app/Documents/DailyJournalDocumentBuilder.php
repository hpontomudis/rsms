<?php

namespace App\Documents;

use App\Models\DailyJournal;

/**
 * Jurnal Harian Guru / Daily Teaching Journal -- the record of what happened.
 *
 * Prints the ACTUAL objectives, which are the journal's own and may differ from
 * the module's planned ones. Assessments are listed as activity only; no mark
 * appears anywhere, because marks live in assessment_results.
 */
class DailyJournalDocumentBuilder
{
    public function build(DailyJournal $journal): PlanningDocument
    {
        $vocabulary = $journal->curriculumScope->curriculum->vocabulary();

        $objectives = $journal->objectives()->map(fn ($objective, $index) => [
            (string) ($index + 1),
            $objective->code,
            $objective->objective_text,
        ])->values()->all();

        $sections = collect([
            new PlanningDocumentSection(
                heading: $vocabulary['objectives'].' yang tercapai / actually covered',
                columns: ['No', 'Kode', $vocabulary['objective']],
                rows: $objectives,
                emptyMessage: 'Tidak ada tujuan pembelajaran tercatat.',
            ),
            new PlanningDocumentSection(
                heading: 'Kegiatan / What actually happened',
                body: $journal->actual_activity,
            ),
        ]);

        foreach ([
            'reflection' => 'Refleksi / Reflection',
            'follow_up' => 'Tindak Lanjut / Follow-up',
        ] as $field => $heading) {
            if ($journal->$field) {
                $sections->push(new PlanningDocumentSection(heading: $heading, body: $journal->$field));
            }
        }

        $assessments = $journal->assessmentLinks()->with('assessment')->get()
            ->map(fn ($link) => [$link->assessment->name, $link->assessment->assessment_date?->format('d M Y')])
            ->all();

        if ($assessments !== []) {
            $sections->push(new PlanningDocumentSection(
                heading: 'Asesmen yang digunakan / Assessment used',
                columns: ['Asesmen', 'Tanggal'],
                rows: $assessments,
            ));
        }

        $slot = $journal->semesterProgrammeItem;

        return new PlanningDocument(
            title: $vocabulary['journal'],
            subtitle: $journal->topic,
            meta: array_filter([
                'Tanggal / Date' => $journal->journal_date->format('d M Y'),
                'Kelas / Roster' => $journal->rosterName().' ('.$journal->rosterLabel().')',
                'Mata Pelajaran' => $journal->subject->name,
                'Periode' => $journal->academicPeriod->name,
                'Pertemuan' => $journal->meeting_number,
                'Alokasi / Actual' => $journal->actual_lesson_periods !== null ? $journal->actual_lesson_periods.' JP' : null,
                'Pengajar / Conducted by' => $journal->conductedBy?->fullName()
                    .($journal->wasSubstituted() ? ' (pengganti / substitute for '.$journal->classSubject->teacher?->fullName().')' : ''),
                $vocabulary['module'] => $journal->teachingModule?->title,
                'Slot ' . $vocabulary['semester'] => $slot ? ($slot->week_label ?? 'Slot '.$slot->position) : null,
                'Status' => ucfirst($journal->status),
            ], fn ($v) => $v !== null && $v !== ''),
            sections: $sections,
            signatories: [
                ['title' => 'Pengajar / Conducted by', 'name' => $journal->conductedBy?->fullName() ?? ''],
                ['title' => (string) config('school.principal_title'), 'name' => (string) config('school.principal_name')],
            ],
        );
    }
}
