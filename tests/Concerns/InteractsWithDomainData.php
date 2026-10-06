<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Assessment;
use App\Models\ClassificationThreshold;
use App\Models\Course;
use App\Models\DassQuestion;
use App\Models\QuestionnaireVersion;
use App\Models\Section;
use App\Models\User;
use App\Models\YearLevel;
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

        $this->addDassQuestions($version, $perSubscale, $perSubscale, $perSubscale);

        return $version->fresh('questions');
    }

    /**
     * Add required questions to a version, numbered sequentially, with the
     * given count per subscale. Defaults to the valid DASS-21 layout
     * (7/7/7) that QuestionnaireVersionService::activate() requires.
     */
    protected function addDassQuestions(QuestionnaireVersion $version, int $depression = 7, int $anxiety = 7, int $stress = 7): void
    {
        $itemNumber = 1;
        $displayOrder = 1;

        $counts = [
            DassQuestion::SUBSCALE_DEPRESSION => $depression,
            DassQuestion::SUBSCALE_ANXIETY => $anxiety,
            DassQuestion::SUBSCALE_STRESS => $stress,
        ];

        // Created directly rather than via DassQuestionFactory: its
        // definition draws item_number from faker->unique() over 1-21 even
        // when overridden, which runs out once a test needs more than 21.
        foreach ($counts as $subscale => $count) {
            for ($i = 0; $i < $count; $i++) {
                $version->questions()->create([
                    'item_number' => $itemNumber,
                    'question_text' => "{$subscale} question {$itemNumber}",
                    'question_type' => DassQuestion::TYPE_LIKERT_SCALE,
                    'subscale' => $subscale,
                    'display_order' => $displayOrder++,
                    'is_required' => true,
                ]);
                $itemNumber++;
            }
        }
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

    /**
     * Run the whole New Assessment wizard for a new student — Step 1, Step 2
     * with the given raw subscale scores, then the given Step 3 decision —
     * and return the saved assessment. The caller must already be `actingAs`
     * a Psychometrician, with official thresholds seeded and $version active.
     * Use a different name per call: the wizard refuses a duplicate student.
     *
     * @param  array<string, mixed>  $decision  Step 3 fields, e.g. `['is_confirmed' => '0', 'corrected_stress_level' => 'Severe']`.
     */
    protected function saveAssessmentThroughWizard(
        QuestionnaireVersion $version,
        int $depressionRaw,
        int $anxietyRaw,
        int $stressRaw,
        array $decision = [],
        string $firstName = 'Lia',
        string $lastName = 'Reyes',
    ): Assessment {
        $this->post(route('assessments.create.student'), [
            'first_name' => $firstName,
            'middle_name' => 'C.',
            'last_name' => $lastName,
            'gender' => 'Female',
            'privacy_consent' => '1',
            'course_id' => Course::factory()->create()->id,
            'year_level_id' => YearLevel::factory()->create()->id,
            'section_id' => Section::factory()->create()->id,
        ])->assertRedirect(route('assessments.create.questionnaire'));

        $this->post(route('assessments.create.questionnaire.store'), [
            'responses' => $this->buildResponses($version, $depressionRaw, $anxietyRaw, $stressRaw),
        ])->assertRedirect(route('assessments.create.result'));

        $this->reviewAndSaveAssessment($decision)->assertRedirect();

        return Assessment::query()->latest('id')->firstOrFail();
    }
}
