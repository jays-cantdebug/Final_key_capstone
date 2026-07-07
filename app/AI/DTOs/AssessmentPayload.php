<?php

declare(strict_types=1);

namespace App\AI\DTOs;

/**
 * Immutable snapshot of a scored assessment's final DASS-21 subscale
 * scores, prepared for the AI Prediction Module to classify.
 *
 * This DTO carries only the data needed for classification — it performs
 * no computation itself and has no dependency on Eloquent, Controllers,
 * HTTP Requests, or Blade Views.
 */
final class AssessmentPayload
{
    public function __construct(
        public readonly int $assessmentId,
        public readonly int $depressionFinalScore,
        public readonly int $anxietyFinalScore,
        public readonly int $stressFinalScore,
    ) {
    }
}
