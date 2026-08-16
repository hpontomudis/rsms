<?php

namespace App\Ai;

use App\Models\TeachingModule;

/**
 * Takes an ALREADY-AUTHORIZED TeachingModule model and produces the small,
 * explicit TeachingModuleContext DTO -- the "explicit builders over generic
 * engines" pattern this codebase already uses for EvidenceService's 8
 * providers, AudienceResolver's 12 resolvers, and DailyJournalContextBuilder.
 *
 * Never accepts a bare ID and never independently queries broader records --
 * no Student query, no other Module, no Assessment, no full CP/Prota/Prosem.
 * Everything it reads comes from the model instance it was handed and that
 * model's own already-loaded relations.
 */
class TeachingModuleContextBuilder
{
    public function build(TeachingModule $module, string $teacherNotesForAi): TeachingModuleContext
    {
        return new TeachingModuleContext(
            subjectName: $module->subject->name,
            rosterName: $module->rosterName(),
            proficiencyLabel: $module->isClassBacked() ? null : $module->teachingGroup?->englishLevel?->name,
            title: $module->title,
            topic: $module->topic,
            objectiveTexts: $module->objectives()->pluck('objective_text')->all(),
            existingPlannedActivity: $module->planned_activity,
            existingTeachingStrategy: $module->teaching_strategy,
            existingResources: $module->resources,
            existingDifferentiation: $module->differentiation,
            existingPlannedAssessment: $module->planned_assessment,
            existingTeacherNotes: $module->teacher_notes,
            teacherNotesForAi: $teacherNotesForAi,
        );
    }
}
