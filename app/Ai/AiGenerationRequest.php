<?php

namespace App\Ai;

/**
 * Provider-neutral input to a generation call. Assistant classes
 * (CommunicationAssistant and future ones) build this; AiProvider
 * implementations only ever see this, never a domain model, a Communication,
 * or anything vendor-specific.
 *
 * `systemInstructions` and `userContent` are kept separate deliberately --
 * `userContent` is where untrusted, user-authored text is delimited as DATA
 * (see CommunicationAssistant), and a provider adapter maps the two onto
 * whatever the vendor's own request shape calls "system" vs "user" content.
 */
final readonly class AiGenerationRequest
{
    public function __construct(
        public string $systemInstructions,
        public string $userContent,
        public float $temperature,
        public int $maxOutputTokens,
    ) {}
}
