<?php

declare(strict_types=1);

namespace App\AI\Contracts;

use App\AI\DTOs\AIClassificationResult;
use App\AI\DTOs\AssessmentPayload;

/**
 * Contract for AI providers used by the AI Prediction Module.
 *
 * All providers follow the Strategy Pattern: AIService depends only on
 * this interface, never on a concrete provider, so the active provider
 * can be swapped via config('ai.provider') without touching controllers,
 * services, or the database schema.
 */
interface AIProviderInterface
{
    /**
     * Classify each DASS-21 subscale in the given assessment payload into
     * its official severity tier.
     */
    public function classify(AssessmentPayload $payload): AIClassificationResult;
}
