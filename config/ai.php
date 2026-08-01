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
    | official classification_thresholds table) and "claude" (scaffold
    | only — throws AIProviderNotImplementedException until implemented).
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
        ],
    ],

];
