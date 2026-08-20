<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // AI assistance provider credential (V9A). Server-side only -- never
    // read from the database, never exposed to Blade/JavaScript, never
    // logged. See config/ai.php for the non-secret provider/model settings.
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
    ],

    // Second AI provider credential, same rules as anthropic above: server-side
    // only, never logged, never exposed to Blade/JavaScript. Active only when
    // AI_PROVIDER=deepseek (config/ai.php).
    'deepseek' => [
        'key' => env('DEEPSEEK_API_KEY'),
    ],

    // One-time super_admin bootstrap credential (Pre-UAT Hardening P1).
    // Read ONLY by the `rsms:bootstrap-admin` Artisan command -- never by
    // DatabaseSeeder, never automatically. Absence of either value means no
    // admin is created, in any environment, including local dev: there is
    // no predictable fallback. Read through this config file (not a raw
    // env() call in the command) so it still resolves correctly after
    // `config:cache`, the same reason services.anthropic.key lives here
    // rather than in config/ai.php.
    'bootstrap_admin' => [
        'email' => env('BOOTSTRAP_ADMIN_EMAIL'),
        'password' => env('BOOTSTRAP_ADMIN_PASSWORD'),
    ],

];
