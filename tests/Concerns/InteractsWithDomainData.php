<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\ClassificationThreshold;
use App\Models\DassQuestion;
use App\Models\QuestionnaireVersion;
use App\Models\User;
use Illuminate\Testing\TestResponse;

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

    /**
     * Drive Step 3 of the New Assessment wizard now that it requires a
     * mandatory pre-save review: GET the review page (computes and caches
     * the AI's classification in session, exactly as a real visit would)
     * then POST the Confirm & Save / Correct & Save decision that is now
     * the only action in the wizard that actually persists anything.
     *
     * Call this after staging student data and responses via the Step 1 /
     * Step 2 wizard routes (or the "Take Again" retake route) in the same
     * test — the caller must already be `actingAs` the right user, since
     * this only issues the two Step 3 requests.
     *
     * Defaults to a plain Confirm with no corrections. Pass `$feedback`
     * to override — e.g. `['is_confirmed' => '0', 'corrected_depression_level' => 'Normal']`
     * to exercise a correction that crosses (or doesn't cross) the
     * flagging threshold.
     *
     * @param  array<string, mixed>  $feedback
     */
    protected function reviewAndSaveAssessment(array $feedback = []): TestResponse
    {
        $this->get(route('assessments.create.result'));

        return $this->post(route('assessments.create.submit'), [
            'is_confirmed' => '1',
            ...$feedback,
        ]);
    }
}
