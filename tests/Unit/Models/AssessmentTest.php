<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Assessment;
use App\Models\DassResult;
use App\Models\FlaggedCase;
use App\Models\PredictionFeedback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class AssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_priority_flag_prefers_counseling_endorsement_over_awareness_notification(): void
    {
        $assessment = Assessment::factory()->create();
        FlaggedCase::factory()->create(['assessment_id' => $assessment->id, 'flag_type' => FlaggedCase::FLAG_TYPE_AWARENESS_NOTIFICATION, 'triggering_subscale' => FlaggedCase::SUBSCALE_DEPRESSION]);
        $endorsement = FlaggedCase::factory()->endorsement()->create(['assessment_id' => $assessment->id]);

        $assessment->load('flaggedCases');

        $this->assertTrue($assessment->priorityFlag()->is($endorsement));
    }

    public function test_secondary_flag_count_counts_rows_beyond_the_priority_flag(): void
    {
        $assessment = Assessment::factory()->create();
        FlaggedCase::factory()->endorsement()->create(['assessment_id' => $assessment->id]);
        FlaggedCase::factory()->create(['assessment_id' => $assessment->id, 'flag_type' => FlaggedCase::FLAG_TYPE_AWARENESS_NOTIFICATION, 'triggering_subscale' => FlaggedCase::SUBSCALE_DEPRESSION]);
        FlaggedCase::factory()->create(['assessment_id' => $assessment->id, 'flag_type' => FlaggedCase::FLAG_TYPE_AWARENESS_NOTIFICATION, 'triggering_subscale' => FlaggedCase::SUBSCALE_ANXIETY]);

        $assessment->load('flaggedCases');

        $this->assertSame(2, $assessment->secondaryFlagCount());
    }

    public function test_priority_flag_is_null_and_secondary_count_is_zero_when_no_flags_exist(): void
    {
        $assessment = Assessment::factory()->create();
        $assessment->load('flaggedCases');

        $this->assertNull($assessment->priorityFlag());
        $this->assertSame(0, $assessment->secondaryFlagCount());
    }

    /**
     * An assessment whose AI classified Depression Normal, Anxiety Mild and
     * Stress as $aiStress, with the given review (null = no review on record),
     * loaded the way every list loads it.
     *
     * @param  array<string, mixed>|null  $review
     */
    private function reviewedAssessment(string $aiStress, ?array $review): Assessment
    {
        $assessment = Assessment::factory()->create();
        DassResult::factory()->withLevels('Normal', 'Mild', $aiStress)->create(['assessment_id' => $assessment->id]);

        if ($review !== null) {
            PredictionFeedback::factory()->create(['assessment_id' => $assessment->id, ...$review]);
        }

        return $assessment->load(['result', 'predictionFeedback']);
    }

    public function test_no_review_on_record_is_not_corrected_and_uses_the_ai_levels(): void
    {
        $assessment = $this->reviewedAssessment('Moderate', null);

        $this->assertFalse($assessment->wasCorrected());
        $this->assertSame('Moderate', $assessment->effectiveLevel('stress'));
        $this->assertSame('Moderate', $assessment->effectiveHighestSeverityLevel());
    }

    public function test_plain_confirm_is_not_corrected(): void
    {
        $assessment = $this->reviewedAssessment('Severe', ['is_confirmed' => true]);

        $this->assertFalse($assessment->wasCorrected());
        $this->assertSame('Severe', $assessment->effectiveLevel('stress'));
    }

    public function test_confirm_with_stored_corrections_is_not_corrected_and_keeps_the_ai_levels(): void
    {
        // Legacy shape (like dev assessment #125): saved before Confirm with
        // corrections was refused. AssessmentService::save() ignores the
        // corrections on a Confirm, so the reviewed levels are the AI's.
        $assessment = $this->reviewedAssessment('Moderate', [
            'is_confirmed' => true,
            'corrected_depression_level' => 'Moderate',
            'corrected_stress_level' => 'Severe',
        ]);

        $this->assertFalse($assessment->wasCorrected());
        $this->assertSame('Moderate', $assessment->effectiveLevel('stress'));
        $this->assertSame('Normal', $assessment->effectiveLevel('depression'));
    }

    public function test_same_level_pick_is_not_corrected(): void
    {
        $assessment = $this->reviewedAssessment('Severe', ['is_confirmed' => false, 'corrected_stress_level' => 'Severe']);

        $this->assertFalse($assessment->wasCorrected());
        $this->assertSame('Severe', $assessment->effectiveLevel('stress'));
    }

    public function test_correct_with_a_real_change_is_corrected_and_uses_the_corrected_level(): void
    {
        $assessment = $this->reviewedAssessment('Moderate', ['is_confirmed' => false, 'corrected_stress_level' => 'Severe']);

        $this->assertTrue($assessment->wasCorrected());
        $this->assertSame('Severe', $assessment->effectiveLevel('stress'));
        // Subscales left Unchanged keep the AI's level.
        $this->assertSame('Normal', $assessment->effectiveLevel('depression'));
        $this->assertSame('Mild', $assessment->effectiveLevel('anxiety'));
        $this->assertSame('Severe', $assessment->effectiveHighestSeverityLevel());
    }

    public function test_correction_within_the_flag_triggering_levels_counts_as_corrected(): void
    {
        $assessment = $this->reviewedAssessment('Severe', ['is_confirmed' => false, 'corrected_stress_level' => 'Extremely Severe']);

        $this->assertTrue($assessment->wasCorrected());
        $this->assertSame('Extremely Severe', $assessment->effectiveLevel('stress'));
    }

    public function test_same_level_pick_next_to_a_real_change_is_corrected(): void
    {
        $assessment = $this->reviewedAssessment('Severe', [
            'is_confirmed' => false,
            'corrected_stress_level' => 'Severe',
            'corrected_depression_level' => 'Mild',
        ]);

        $this->assertTrue($assessment->wasCorrected());
        $this->assertSame('Mild', $assessment->effectiveLevel('depression'));
    }

    public function test_reviewed_levels_refuse_to_lazy_load(): void
    {
        $assessment = Assessment::factory()->create();
        DassResult::factory()->create(['assessment_id' => $assessment->id]);

        $fresh = Assessment::query()->with('result')->findOrFail($assessment->id);

        $this->expectException(LogicException::class);
        $fresh->wasCorrected();
    }

    public function test_effective_level_rejects_an_unknown_subscale(): void
    {
        $assessment = $this->reviewedAssessment('Normal', null);

        $this->expectException(InvalidArgumentException::class);
        $assessment->effectiveLevel('Stress');
    }
}
