<?php

declare(strict_types=1);

namespace App\AI\Providers;

use App\AI\Contracts\AIProviderInterface;
use App\AI\DTOs\AssessmentPayload;

/**
 * Default AI provider for this phase.
 *
 * Performs no HTTP requests and no real prediction logic. It exists so
 * the AI integration seam (AIService -> AIProviderInterface) is fully
 * wired and exercised ahead of a real AI provider being configured.
 */
class PlaceholderAIProvider implements AIProviderInterface
{
    /**
     * @return array{mentalHealthStatus: string, riskLevel: string, interpretation: string, recommendation: string}
     */
    public function predict(AssessmentPayload $payload): array
    {
        return [
            'mentalHealthStatus' => 'Pending AI Analysis',
            'riskLevel' => '—',
            'interpretation' => 'AI Prediction Module not yet integrated.',
            'recommendation' => 'Waiting for external AI service.',
        ];
    }
}
