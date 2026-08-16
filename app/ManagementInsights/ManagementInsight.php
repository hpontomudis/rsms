<?php

namespace App\ManagementInsights;

/**
 * A single deterministic management fact. Immutable, computed live per
 * request (no snapshot table, no cache -- see V9A-5 architecture review
 * §22). This is the CANONICAL rendered value: the dashboard reads `count`
 * directly from this DTO, not from any AI narrative that may later paraphrase
 * it (see `ManagementNarrativeAssistant` -- AI output never becomes the
 * displayed count).
 *
 * `reliability` states are exactly three:
 *   - 'reliable'    fact can be computed confidently from current schema
 *   - 'limited'     fact can be computed but has a documented, bounded
 *                   limitation callers should be aware of
 *   - 'unavailable' fact cannot honestly be computed
 *
 * CRITICAL: when reliability is 'unavailable', `count` MUST be null, never
 * zero. The absence of evidence is not evidence of absence -- returning 0
 * for a fact we cannot compute would silently claim "no issue" when the
 * truth is "we don't know." This distinction is enforced at both the DTO
 * layer (this constructor) and again in the AI system prompt.
 *
 * `severity` is 'info' or 'attention' only -- no `urgent`/`critical`.
 * Providers own the rule; see each provider's docblock. Deliberately NOT a
 * global `count > 0 => attention` rule: a non-zero Draft Journal count is
 * normal workflow state (teachers draft before finalizing), while a
 * non-zero missing-Semester-Programme count is clearly actionable.
 *
 * `sourceIds` and `sourceType` exist for deterministic dashboard drill-down
 * only -- they are ALWAYS stripped before the DTO reaches the AI narrative
 * layer (see `ManagementNarrativeContextBuilder`).
 */
final readonly class ManagementInsight
{
    public const RELIABILITY_RELIABLE = 'reliable';

    public const RELIABILITY_LIMITED = 'limited';

    public const RELIABILITY_UNAVAILABLE = 'unavailable';

    public const SEVERITY_INFO = 'info';

    public const SEVERITY_ATTENTION = 'attention';

    public function __construct(
        public string $key,
        public string $category,
        public string $severity,
        public string $title,
        public string $description,
        public ?int $count,
        public string $reliability,
        public ?string $reliabilityNote = null,
        public ?string $sourceType = null,
        /** @var array<int, int> */
        public array $sourceIds = [],
        public ?string $actionRouteName = null,
        /** @var array<string, mixed> */
        public array $actionRouteParams = [],
    ) {
        if ($reliability === self::RELIABILITY_UNAVAILABLE && $count !== null) {
            throw new \InvalidArgumentException(
                "A ManagementInsight with reliability='unavailable' must have count=null (\"unknown != zero\"). Got count={$count} for key '{$key}'."
            );
        }
    }
}
