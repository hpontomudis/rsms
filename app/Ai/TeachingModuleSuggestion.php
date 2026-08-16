<?php

namespace App\Ai;

/**
 * Structured output for the Teaching Module planning assistant -- exactly
 * five nullable fields, mirroring TeachingModule::PLAN_FIELDS minus `title`
 * and `topic` (those stay short, teacher-authored anchors, never AI output).
 * No field here is capable of representing a Learning Objective link, a
 * Semester Programme link, curriculum/subject/roster identity, or status:
 * this is the DTO half of the "structurally unrepresentable" firewall the
 * V9A-4 architecture review required (the other half is TeachingModuleAssistant
 * never holding a reference to TeachingModuleService).
 */
final readonly class TeachingModuleSuggestion
{
    public function __construct(
        public ?string $plannedActivity,
        public ?string $teachingStrategy,
        public ?string $resources,
        public ?string $differentiation,
        public ?string $plannedAssessment,
    ) {}

    public function isEmpty(): bool
    {
        return $this->plannedActivity === null
            && $this->teachingStrategy === null
            && $this->resources === null
            && $this->differentiation === null
            && $this->plannedAssessment === null;
    }
}
