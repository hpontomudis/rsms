<?php

/**
 * AI is optional assistance, never the source of truth (V9A architecture
 * review). Nothing here lets a config value bypass that: it only controls
 * whether the feature runs, which provider/model answers, and how much a
 * single request may cost in time and tokens.
 *
 * Deliberately a config file, not a database table -- the same reasoning
 * `config/school.php` already documents: these values change by deploy, not
 * by a user clicking through a screen, and a settings table would need a
 * screen, a policy and a migration to serve a handful of values.
 *
 * The provider credential itself lives in `config/services.php`
 * (`services.anthropic.key`), Laravel's own conventional location for
 * third-party credentials -- never here, never in the database, never in a
 * settings UI.
 */
return [

    'enabled' => env('AI_ENABLED', true),

    'provider' => env('AI_PROVIDER', 'anthropic'),

    'model' => env('AI_MODEL', match (env('AI_PROVIDER', 'anthropic')) {
        'deepseek' => 'deepseek-chat',
        default => 'claude-sonnet-5',
    }),

    // Seconds. A synchronous request in V9A -- see AiGenerationService --
    // so this bounds how long a User's click can block the page.
    'timeout' => env('AI_TIMEOUT', 25),

    'default_temperature' => (float) env('AI_DEFAULT_TEMPERATURE', 0.5),

    'default_max_output_tokens' => (int) env('AI_DEFAULT_MAX_OUTPUT_TOKENS', 800),

    'rate_limit' => [
        'per_minute' => (int) env('AI_RATE_LIMIT_PER_MINUTE', 5),
        'per_day' => (int) env('AI_RATE_LIMIT_PER_DAY', 50),
    ],

    /*
     * Per-assistant overrides, only where a use case genuinely needs
     * different generation behaviour from the defaults above -- not a
     * config file per assistant, one array entry per assistant that departs
     * from the default.
     */
    'assistants' => [
        'communication_draft' => [
            'temperature' => (float) env('AI_COMMUNICATION_TEMPERATURE', 0.4),
            'max_output_tokens' => (int) env('AI_COMMUNICATION_MAX_OUTPUT_TOKENS', 600),
        ],
        'daily_journal_reflection' => [
            // Lower than the default: this assistant returns strict JSON and
            // benefits from less creative variance, not more.
            'temperature' => (float) env('AI_JOURNAL_TEMPERATURE', 0.3),
            'max_output_tokens' => (int) env('AI_JOURNAL_MAX_OUTPUT_TOKENS', 500),
        ],
        'teaching_module_plan' => [
            // Five fields rather than two, so a higher ceiling than the
            // journal assistant -- still a planning aid, not an essay.
            'temperature' => (float) env('AI_MODULE_TEMPERATURE', 0.3),
            'max_output_tokens' => (int) env('AI_MODULE_MAX_OUTPUT_TOKENS', 1400),
        ],
        'management_insight_summary' => [
            // Narrating a small list of pre-computed facts -- low
            // creative variance is a virtue, and the response is short
            // (one paragraph + a bulleted list of one-sentence items).
            'temperature' => (float) env('AI_INSIGHT_TEMPERATURE', 0.2),
            'max_output_tokens' => (int) env('AI_INSIGHT_MAX_OUTPUT_TOKENS', 600),
        ],
    ],

];
