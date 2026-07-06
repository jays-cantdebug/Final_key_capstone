<?php

declare(strict_types=1);

namespace App\AI\Providers;

use App\AI\Contracts\AIProviderInterface;
use App\AI\DTOs\AssessmentPayload;
use App\AI\Exceptions\AIProviderNotImplementedException;

/**
 * Scaffold for a future Claude API-backed AI provider.
 *
 * Not implemented in this phase: no HTTP requests are made and no
 * external API is called. Configure AI_PROVIDER=claude in .env only
 * once a real implementation replaces this scaffold.
 */
class ClaudeAIProvider implements AIProviderInterface
{
    /**
     * @throws AIProviderNotImplementedException always, until implemented.
     */
    public function predict(AssessmentPayload $payload): array
    {
        throw new AIProviderNotImplementedException(
            'The Claude AI provider is not yet implemented. Set AI_PROVIDER=placeholder in .env until this provider is available.'
        );
    }
}
