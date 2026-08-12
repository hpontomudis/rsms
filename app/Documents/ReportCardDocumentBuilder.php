<?php

namespace App\Documents;

use App\Models\AcademicPeriod;
use App\Models\AcademicRecord;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Services\AcademicRecordService;
use App\Services\ReportCardBuilder;

/**
 * Turns either current data or an issued record into one printable contract.
 *
 * THREE ENTRY POINTS, ONE OUTPUT. The template cannot tell which produced it,
 * which is the whole reason this class exists: a live preview and an issued
 * document must never be allowed to drift into two layouts.
 *
 * fromPublished() is the one that carries the guarantee. It reads
 * academic_records and academic_record_subjects and NOTHING else -- no
 * subjects.name, no students.name, no academic_periods.name, no assessments.
 * A test proves that mutating every one of those leaves the rendered document
 * unchanged.
 */
class ReportCardDocumentBuilder
{
    public function __construct(
        private ReportCardBuilder $cards,
        private AcademicRecordService $records,
    ) {}

    /** The year overview: one column per period, plus a subject overall. */
    public function fromLiveYear(Student $student, AcademicYear $year): ReportCardDocument
    {
        $card = $this->cards->build($student, $year);
        $periods = $card['periods'];
        $class = $this->classContext($student, $year);

        $rows = $card['rows']->map(fn ($row) => new ReportCardDocumentRow(
            subjectName: $row->subject->name,
            scores: $periods->map(fn ($p) => $row->periodAverages[$p->id])->all(),
            overall: $row->overall,
        ));

        return new ReportCardDocument(
            title: 'Rekap Nilai / Academic Overview',
            isPreview: true,
            studentName: $student->fullName(),
            studentNumber: $student->student_number,
            className: $class?->name,
            gradeName: $class?->grade?->name,
            academicYearName: $year->name,
            periodName: null,
            columns: $periods->pluck('name')->all(),
            rows: $rows,
            showOverallColumn: true,
            overallAverage: $card['overallAverage'],
            homeroomComment: null,
            signatories: [],
            schoolName: config('school.name'),
            schoolLine2: $this->orNull(config('school.line2')),
            schoolAddress: $this->orNull(config('school.address')),
        );
    }

    /** One period, exactly as publication would issue it -- but live. */
    public function fromLivePeriod(Student $student, AcademicPeriod $period, ?AcademicRecord $draft = null): ReportCardDocument
    {
        $year = $period->academicYear;
        $card = $this->cards->buildForPeriod($student, $period);
        $class = $this->classContext($student, $year);

        $rows = $card['rows']->map(fn ($row) => new ReportCardDocumentRow(
            subjectName: $row->subject->name,
            scores: [$row->score],
        ));

        return new ReportCardDocument(
            title: 'Rapor / Report Card',
            isPreview: true,
            studentName: $student->fullName(),
            studentNumber: $student->student_number,
            className: $class?->name,
            gradeName: $class?->grade?->name,
            academicYearName: $year->name,
            periodName: $period->name,
            columns: ['Nilai / Score'],
            rows: $rows,
            showOverallColumn: false,
            overallAverage: $card['overallAverage'],
            homeroomComment: $draft?->homeroom_comment,
            signatories: $this->liveSignatories($class),
            schoolName: config('school.name'),
            schoolLine2: $this->orNull(config('school.line2')),
            schoolAddress: $this->orNull(config('school.address')),
        );
    }

    /**
     * The issued document. Snapshot columns only.
     *
     * Every value below comes from the record or its subject rows. Nothing here
     * touches a mutable label, which is what makes a 2026 rapor still print
     * 2026's subject names in 2029.
     */
    public function fromPublished(AcademicRecord $record): ReportCardDocument
    {
        $rows = $record->subjects->map(fn ($row) => new ReportCardDocumentRow(
            subjectName: $row->subject_name_snapshot,
            scores: [$row->score],
        ));

        return new ReportCardDocument(
            title: 'Rapor / Report Card',
            isPreview: false,
            studentName: $record->student_name_snapshot,
            studentNumber: $record->student_number_snapshot,
            className: $record->class_name_snapshot,
            gradeName: $record->grade_name_snapshot,
            academicYearName: $record->academic_year_name_snapshot,
            periodName: $record->period_name_snapshot,
            columns: ['Nilai / Score'],
            rows: $rows,
            showOverallColumn: false,
            overallAverage: $record->overall_average,
            homeroomComment: $record->homeroom_comment,
            signatories: [
                ['title' => 'Wali Kelas / Homeroom Teacher', 'name' => $record->homeroom_teacher_name_snapshot ?? ''],
                ['title' => $record->principal_title_snapshot ?? '', 'name' => $record->principal_name_snapshot ?? ''],
            ],
            schoolName: $record->school_name_snapshot ?? '',
            schoolLine2: $record->school_line2_snapshot,
            schoolAddress: $record->school_address_snapshot,
            issuedOn: $record->published_at?->format('d M Y'),
            statusLabel: $record->isSuperseded() ? 'Superseded / Digantikan' : null,
        );
    }

    /** Signing blocks for a live preview, from current config and class. */
    private function liveSignatories($class): array
    {
        $homeroom = null;

        try {
            $homeroom = $this->records->resolveHomeroomTeacherName($class);
        } catch (\Illuminate\Validation\ValidationException) {
            // Ambiguous homeroom teacher: a preview still prints, with a blank
            // line. Publication is where it must be resolved.
        }

        return [
            ['title' => 'Wali Kelas / Homeroom Teacher', 'name' => $homeroom ?? ''],
            ['title' => (string) config('school.principal_title'), 'name' => (string) config('school.principal_name')],
        ];
    }

    private function classContext(Student $student, AcademicYear $year)
    {
        try {
            return $this->records->resolveClass($student, $year);
        } catch (\Illuminate\Validation\ValidationException) {
            // Ambiguous class membership blocks PUBLICATION, but must not stop
            // a teacher looking at a preview.
            return null;
        }
    }

    private function orNull(?string $value): ?string
    {
        return ($value === null || trim($value) === '') ? null : $value;
    }
}
