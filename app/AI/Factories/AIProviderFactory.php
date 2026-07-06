<?php

declare(strict_types=1);

namespace App\AI\Factories;

use App\AI\Contracts\AIProviderInterface;
use App\AI\Providers\ClaudeAIProvider;
use App\AI\Providers\PlaceholderAIProvider;
use InvalidArgumentException;

/**
 * Resolves the active AI provider, as configured by config('ai.default').
 */
class AIProviderFactory
{
    public function make(): AIProviderInterface
    {
        return match (config('ai.default')) {
            'placeholder' => new PlaceholderAIProvider(),
            'claude' => new ClaudeAIProvider(),
            default => throw new InvalidArgumentException(
                sprintf('Unknown AI provider [%s] configured in config/ai.php.', config('ai.default'))
            ),
        };
    }
}
