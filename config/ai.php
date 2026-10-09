<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Active AI Provider
    |--------------------------------------------------------------------------
    |
    | The AI provider currently resolved by AIProviderFactory. Supported:
    | "rule_based" (default — classifies each DASS-21 subscale against the
    | official classification_thresholds table) and "claude" (sends scores
    | to the Claude Messages API, then cross-checks the reply against
    | RuleBasedDASSProvider before trusting it — see ClaudeAIProvider).
    |
    */

    'provider' => env('AI_PROVIDER', 'rule_based'),

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'rule_based' => [],

        'claude' => [
            'api_key' => env('CLAUDE_API_KEY'),
            'model' => env('CLAUDE_MODEL', 'claude-sonnet-5'),
            'api_url' => env('CLAUDE_API_URL', 'https://api.anthropic.com/v1/messages'),
            // Seconds. The total is capped at 20 in ClaudeAIProvider so the
            // request always gives up (and falls back to rule-based) well
            // before PHP's own 30-second limit, which on Windows counts the
            // time spent waiting on the network and can't be caught.
            'timeout' => env('CLAUDE_TIMEOUT', 12),
            'connect_timeout' => env('CLAUDE_CONNECT_TIMEOUT', 4),
        ],
    ],

];
