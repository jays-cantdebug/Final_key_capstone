<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\ClassificationThreshold;
use App\Models\DassQuestion;
use App\Models\QuestionnaireVersion;
use App\Models\User;

/**
 * Shared fixtures for domain tests: acting-as helpers for both roles, the
 * official classification_thresholds seed, and a configurable DASS-21
 * questionnaire version whose per-subscale raw score can be dialed to a
 * specific value so tests can target an exact severity band.
 */
trait InteractsWithDomainData
{
    protected function psychometrician(array $attributes = []): User
    {
        return User::factory()->psychometrician()->create($attributes);
    }

    protected function guidanceCounselor(array $attributes = []): User
    {
        return User::factory()->guidanceCounselor()->create($attributes);
    }

    /**
     * Seed all 15 official DASS-21 classification_thresholds rows — the
     * same source of truth ClassificationThresholdSeeder uses.
     */
    protected function seedOfficialThresholds(): void
    {
        foreach (ClassificationThreshold::officialValues() as $threshold) {
            ClassificationThreshold::query()->updateOrCreate(
                ['subscale' => $threshold['subscale'], 'severity_level' => $threshold['severity_level']],
                ['min_score' => $threshold['min_score'], 'max_score' => $threshold['max_score']]
            );
        }
    }

    /**
     * Build an Active questionnaire version with 7 questions per subscale
     * (21 total, matching the real DASS-21 layout).
     */
    protected function createActiveQuestionnaireVersion(int $perSubscale = 7): QuestionnaireVersion
    {
        $version = QuestionnaireVersion::factory()->active()->create();

        $itemNumber = 1;
        $displayOrder = 1;

        foreach ([DassQuestion::SUBSCALE_DEPRESSION, DassQuestion::SUBSCALE_ANXIETY, DassQuestion::SUBSCALE_STRESS] as $subscale) {
            for ($i = 0; $i < $perSubscale; $i++) {
                DassQuestion::factory()->create([
                    'questionnaire_version_id' => $version->id,
                    'subscale' => $subscale,
                    'item_number' => $itemNumber++,
                    'display_order' => $displayOrder++,
                ]);
            }
        }

        return $version->fresh('questions');
    }

    /**
     * Build a [question_id => answer_value] map for the given version that
     * produces the exact raw score requested per subscale (final score is
     * always raw * 2, per DassScoringService). Each subscale's raw target
     * must be reachable by distributing 0-3 across its question count.
     *
     * @return array<int, int>
     */
    protected function buildResponses(QuestionnaireVersion $version, int $depressionRaw, int $anxietyRaw, int $stressRaw): array
    {
        $targets = [
            DassQuestion::SUBSCALE_DEPRESSION => $depressionRaw,
            DassQuestion::SUBSCALE_ANXIETY => $anxietyRaw,
            DassQuestion::SUBSCALE_STRESS => $stressRaw,
        ];

        $responses = [];

        foreach ($targets as $subscale => $rawTarget) {
            $questions = $version->questions->where('subscale', $subscale)->values();

            $remaining = $rawTarget;

            /** @var DassQuestion $question */
            foreach ($questions as $question) {
                $value = min(3, $remaining);
                $responses[$question->id] = $value;
                $remaining -= $value;
            }
        }

        return $responses;
    }
}
