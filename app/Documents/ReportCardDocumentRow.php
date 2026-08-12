<?php

namespace App\Documents;

/**
 * One subject line on a printed report card.
 *
 * `scores` is aligned to the document's `columns`: one entry per column, null
 * where there is no result. `overall` is only meaningful on the year overview,
 * where a subject has both period columns and a year figure.
 */
class ReportCardDocumentRow
{
    /** @param  array<int, int|null>  $scores */
    public function __construct(
        public readonly string $subjectName,
        public readonly array $scores,
        public readonly ?int $overall = null,
    ) {}
}
