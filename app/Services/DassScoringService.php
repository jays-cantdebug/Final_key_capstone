<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClassificationThreshold;
use App\Models\QuestionnaireVersion;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Computes the official DASS-21 subscale scores, severity classifications,
 * and overall flagged status for a completed set of questionnaire
 * responses.
 *
 * This service performs no persistence and has no dependency on
 * Controllers, HTTP Requests, or Blade Views — it is a pure computation
 * engine over already-validated input.
 */
class DassScoringService
{
    public function __construct(private readonly SystemSettingService $settingService)
    {
    }

    /**
     * @param array<int, int> $responses Question ID => answer value (0-3).
     *
     * @return array{
     *     depression_raw_score: int, anxiety_raw_score: int, stress_raw_score: int,
     *     depression_final_score: int, anxiety_final_score: int, stress_final_score: int,
     *     depression_level: string, anxiety_level: string, stress_level: string,
     *     overall_status: string, overall_flag: bool
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

        $depressionFinal = $depressionRaw * 2;
        $anxietyFinal = $anxietyRaw * 2;
        $stressFinal = $stressRaw * 2;

        $depressionThreshold = $this->classify(ClassificationThreshold::SUBSCALE_DEPRESSION, $depressionFinal);
        $anxietyThreshold = $this->classify(ClassificationThreshold::SUBSCALE_ANXIETY, $anxietyFinal);
        $stressThreshold = $this->classify(ClassificationThreshold::SUBSCALE_STRESS, $stressFinal);

        $highestRankThreshold = Collection::make([$depressionThreshold, $anxietyThreshold, $stressThreshold])
            ->sortByDesc(fn (ClassificationThreshold $threshold): int => $threshold->severity_rank)
            ->first();

        $notificationThresholdRank = (int) ClassificationThreshold::query()
            ->where('severity_level', $this->settingService->notificationSeverityThreshold())
            ->value('severity_rank');

        return [
            'depression_raw_score' => $depressionRaw,
            'anxiety_raw_score' => $anxietyRaw,
            'stress_raw_score' => $stressRaw,
            'depression_final_score' => $depressionFinal,
            'anxiety_final_score' => $anxietyFinal,
            'stress_final_score' => $stressFinal,
            'depression_level' => $depressionThreshold->severity_level,
            'anxiety_level' => $anxietyThreshold->severity_level,
            'stress_level' => $stressThreshold->severity_level,
            'overall_status' => $highestRankThreshold->severity_level,
            'overall_flag' => $highestRankThreshold->severity_rank >= $notificationThresholdRank,
        ];
    }

    /**
     * @throws RuntimeException if no threshold row covers the given score.
     */
    private function classify(string $subscale, int $finalScore): ClassificationThreshold
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

        return $threshold;
    }
}
