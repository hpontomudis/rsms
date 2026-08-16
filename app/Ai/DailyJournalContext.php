<?php

namespace App\Ai;

/**
 * The entire universe of facts DailyJournalAssistant may see about one
 * journal, per the V9A-3 architecture review's exact allowlist. Deliberately
 * NOT the DailyJournal model itself -- a caller that only ever gets this DTO
 * cannot accidentally read (or later add a read of) a field this phase
 * excludes, the same reasoning the review applied to the request DTO's
 * "structurally unrepresentable" mutation firewall, applied here to input
 * instead of output.
 *
 * No student/guardian name, no Assessment identity or result, no attendance,
 * no Teaching Module or Semester Programme detail, no journal_date, no JP,
 * no conductor identity.
 */
final readonly class DailyJournalContext
{
    public function __construct(
        public string $subjectName,
        public string $rosterName,
        public string $topic,
        /** @var string[] */
        public array $objectiveTitles,
        public ?string $existingReflection,
        public ?string $existingFollowUp,
        public string $teacherNotes,
    ) {}
}
