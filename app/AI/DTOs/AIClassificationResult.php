<?php

declare(strict_types=1);

namespace App\AI\DTOs;

/**
 * Immutable result of classifying an AssessmentPayload: the severity level
 * assigned to each DASS-21 subscale, plus which AI provider produced it.
 */
final class AIClassificationResult
{
    public function __construct(
        public readonly string $depressionLevel,
        public readonly string $anxietyLevel,
        public readonly string $stressLevel,
        public readonly string $provider,
    ) {}
}
