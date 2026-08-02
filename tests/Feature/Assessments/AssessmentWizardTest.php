<?php

declare(strict_types=1);

namespace Tests\Feature\Assessments;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\FlaggedCase;
use App\Models\Section;
use App\Models\YearLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

class AssessmentWizardTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    private function lookupIds(): array
    {
        return [
            'course_id' => Course::factory()->create()->id,
            'year_level_id' => YearLevel::factory()->create()->id,
            'section_id' => Section::factory()->create()->id,
        ];
    }

    public function test_step_one_stages_the_student_in_session_without_persisting(): void
    {
        $psychometrician = $this->psychometrician();
        $ids = $this->lookupIds();

        $this->assertDatabaseCount('students', 0);

        $response = $this->actingAs($psychometrician)->post(route('assessments.create.student'), [
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
            'gender' => 'Male',
            'privacy_consent' => '1',
            ...$ids,
        ]);

        $response->assertRedirect(route('assessments.create.questionnaire'));

        // Nothing is written to the database until final submit, so
        // abandoning the wizard here leaves no orphan student row.
        $this->assertDatabaseCount('students', 0);
        $this->assertSame('Juan', session('assessment_wizard.student_data.first_name'));
        $this->assertSame('Dela', session('assessment_wizard.student_data.middle_name'));
        $this->assertSame('Cruz', session('assessment_wizard.student_data.last_name'));
    }

    public function test_middle_name_is_required(): void
    {
        $psychometrician = $this->psychometrician();
        $ids = $this->lookupIds();

        $response = $this->actingAs($psychometrician)->post(route('assessments.create.student'), [
            'first_name' => 'Juan',
            'last_name' => 'Cruz',
            'gender' => 'Male',
            'privacy_consent' => '1',
            ...$ids,
        ]);

        $response->assertSessionHasErrors('middle_name');
        $this->assertDatabaseCount('students', 0);
    }

    public function test_first_middle_and_last_name_are_required(): void
    {
        $psychometrician = $this->psychometrician();
        $ids = $this->lookupIds();

        $response = $this->actingAs($psychometrician)->post(route('assessments.create.student'), [
            'gender' => 'Male',
            'privacy_consent' => '1',
            ...$ids,
        ]);

        $response->assertSessionHasErrors(['first_name', 'middle_name', 'last_name']);
        $this->assertDatabaseCount('students', 0);
    }

    public function test_abandoning_wizard_after_step_two_leaves_no_orphan_student(): void
    {
        $psychometrician = $this->psychometrician();
        $this->seedOfficialThresholds();
        $version = $this->createActiveQuestionnaireVersion();
        $ids = $this->lookupIds();

        $this->actingAs($psychometrician)->post(route('assessments.create.student'), [
            'first_name' => 'Ana',
            'middle_name' => 'Reyes',
            'last_name' => 'Lopez',
            'gender' => 'Female',
            'privacy_consent' => '1',
            ...$ids,
        ]);

        $responses = $this->buildResponses($version, depressionRaw: 1, anxietyRaw: 1, stressRaw: 1);
        $this->actingAs($psychometrician)->post(route('assessments.create.questionnaire.store'), ['responses' => $responses]);

        // Wizard abandoned here -- Step 3 submit is never posted.
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('assessments', 0);
    }

    public function test_questionnaire_step_requires_a_registered_student_first(): void
    {
        $psychometrician = $this->psychometrician();

        $response = $this->actingAs($psychometrician)->get(route('assessments.create.questionnaire'));

        $response->assertRedirect(route('assessments.create'));
        $response->assertSessionHasErrors('student');
    }

    public function test_incomplete_questionnaire_submission_is_rejected_with_missing_question_errors(): void
    {
        $psychometrician = $this->psychometrician();
        $this->seedOfficialThresholds();
        $version = $this->createActiveQuestionnaireVersion();
        $ids = $this->lookupIds();

        $this->actingAs($psychometrician)->post(route('assessments.create.student'), [
            'first_name' => 'Liza',
            'middle_name' => 'Ramos',
            'last_name' => 'Torres',
            'gender' => 'Female',
            'privacy_consent' => '1',
            ...$ids,
        ]);

        $responses = $this->buildResponses($version, depressionRaw: 1, anxietyRaw: 1, stressRaw: 1);
        $missingQuestion = $version->questions->first();
        unset($responses[$missingQuestion->id]);

        $response = $this->actingAs($psychometrician)
            ->post(route('assessments.create.questionnaire.store'), ['responses' => $responses]);

        $response->assertSessionHasErrors('responses.'.$missingQuestion->id);
        $this->assertDatabaseCount('assessments', 0);
    }

    public function test_review_step_computes_but_does_not_persist_anything(): void
    {
        $psychometrician = $this->psychometrician();
        $this->seedOfficialThresholds();
        $version = $this->createActiveQuestionnaireVersion();
        $ids = $this->lookupIds();

        $this->actingAs($psychometrician)->post(route('assessments.create.student'), [
            'first_name' => 'Maria',
            'middle_name' => 'Garcia',
            'last_name' => 'Santos',
            'gender' => 'Female',
            'privacy_consent' => '1',
            ...$ids,
        ]);

        $responses = $this->buildResponses($version, depressionRaw: 4, anxietyRaw: 3, stressRaw: 5);
        $this->actingAs($psychometrician)->post(route('assessments.create.questionnaire.store'), ['responses' => $responses]);

        $response = $this->actingAs($psychometrician)->get(route('assessments.create.result'));

        $response->assertOk();
        $response->assertViewHas('review', fn (array $review): bool => $review['scores']['depression_final_score'] === 8
            && $review['scores']['anxiety_final_score'] === 6
            && $review['scores']['stress_final_score'] === 10);

        // Visiting the review page computes and caches the AI's
        // classification but must not write anything to the database --
        // review is required, but reviewing is not the same as saving.
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('assessments', 0);
        $this->assertDatabaseCount('dass_results', 0);
    }

    public function test_full_wizard_flow_creates_a_scored_assessment(): void
    {
        $psychometrician = $this->psychometrician();
        $this->seedOfficialThresholds();
        $version = $this->createActiveQuestionnaireVersion();
        $ids = $this->lookupIds();

        $this->actingAs($psychometrician)->post(route('assessments.create.student'), [
            'first_name' => 'Maria',
            'middle_name' => 'Garcia',
            'last_name' => 'Santos',
            'gender' => 'Female',
            'privacy_consent' => '1',
            ...$ids,
        ]);

        $responses = $this->buildResponses($version, depressionRaw: 4, anxietyRaw: 3, stressRaw: 5);

        $this->actingAs($psychometrician)
            ->post(route('assessments.create.questionnaire.store'), ['responses' => $responses])
            ->assertRedirect(route('assessments.create.result'));

        $submitResponse = $this->actingAs($psychometrician)->reviewAndSaveAssessment();

        $this->assertDatabaseCount('assessments', 1);
        $this->assertDatabaseCount('dass_results', 1);

        $assessment = Assessment::first();
        $submitResponse->assertRedirect(route('assessments.show', $assessment));

        $this->assertSame(8, $assessment->result->depression_final_score);
        $this->assertSame(6, $assessment->result->anxiety_final_score);
        $this->assertSame(10, $assessment->result->stress_final_score);

        // Confirming (the default decision `reviewAndSaveAssessment()`
        // submits) is itself a mandatory review decision -- it always
        // creates the prediction_feedback row, not just on request.
        $this->assertDatabaseHas('prediction_feedback', [
            'assessment_id' => $assessment->id,
            'is_confirmed' => 1,
        ]);
    }

    public function test_confirming_persists_the_ai_classification_and_flags_off_it(): void
    {
        $psychometrician = $this->psychometrician();
        $this->guidanceCounselor();
        $this->seedOfficialThresholds();
        $version = $this->createActiveQuestionnaireVersion();
        $ids = $this->lookupIds();

        $this->actingAs($psychometrician)->post(route('assessments.create.student'), [
            'first_name' => 'Pedro',
            'middle_name' => 'Villanueva',
            'last_name' => 'Reyes',
            'gender' => 'Male',
            'privacy_consent' => '1',
            ...$ids,
        ]);

        // Stress raw 14 -> final 28, Severe (26-33 band).
        $responses = $this->buildResponses($version, depressionRaw: 0, anxietyRaw: 0, stressRaw: 14);
        $this->actingAs($psychometrician)->post(route('assessments.create.questionnaire.store'), ['responses' => $responses]);

        $this->actingAs($psychometrician)->reviewAndSaveAssessment();

        $this->assertDatabaseHas('dass_results', ['stress_level' => 'Severe']);
        $this->assertDatabaseHas('flagged_cases', ['flag_type' => FlaggedCase::FLAG_TYPE_COUNSELING_ENDORSEMENT, 'triggering_subscale' => FlaggedCase::SUBSCALE_STRESS]);
        $this->assertDatabaseCount('system_notifications', 1);
    }

    public function test_correcting_down_across_the_flagging_threshold_creates_no_flag_or_notification(): void
    {
        $psychometrician = $this->psychometrician();
        $this->guidanceCounselor();
        $this->seedOfficialThresholds();
        $version = $this->createActiveQuestionnaireVersion();
        $ids = $this->lookupIds();

        $this->actingAs($psychometrician)->post(route('assessments.create.student'), [
            'first_name' => 'Carla',
            'middle_name' => 'Bautista',
            'last_name' => 'Mendoza',
            'gender' => 'Female',
            'privacy_consent' => '1',
            ...$ids,
        ]);

        // Depression raw 14 -> final 28, Extremely Severe (28-42 band) --
        // would trigger an awareness_notification flag if left as-is.
        $responses = $this->buildResponses($version, depressionRaw: 14, anxietyRaw: 0, stressRaw: 0);
        $this->actingAs($psychometrician)->post(route('assessments.create.questionnaire.store'), ['responses' => $responses]);

        $this->actingAs($psychometrician)->reviewAndSaveAssessment([
            'is_confirmed' => '0',
            'corrected_depression_level' => 'Normal',
        ]);

        $assessment = Assessment::first();

        // The AI's raw classification is still preserved for the audit
        // trail -- correcting it does not rewrite what the AI actually said.
        $this->assertSame('Extremely Severe', $assessment->result->depression_level);

        // But flagging and notification are evaluated against the
        // reviewed, corrected severity -- which never crossed the
        // threshold, so neither should exist.
        $this->assertDatabaseCount('flagged_cases', 0);
        $this->assertDatabaseCount('system_notifications', 0);

        $this->assertDatabaseHas('prediction_feedback', [
            'assessment_id' => $assessment->id,
            'is_confirmed' => 0,
            'corrected_depression_level' => 'Normal',
        ]);
    }

    public function test_correcting_up_across_the_flagging_threshold_creates_a_flag_and_notification(): void
    {
        $psychometrician = $this->psychometrician();
        $this->guidanceCounselor();
        $this->seedOfficialThresholds();
        $version = $this->createActiveQuestionnaireVersion();
        $ids = $this->lookupIds();

        $this->actingAs($psychometrician)->post(route('assessments.create.student'), [
            'first_name' => 'Dario',
            'middle_name' => 'Salonga',
            'last_name' => 'Ilagan',
            'gender' => 'Male',
            'privacy_consent' => '1',
            ...$ids,
        ]);

        // Depression raw 0 -> final 0, Normal -- the AI sees nothing
        // flag-worthy here.
        $responses = $this->buildResponses($version, depressionRaw: 0, anxietyRaw: 0, stressRaw: 0);
        $this->actingAs($psychometrician)->post(route('assessments.create.questionnaire.store'), ['responses' => $responses]);

        $this->actingAs($psychometrician)->reviewAndSaveAssessment([
            'is_confirmed' => '0',
            'corrected_depression_level' => 'Extremely Severe',
        ]);

        $assessment = Assessment::first();

        // Raw AI output preserved, exactly as the "correct down" case.
        $this->assertSame('Normal', $assessment->result->depression_level);

        // A human-initiated upward correction is just as valid an input
        // to flagging as the AI's own output -- it must not be silently
        // ignored just because the AI never flagged it.
        $this->assertDatabaseHas('flagged_cases', [
            'assessment_id' => $assessment->id,
            'flag_type' => FlaggedCase::FLAG_TYPE_AWARENESS_NOTIFICATION,
            'triggering_subscale' => FlaggedCase::SUBSCALE_DEPRESSION,
        ]);
        $this->assertDatabaseCount('system_notifications', 1);
    }

    public function test_final_save_validation_failure_persists_nothing(): void
    {
        $psychometrician = $this->psychometrician();
        $this->seedOfficialThresholds();
        $version = $this->createActiveQuestionnaireVersion();
        $ids = $this->lookupIds();

        $this->actingAs($psychometrician)->post(route('assessments.create.student'), [
            'first_name' => 'Elena',
            'middle_name' => 'Cruz',
            'last_name' => 'Padilla',
            'gender' => 'Female',
            'privacy_consent' => '1',
            ...$ids,
        ]);

        $responses = $this->buildResponses($version, depressionRaw: 1, anxietyRaw: 1, stressRaw: 1);
        $this->actingAs($psychometrician)->post(route('assessments.create.questionnaire.store'), ['responses' => $responses]);
        $this->actingAs($psychometrician)->get(route('assessments.create.result'));

        // Neither Confirm nor Correct was actually chosen.
        $response = $this->actingAs($psychometrician)->post(route('assessments.create.submit'), [
            'corrected_depression_level' => 'not-a-real-severity-level',
        ]);

        $response->assertSessionHasErrors(['is_confirmed', 'corrected_depression_level']);
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('assessments', 0);
        $this->assertDatabaseCount('dass_results', 0);
    }

    public function test_submitting_without_completing_step_one_is_rejected(): void
    {
        $psychometrician = $this->psychometrician();

        $response = $this->actingAs($psychometrician)->post(route('assessments.create.submit'), [
            'is_confirmed' => '1',
        ]);

        $response->assertRedirect(route('assessments.create'));
        $this->assertDatabaseCount('assessments', 0);
    }

    public function test_guidance_counselor_cannot_access_the_wizard(): void
    {
        $counselor = $this->guidanceCounselor();

        $this->actingAs($counselor)->get(route('assessments.create'))->assertForbidden();
    }
}
