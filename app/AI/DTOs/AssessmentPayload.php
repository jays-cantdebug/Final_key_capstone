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
 *
 * `assessmentId` is nullable — classification now happens at Step 3
 * review time, before an `Assessment` row exists (see
 * `AssessmentService::reviewAssessment()`), so there is no real ID to
 * carry yet. It is only ever used for log correlation in a provider's
 * error/disagreement logging, never for classification logic itself.
 */
final class AssessmentPayload
{
    public function __construct(
        public readonly int $depressionFinalScore,
        public readonly int $anxietyFinalScore,
        public readonly int $stressFinalScore,
        public readonly ?int $assessmentId = null,
    ) {}
}
