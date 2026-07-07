<?php

declare(strict_types=1);

namespace App\AI\Providers;

use App\AI\Contracts\AIProviderInterface;
use App\AI\DTOs\AIClassificationResult;
use App\AI\DTOs\AssessmentPayload;
use App\Models\ClassificationThreshold;
use RuntimeException;

/**
 * Default, functional AI provider for this phase.
 *
 * Classifies each DASS-21 subscale strictly by querying the locked,
 * official classification_thresholds table — no cutoffs are hardcoded
 * here. This is the "AI" the capstone paper requires as a real, working
 * classification component: a deterministic decision engine registered
 * behind the same AIProviderInterface/Strategy Pattern a future
 * ML/Claude-backed provider will use.
 */
class RuleBasedDASSProvider implements AIProviderInterface
{
    private const PROVIDER_NAME = 'rule_based';

    public function classify(AssessmentPayload $payload): AIClassificationResult
    {
        return new AIClassificationResult(
            depressionLevel: $this->classifySubscale(ClassificationThreshold::SUBSCALE_DEPRESSION, $payload->depressionFinalScore),
            anxietyLevel: $this->classifySubscale(ClassificationThreshold::SUBSCALE_ANXIETY, $payload->anxietyFinalScore),
            stressLevel: $this->classifySubscale(ClassificationThreshold::SUBSCALE_STRESS, $payload->stressFinalScore),
            provider: self::PROVIDER_NAME,
        );
    }

    /**
     * @throws RuntimeException if no threshold row covers the given score.
     */
    private function classifySubscale(string $subscale, int $finalScore): string
    {
        $threshold = ClassificationThreshold::query()
            ->where('subscale', $subscale)
            ->where('min_score', '<=', $finalScore)
            ->where('max_score', '>=', $finalScore)
            ->first();

        if ($threshold === null) {
            throw new RuntimeException(sprintf(
                'No classification threshold configured for subscale [%s] at score [%d].',
                $subscale,
                $finalScore
            ));
        }

        return $threshold->severity_level;
    }
}
