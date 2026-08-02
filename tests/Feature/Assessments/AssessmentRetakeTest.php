<?php

declare(strict_types=1);

namespace Tests\Feature\Assessments;

use App\Models\Assessment;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

/**
 * "Take Again" retake flow: starts the wizard for an already-registered
 * student, skips Step 1, and (unlike the regular New Assessment flow)
 * attaches the new assessment to the existing student_id instead of
 * minting a fresh student row.
 */
class AssessmentRetakeTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    public function test_take_again_seeds_session_from_existing_student_and_skips_to_step_two(): void
    {
        $psychometrician = $this->psychometrician();
        $student = Student::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);

        $response = $this->actingAs($psychometrician)->get(route('assessments.create.retake', $student));

        $response->assertRedirect(route('assessments.create.questionnaire'));
        $this->assertSame('Juan', session('assessment_wizard.student_data.first_name'));
        $this->assertSame('Dela Cruz', session('assessment_wizard.student_data.last_name'));
        $this->assertSame($student->id, session('assessment_wizard.existing_student_id'));

        // No students table write happens on this step either -- Take
        // Again only stages session data, exactly like Step 1 does.
        $this->assertDatabaseCount('students', 1);
    }

    public function test_retake_requires_privacy_consent_but_regular_flow_does_not(): void
    {
        $psychometrician = $this->psychometrician();
        $this->seedOfficialThresholds();
        $version = $this->createActiveQuestionnaireVersion();
        $responses = $this->buildResponses($version, depressionRaw: 1, anxietyRaw: 1, stressRaw: 1);

        // Retake mode: omitting privacy_consent is rejected.
        $student = Student::factory()->create();
        $this->actingAs($psychometrician)->get(route('assessments.create.retake', $student));

        $retakeResponse = $this->actingAs($psychometrician)
            ->post(route('assessments.create.questionnaire.store'), ['responses' => $responses]);

        $retakeResponse->assertSessionHasErrors('privacy_consent');

        // Regular flow: Step 2 never asks for privacy_consent at all --
        // consent was already captured on Step 1's students row.
        $this->actingAs($psychometrician)->post(route('assessments.create.student'), [
            'first_name' => 'Ana',
            'middle_name' => 'Reyes',
            'last_name' => 'Lopez',
            'gender' => 'Female',
            'privacy_consent' => '1',
            'course_id' => $student->course_id,
            'year_level_id' => $student->year_level_id,
            'section_id' => $student->section_id,
        ]);

        $regularResponse = $this->actingAs($psychometrician)
            ->post(route('assessments.create.questionnaire.store'), ['responses' => $responses]);

        $regularResponse->assertSessionDoesntHaveErrors('privacy_consent');
        $regularResponse->assertRedirect(route('assessments.create.result'));
    }

    public function test_full_retake_flow_attaches_a_new_assessment_to_the_existing_student(): void
    {
        $psychometrician = $this->psychometrician();
        $this->seedOfficialThresholds();
        $version = $this->createActiveQuestionnaireVersion();
        $student = Student::factory()->create();

        $this->actingAs($psychometrician)->get(route('assessments.create.retake', $student));

        $responses = $this->buildResponses($version, depressionRaw: 4, anxietyRaw: 3, stressRaw: 5);
        $this->actingAs($psychometrician)
            ->post(route('assessments.create.questionnaire.store'), [
                'responses' => $responses,
                'privacy_consent' => '1',
            ])
            ->assertRedirect(route('assessments.create.result'));

        $this->actingAs($psychometrician)->reviewAndSaveAssessment();

        // Exactly one student the whole time -- the retake never created
        // a second one, unlike the regular flow which always would.
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('assessments', 1);

        $assessment = Assessment::first();
        $this->assertSame($student->id, $assessment->student_id);
        $this->assertNotNull($assessment->privacy_consent_at);
    }

    public function test_two_retakes_produce_two_assessments_visible_together_in_assessment_history(): void
    {
        $psychometrician = $this->psychometrician();
        $this->seedOfficialThresholds();
        $version = $this->createActiveQuestionnaireVersion();
        $student = Student::factory()->create();

        foreach ([1, 2] as $i) {
            $this->actingAs($psychometrician)->get(route('assessments.create.retake', $student));

            $responses = $this->buildResponses($version, depressionRaw: $i, anxietyRaw: $i, stressRaw: $i);
            $this->actingAs($psychometrician)->post(route('assessments.create.questionnaire.store'), [
                'responses' => $responses,
                'privacy_consent' => '1',
            ]);
            $this->actingAs($psychometrician)->reviewAndSaveAssessment();
        }

        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('assessments', 2);
        $this->assertSame(2, Assessment::where('student_id', $student->id)->count());

        $history = $this->actingAs($psychometrician)
            ->get(route('assessments.index', ['student_number' => $student->student_number]));

        $history->assertOk();
        $history->assertViewHas('assessments', fn ($paginator) => $paginator->total() === 2);
    }

    public function test_retaking_an_archived_student_is_not_found(): void
    {
        $psychometrician = $this->psychometrician();
        $student = Student::factory()->create();
        $student->delete();

        $this->actingAs($psychometrician)
            ->get(route('assessments.create.retake', $student))
            ->assertNotFound();
    }

    public function test_guidance_counselor_cannot_start_a_retake(): void
    {
        $counselor = $this->guidanceCounselor();
        $student = Student::factory()->create();

        $this->actingAs($counselor)
            ->get(route('assessments.create.retake', $student))
            ->assertForbidden();
    }
}
