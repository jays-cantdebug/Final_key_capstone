<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClassificationThreshold;
use App\Models\QuestionnaireVersion;
use RuntimeException;

/**
 * Computes the official DASS-21 raw and final subscale scores for a
 * completed set of questionnaire responses.
 *
 * Severity classification is the AI Prediction Module's responsibility
 * (see App\AI\Providers\RuleBasedDASSProvider), not this service's — this
 * service performs no persistence, no classification, and has no
 * dependency on Controllers, HTTP Requests, or Blade Views. It is a pure
 * arithmetic computation engine over already-validated input.
 */
class DassScoringService
{
    /**
     * @param  array<int, int>  $responses  Question ID => answer value (0-3).
     * @return array{
     *     depression_raw_score: int, anxiety_raw_score: int, stress_raw_score: int,
     *     depression_final_score: int, anxiety_final_score: int, stress_final_score: int
     * }
     */
    public function score(QuestionnaireVersion $version, array $responses): array
    {
        $subscaleTotals = [
            ClassificationThreshold::SUBSCALE_DEPRESSION => 0,
            ClassificationThreshold::SUBSCALE_ANXIETY => 0,
            ClassificationThreshold::SUBSCALE_STRESS => 0,
        ];

        foreach ($version->questions as $question) {
            $answerValue = $responses[$question->id] ?? null;

            if ($answerValue === null) {
                throw new RuntimeException(sprintf('Missing response for question #%d.', $question->item_number));
            }

            $subscaleTotals[$question->subscale] += (int) $answerValue;
        }

        $depressionRaw = $subscaleTotals[ClassificationThreshold::SUBSCALE_DEPRESSION];
        $anxietyRaw = $subscaleTotals[ClassificationThreshold::SUBSCALE_ANXIETY];
        $stressRaw = $subscaleTotals[ClassificationThreshold::SUBSCALE_STRESS];

        return [
            'depression_raw_score' => $depressionRaw,
            'anxiety_raw_score' => $anxietyRaw,
            'stress_raw_score' => $stressRaw,
            'depression_final_score' => $depressionRaw * 2,
            'anxiety_final_score' => $anxietyRaw * 2,
            'stress_final_score' => $stressRaw * 2,
        ];
    }
}
