<?php

declare(strict_types=1);

namespace App\AI\Contracts;

use App\AI\DTOs\AssessmentPayload;

/**
 * Contract for AI providers used by the AI Prediction Module.
 *
 * All providers follow the Strategy Pattern: AIService depends only on
 * this interface, never on a concrete provider, so the active provider
 * can be swapped via config('ai.default') without touching controllers,
 * services, or the database schema.
 */
interface AIProviderInterface
{
    /**
     * Generate a mental health prediction for the given assessment payload.
     *
     * @return array{mentalHealthStatus: string, riskLevel: string, interpretation: string, recommendation: string}
     */
    public function predict(AssessmentPayload $payload): array;
}
