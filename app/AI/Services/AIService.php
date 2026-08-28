<?php

declare(strict_types=1);

namespace App\AI\Services;

use App\AI\Contracts\AIProviderInterface;
use App\AI\DTOs\AIClassificationResult;
use App\AI\DTOs\AssessmentPayload;

/**
 * Entry point controllers/services use to obtain an AI classification.
 * Depends only on AIProviderInterface via dependency injection; callers
 * must never instantiate an AI provider directly.
 */
class AIService
{
    public function __construct(private readonly AIProviderInterface $provider) {}

    public function classify(AssessmentPayload $payload): AIClassificationResult
    {
        return $this->provider->classify($payload);
    }
}
