<?php

namespace App\ManagementInsights;

/**
 * Strips a list of already-computed `ManagementInsight` DTOs down to the
 * V9A-5 AI context allowlist (§24 of the architecture review):
 *
 *   key, category, title, description, count, reliability
 *
 * Explicitly dropped, never passed to the AI: severity (deterministic
 * dashboard shows it; the AI should not import a pre-computed judgment
 * word), sourceType/sourceIds (raw record IDs have no business reaching
 * the model), actionRouteName/actionRouteParams (no URL construction),
 * reliabilityNote (free text -- the reliability *state* is enough for
 * hallucination control).
 *
 * Structural firewall: this builder is the ONLY code path that produces
 * the payload passed to the assistant; assistants have no Eloquent access.
 * If a future insight adds a sensitive field, it must be added to the
 * allowlist here explicitly, not silently propagated.
 */
class ManagementNarrativeContextBuilder
{
    /**
     * @param  list<ManagementInsight>  $insights
     * @return list<array<string, mixed>>
     */
    public function build(array $insights): array
    {
        return array_values(array_map(fn (ManagementInsight $i) => [
            'key' => $i->key,
            'category' => $i->category,
            'title' => $i->title,
            'description' => $i->description,
            'count' => $i->count,
            'reliability' => $i->reliability,
        ], $insights));
    }
}
