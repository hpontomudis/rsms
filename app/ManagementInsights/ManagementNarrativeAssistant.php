<?php

namespace App\ManagementInsights;

use App\Ai\AiGenerationRequest;
use App\Ai\AiGenerationResult;
use App\Ai\AiGenerationService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use JsonException;

/**
 * Narrates a list of already-computed, deterministic ManagementInsight
 * DTOs. Has no reference to ManagementInsightService, no Eloquent access,
 * no query builder, no way to reach a single row of any domain model --
 * matches the discipline every prior V9A assistant establishes.
 *
 * Authorization is checked HERE: both `management-insights.view` (the
 * dashboard gate) AND `ai.use` (the AI kill-switch). A user with only the
 * view permission gets the deterministic dashboard without an AI button;
 * a user with only ai.use gets nothing at all.
 *
 * accepted_at semantics: this assistant has no Apply step, and
 * `AiGenerationService::markAccepted()` is never called for this use case.
 * `ai_generations.accepted_at` stays permanently NULL for
 * `management_insight_summary` -- reusing it to mean "the summary was
 * read" would drift from its established meaning ("at least one suggested
 * field was applied to unsaved form state"), so it's simply left unset.
 */
class ManagementNarrativeAssistant
{
    public const USE_CASE = 'management_insight_summary';

    public const PROMPT_VERSION = 'v1';

    public function __construct(
        private readonly AiGenerationService $generations,
        private readonly ManagementNarrativeContextBuilder $contextBuilder,
    ) {}

    /**
     * @param  list<ManagementInsight>  $insights
     */
    public function suggest(User $actor, array $insights): ManagementNarrativeAssistantResult
    {
        $this->authorize($actor);

        $context = $this->contextBuilder->build($insights);

        $request = new AiGenerationRequest(
            systemInstructions: $this->systemInstructions(),
            userContent: $this->delimitedContent($context),
            temperature: (float) config('ai.assistants.management_insight_summary.temperature', config('ai.default_temperature')),
            maxOutputTokens: (int) config('ai.assistants.management_insight_summary.max_output_tokens', config('ai.default_max_output_tokens')),
        );

        $result = $this->generations->generate($actor, self::USE_CASE, self::PROMPT_VERSION, $request);

        return $this->interpret($result);
    }

    private function authorize(User $actor): void
    {
        if (! $actor->can('management-insights.view')) {
            throw new AuthorizationException('This account is not permitted to view Management Insights.');
        }

        if (! $actor->can('ai.use')) {
            throw new AuthorizationException('This account is not permitted to use AI assistance.');
        }
    }

    private function interpret(AiGenerationResult $result): ManagementNarrativeAssistantResult
    {
        if ($result->status === 'rate_limited') {
            return ManagementNarrativeAssistantResult::rateLimited();
        }

        if (! $result->isSuccess()) {
            return ManagementNarrativeAssistantResult::failed();
        }

        $suggestion = $this->parse($result->text);

        if ($suggestion === null || $suggestion->isEmpty()) {
            return ManagementNarrativeAssistantResult::unusable($result->generationId);
        }

        return ManagementNarrativeAssistantResult::success($suggestion, $result->generationId);
    }

    private function parse(?string $text): ?ManagementNarrativeSuggestion
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        try {
            $decoded = json_decode($text, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        $summary = is_string($decoded['summary'] ?? null) && trim($decoded['summary']) !== '' ? $decoded['summary'] : null;

        $rawPoints = $decoded['attention_points'] ?? [];
        $attentionPoints = [];
        if (is_array($rawPoints)) {
            foreach ($rawPoints as $point) {
                if (is_string($point) && trim($point) !== '') {
                    $attentionPoints[] = $point;
                }
            }
        }

        return new ManagementNarrativeSuggestion(
            summary: $summary,
            attentionPoints: $attentionPoints,
        );
    }

    private function systemInstructions(): string
    {
        return <<<TEXT
        You are a school-management-reporting assistant inside Rahai School's internal RSMS. You will be given a list of already-verified, deterministically-computed management insights inside <insights> tags. Your only job is to narrate those insights briefly and neutrally.

        Rules, all mandatory:
        - Use only the supplied insights. Do not introduce facts, records, causes, or explanations that are not present in them. Do not name any individual person -- no student, staff, teacher, or guardian names ever appear in your output.
        - Preserve every "count" value exactly as given. Do not round, estimate, or paraphrase a number into a different one.
        - "reliability" has three possible values: "reliable" (trustworthy), "limited" (trustworthy with a bounded caveat), or "unavailable" (the fact could not be computed). For an insight with reliability = "unavailable", the count is null and you MUST NOT describe it as zero, none, or absent. Say instead that the fact was not reliably computable for this scope. UNKNOWN is not ZERO.
        - Do not infer teaching quality, student ability, staff performance, or blame from any count. A draft record is a workflow state, not a failure. Missing planning may indicate many things; do not speculate about causes.
        - Do not invent or reinterpret an insight's severity, category, or title.
        - Do not suggest actions that would mutate school data (publish, delete, finalize, assign, discipline, etc). At most, phrase follow-ups as "may be worth reviewing."
        - Respond with ONLY a single JSON object, no other text, no Markdown code fences, in exactly this shape: {"summary": "...", "attention_points": ["...", "..."]}. `summary` is a short paragraph (2-4 sentences) or an empty string. `attention_points` is a list of short strings (each one sentence), or an empty list. Nothing else.
        TEXT;
    }

    /**
     * @param  list<array<string, mixed>>  $context
     */
    private function delimitedContent(array $context): string
    {
        // The context is already sanitised by ManagementNarrativeContextBuilder;
        // JSON-encoding it here is safer than string concatenation, which
        // would let injected characters in a title/description break out of
        // the delimited block. json_encode-produced JSON inside <insights>
        // stays valid JSON no matter what the field values contain.
        return "<insights>\n".json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n</insights>";
    }
}
