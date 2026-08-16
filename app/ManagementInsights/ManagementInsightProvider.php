<?php

namespace App\ManagementInsights;

/**
 * The read-side counterpart to EvidenceRegistry's write-side "explicit
 * builders over generic engines" pattern (see PROJECT_STATUS.md's
 * Architectural Decisions). Each provider owns exactly one insight key,
 * documents its own reliability/severity rule in its class docblock, and
 * queries live -- no snapshot table, no cache, no shared query engine.
 *
 * Providers MUST NOT call AcademicYear::current() (its underlying
 * `is_current` flag has no DB uniqueness guarantee, per Foundation
 * Technical Debt); scope is always the explicit `ManagementInsightScope`
 * DTO passed in.
 */
interface ManagementInsightProvider
{
    /**
     * The registry key -- must match `ManagementInsightRegistry`'s mapping.
     * Doubles as the insight's `key` field on the emitted DTO.
     */
    public function key(): string;

    /**
     * Compute and return exactly one insight for the given scope. Providers
     * that would have "nothing to report" return an insight with count = 0
     * (reliability = 'reliable'), NOT null -- null is reserved for the
     * genuinely unknown case (reliability = 'unavailable').
     */
    public function build(ManagementInsightScope $scope): ManagementInsight;
}
