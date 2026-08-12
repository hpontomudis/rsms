<?php

namespace App\Documents;

use Illuminate\Support\Collection;

/**
 * Everything a printed report card shows, and nothing about where it came from.
 *
 * THE POINT OF THIS CLASS. A live preview is built from ReportCardBuilder
 * against current data; an issued record is built from academic_records and
 * academic_record_subjects and nothing else. The template must not be able to
 * tell the difference, and must never reach past this object to a model --
 * otherwise a published document would quietly start showing a renamed subject
 * or a corrected score, which is the exact failure publication exists to
 * prevent.
 *
 * Plain readonly values. No relations, no lazy loading, no queries.
 */
class ReportCardDocument
{
    /**
     * @param  array<int, string>  $columns  column headings, in print order
     * @param  Collection<int, ReportCardDocumentRow>  $rows
     * @param  array<int, array{name: string, title: string}>  $signatories
     */
    public function __construct(
        public readonly string $title,
        public readonly bool $isPreview,
        public readonly string $studentName,
        public readonly ?string $studentNumber,
        public readonly ?string $className,
        public readonly ?string $gradeName,
        public readonly string $academicYearName,
        public readonly ?string $periodName,
        public readonly array $columns,
        public readonly Collection $rows,
        public readonly bool $showOverallColumn,
        public readonly ?int $overallAverage,
        public readonly ?string $homeroomComment,
        public readonly array $signatories,
        public readonly string $schoolName,
        public readonly ?string $schoolLine2,
        public readonly ?string $schoolAddress,
        public readonly ?string $issuedOn = null,
        public readonly ?string $statusLabel = null,
    ) {}

    /** Header facts, in the order a rapor prints them. */
    public function meta(): array
    {
        return array_filter([
            'Nama / Name' => $this->studentName,
            'NIS / Number' => $this->studentNumber,
            'Kelas / Class' => $this->className,
            'Jenjang / Grade' => $this->gradeName,
            'Tahun Ajaran / Year' => $this->academicYearName,
            'Periode / Period' => $this->periodName,
            'Diterbitkan / Issued' => $this->issuedOn,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
