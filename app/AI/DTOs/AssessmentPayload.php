<?php

declare(strict_types=1);

namespace App\AI\DTOs;

/**
 * Immutable snapshot of a completed, scored assessment, prepared for the
 * AI Prediction Module.
 *
 * This DTO carries only already-computed, persisted assessment data — it
 * performs no computation itself and has no dependency on Eloquent,
 * Controllers, HTTP Requests, or Blade Views.
 */
final class AssessmentPayload
{
    /**
     * @param array<int, array{question: string, subscale: string, answerValue: int}> $responses
     */
    public function __construct(
        public readonly int $assessmentId,
        public readonly int $studentId,
        public readonly string $studentFullName,
        public readonly int $depressionFinalScore,
        public readonly int $anxietyFinalScore,
        public readonly int $stressFinalScore,
        public readonly string $depressionLevel,
        public readonly string $anxietyLevel,
        public readonly string $stressLevel,
        public readonly string $overallStatus,
        public readonly bool $overallFlag,
        public readonly array $responses,
        public readonly string $submittedAt,
    ) {
    }
}
