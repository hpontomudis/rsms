<?php

namespace App\Ai;

use App\Models\DailyJournal;

/**
 * Takes an ALREADY-AUTHORIZED DailyJournal model and produces the small,
 * explicit DailyJournalContext DTO -- the "explicit builders over generic
 * engines" pattern this codebase already uses for EvidenceService's 8
 * providers and AudienceResolver's 12 resolvers.
 *
 * Never accepts a bare ID and never independently queries broader records
 * (no Student query, no other Journal, no Assessment, no Teaching Module).
 * Everything it reads comes from the model instance it was handed and that
 * model's own already-loaded relations.
 */
class DailyJournalContextBuilder
{
    public function build(DailyJournal $journal, string $teacherNotes): DailyJournalContext
    {
        return new DailyJournalContext(
            subjectName: $journal->subject->name,
            rosterName: $journal->rosterName(),
            topic: $journal->topic,
            objectiveTitles: $journal->objectives()->pluck('objective_text')->all(),
            existingReflection: $journal->reflection,
            existingFollowUp: $journal->follow_up,
            teacherNotes: $teacherNotes,
        );
    }
}
