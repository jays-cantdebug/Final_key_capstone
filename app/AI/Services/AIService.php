<?php

declare(strict_types=1);

namespace App\AI\Services;

use App\AI\Contracts\AIProviderInterface;
use App\AI\DTOs\AssessmentPayload;

/**
 * Entry point controllers use to obtain an AI prediction. Depends only on
 * AIProviderInterface via dependency injection; controllers must never
 * instantiate an AI provider directly.
 */
class AIService
{
    public function __construct(private readonly AIProviderInterface $provider)
    {
    }

    /**
     * @return array{mentalHealthStatus: string, riskLevel: string, interpretation: string, recommendation: string}
     */
    public function predict(AssessmentPayload $payload): array
    {
        return $this->provider->predict($payload);
    }
}
