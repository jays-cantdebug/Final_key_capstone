<?php

declare(strict_types=1);

namespace Tests\Feature\Assessments;

use App\Models\Assessment;
use App\Models\ClassificationThreshold;
use App\Models\DassResult;
use App\Models\FlaggedCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

class PredictionFeedbackTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    public function test_psychometrician_can_confirm_the_ai_classification(): void
    {
        $psychometrician = $this->psychometrician();
        $assessment = Assessment::factory()->create(['psychometrician_id' => $psychometrician->id]);
        DassResult::factory()->create(['assessment_id' => $assessment->id]);

        $response = $this->actingAs($psychometrician)->post(route('assessments.feedback.store', $assessment), [
            'is_confirmed' => true,
        ]);

        $response->assertRedirect(route('assessments.show', $assessment));
        $this->assertDatabaseHas('prediction_feedback', [
            'assessment_id' => $assessment->id,
            'is_confirmed' => true,
        ]);
    }

    public function test_psychometrician_can_correct_the_ai_classification(): void
    {
        $psychometrician = $this->psychometrician();
        $assessment = Assessment::factory()->create(['psychometrician_id' => $psychometrician->id]);
        DassResult::factory()->create(['assessment_id' => $assessment->id]);

        $this->actingAs($psychometrician)->post(route('assessments.feedback.store', $assessment), [
            'is_confirmed' => false,
            'corrected_depression_level' => ClassificationThreshold::SEVERITY_SEVERE,
            'notes' => 'Clinical interview suggests higher severity.',
        ]);

        $this->assertDatabaseHas('prediction_feedback', [
            'assessment_id' => $assessment->id,
            'is_confirmed' => false,
            'corrected_depression_level' => ClassificationThreshold::SEVERITY_SEVERE,
        ]);
    }

    public function test_resubmitting_feedback_updates_the_existing_row_in_place(): void
    {
        $psychometrician = $this->psychometrician();
        $assessment = Assessment::factory()->create(['psychometrician_id' => $psychometrician->id]);
        DassResult::factory()->create(['assessment_id' => $assessment->id]);

        $this->actingAs($psychometrician)->post(route('assessments.feedback.store', $assessment), ['is_confirmed' => true]);
        $this->actingAs($psychometrician)->post(route('assessments.feedback.store', $assessment), [
            'is_confirmed' => false,
            'corrected_stress_level' => ClassificationThreshold::SEVERITY_MILD,
        ]);

        $this->assertDatabaseCount('prediction_feedback', 1);
        $this->assertDatabaseHas('prediction_feedback', [
            'assessment_id' => $assessment->id,
            'is_confirmed' => false,
            'corrected_stress_level' => ClassificationThreshold::SEVERITY_MILD,
        ]);
    }

    /**
     * Regression test: the Feedback Loop route is Psychometrician-only.
     * This was previously only enforced client-side (the Blade view hid
     * the panel) — verified here at the HTTP layer, independent of the view.
     */
    public function test_guidance_counselor_cannot_submit_feedback(): void
    {
        $counselor = $this->guidanceCounselor();
        $assessment = Assessment::factory()->create();
        DassResult::factory()->create(['assessment_id' => $assessment->id]);

        $response = $this->actingAs($counselor)->post(route('assessments.feedback.store', $assessment), [
            'is_confirmed' => true,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('prediction_feedback', 0);
    }

    public function test_correction_does_not_mutate_the_original_dass_result(): void
    {
        $psychometrician = $this->psychometrician();
        $assessment = Assessment::factory()->create(['psychometrician_id' => $psychometrician->id]);
        $result = DassResult::factory()
            ->withLevels(ClassificationThreshold::SEVERITY_MODERATE, ClassificationThreshold::SEVERITY_MODERATE, ClassificationThreshold::SEVERITY_MODERATE)
            ->create(['assessment_id' => $assessment->id]);

        $this->actingAs($psychometrician)->post(route('assessments.feedback.store', $assessment), [
            'is_confirmed' => false,
            'corrected_depression_level' => ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE,
        ]);

        $this->assertSame(ClassificationThreshold::SEVERITY_MODERATE, $result->fresh()->depression_level);
    }

    public function test_correction_does_not_alter_already_issued_flagged_cases(): void
    {
        $psychometrician = $this->psychometrician();
        $this->guidanceCounselor();
        $assessment = Assessment::factory()->create(['psychometrician_id' => $psychometrician->id]);
        DassResult::factory()
            ->withLevels(ClassificationThreshold::SEVERITY_NORMAL, ClassificationThreshold::SEVERITY_NORMAL, ClassificationThreshold::SEVERITY_SEVERE)
            ->create(['assessment_id' => $assessment->id]);
        $flaggedCase = FlaggedCase::factory()->endorsement()->create(['assessment_id' => $assessment->id]);

        $this->actingAs($psychometrician)->post(route('assessments.feedback.store', $assessment), [
            'is_confirmed' => false,
            'corrected_stress_level' => ClassificationThreshold::SEVERITY_NORMAL,
        ]);

        $this->assertDatabaseCount('flagged_cases', 1);
        $this->assertSame(FlaggedCase::FLAG_TYPE_COUNSELING_ENDORSEMENT, $flaggedCase->fresh()->flag_type);
    }
}
