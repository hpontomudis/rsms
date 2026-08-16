<?php

namespace App\Ai;

/**
 * The entire universe of facts TeachingModuleAssistant may see about one
 * module, per the V9A-4 architecture review's exact allowlist. Deliberately
 * NOT the TeachingModule model itself -- a caller that only ever gets this
 * DTO cannot accidentally read (or later add a read of) a field this phase
 * excludes: full CP/Learning Outcome text, adjacent objectives, ATP, Prota,
 * Prosem period/JP/week-label, Assessment identity, or any student/guardian
 * data.
 *
 * `proficiencyLabel` is populated only for a teaching-group-backed module
 * (e.g. "Green"), never for a class-backed one -- no invented mapping
 * between English Level and national Learning Phase.
 *
 * `existing*` fields mirror the module's current plan-field values (nullable),
 * enabling "improve this" grounding without a separate mode flag, the same
 * pattern DailyJournalContext already uses for existingReflection/
 * existingFollowUp. `existingTeacherNotes` is the module's own PERSISTED
 * `teacher_notes` field, included read-only; `teacherNotesForAi` is a
 * separate, transient, this-generation-only instruction -- see
 * TeachingModuleAssistant's docblock for why the two are kept apart.
 */
final readonly class TeachingModuleContext
{
    public function __construct(
        public string $subjectName,
        public string $rosterName,
        public ?string $proficiencyLabel,
        public string $title,
        public ?string $topic,
        /** @var string[] */
        public array $objectiveTexts,
        public ?string $existingPlannedActivity,
        public ?string $existingTeachingStrategy,
        public ?string $existingResources,
        public ?string $existingDifferentiation,
        public ?string $existingPlannedAssessment,
        public ?string $existingTeacherNotes,
        public string $teacherNotesForAi,
    ) {}
}
