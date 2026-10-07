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
 * `assessmentId` is nullable — classification happens at Step 3 review
 * time, before an `Assessment` row exists (see
 * `AssessmentService::reviewAssessment()`, the only caller, which never
 * sets it), so in practice it is always null, including in the
 * `assessment_id` of ClaudeAIProvider's disagreement/failure log entries;
 * those can only be matched to an assessment by time and scores. It is
 * never used for classification logic, nor sent to the Claude API.
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
