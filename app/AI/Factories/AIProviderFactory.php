<?php

declare(strict_types=1);

namespace App\AI\Factories;

use App\AI\Contracts\AIProviderInterface;
use App\AI\Providers\ClaudeAIProvider;
use App\AI\Providers\RuleBasedDASSProvider;
use InvalidArgumentException;

/**
 * Resolves the active AI provider, as configured by config('ai.provider').
 */
class AIProviderFactory
{
    public function make(): AIProviderInterface
    {
        return match (config('ai.provider')) {
            'rule_based' => app(RuleBasedDASSProvider::class),
            'claude' => app(ClaudeAIProvider::class),
            default => throw new InvalidArgumentException(
                sprintf('Unknown AI provider [%s] configured in config/ai.php.', config('ai.provider'))
            ),
        };
    }
}
