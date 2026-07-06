<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Active AI Provider
    |--------------------------------------------------------------------------
    |
    | The AI provider currently resolved by AIProviderFactory. Supported:
    | "placeholder" (default, no external calls) and "claude" (scaffold
    | only — throws AIProviderNotImplementedException until implemented).
    |
    */

    'default' => env('AI_PROVIDER', 'placeholder'),

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'placeholder' => [],

        'claude' => [
            'api_key' => env('CLAUDE_API_KEY'),
            'model' => env('CLAUDE_MODEL'),
        ],
    ],

];
