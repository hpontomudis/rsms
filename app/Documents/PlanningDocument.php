<?php

namespace App\Documents;

use Illuminate\Support\Collection;

/**
 * A printed planning document: ATP, Prota, Prosem, Modul Ajar or Jurnal Harian.
 *
 * NOTHING HERE IS SNAPSHOTTED. Unlike a report card, these render their
 * canonical record live and store nothing new -- each already carries whatever
 * historical protection it needs (a ready module is frozen, a finalized journal
 * is frozen, an archived plan is read-only). Copying them into a document table
 * would create a second version of facts that already have one.
 *
 * The consequence is deliberate and printed on the page: an ACTIVE Prota or
 * Prosem stays editable, so a printout is a picture of a moment. That is what
 * the "Dicetak / Printed" timestamp in the shared layout is for.
 */
class PlanningDocument
{
    /**
     * @param  array<string, string|null>  $meta
     * @param  Collection<int, PlanningDocumentSection>  $sections
     * @param  array<int, array{name: string, title: string}>  $signatories
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $subtitle,
        public readonly array $meta,
        public readonly Collection $sections,
        public readonly array $signatories = [],
    ) {}
}
