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
            'full_name' => 'Juan Dela Cruz',
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

    public function test_abandoning_wizard_after_step_two_leaves_no_orphan_student(): void
    {
        $psychometrician = $this->psychometrician();
        $this->seedOfficialThresholds();
        $version = $this->createActiveQuestionnaireVersion();
        $ids = $this->lookupIds();

        $this->actingAs($psychometrician)->post(route('assessments.create.student'), [
            'full_name' => 'Ana Lopez',
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

    /**
     * @dataProvider fullNameSplits
     */
    public function test_full_name_splitting_edge_cases(string $fullName, ?string $first, ?string $middle, ?string $last, bool $shouldSucceed): void
    {
        $psychometrician = $this->psychometrician();
        $ids = $this->lookupIds();

        $response = $this->actingAs($psychometrician)->post(route('assessments.create.student'), [
            'full_name' => $fullName,
            'gender' => 'Male',
            'privacy_consent' => '1',
            ...$ids,
        ]);

        if (! $shouldSucceed) {
            $response->assertSessionHasErrors('full_name');
            $this->assertDatabaseCount('students', 0);

            return;
        }

        $this->assertSame($first, session('assessment_wizard.student_data.first_name'));
        $this->assertSame($middle, session('assessment_wizard.student_data.middle_name'));
        $this->assertSame($last, session('assessment_wizard.student_data.last_name'));
    }

    public static function fullNameSplits(): array
    {
        return [
            'single word is rejected' => ['Juan', null, null, null, false],
            'two words -> first/last, no middle' => ['Juan Cruz', 'Juan', null, 'Cruz', true],
            'three words -> first/middle/last' => ['Juan Dela Cruz', 'Juan', 'Dela', 'Cruz', true],
            'four+ words -> everything between joined as middle' => ['Juan Carlos Dela Cruz', 'Juan', 'Carlos Dela', 'Cruz', true],
        ];
    }

    public function test_questionnaire_step_requires_a_registered_student_first(): void
    {
        $psychometrician = $this->psychometrician();

        $response = $this->actingAs($psychometrician)->get(route('assessments.create.questionnaire'));

        $response->assertRedirect(route('assessments.create'));
        $response->assertSessionHasErrors('student');
    }

    public function test_full_wizard_flow_creates_a_scored_assessment(): void
    {
        $psychometrician = $this->psychometrician();
        $this->seedOfficialThresholds();
        $version = $this->createActiveQuestionnaireVersion();
        $ids = $this->lookupIds();

        $this->actingAs($psychometrician)->post(route('assessments.create.student'), [
            'full_name' => 'Maria Santos',
            'gender' => 'Female',
            'privacy_consent' => '1',
            ...$ids,
        ]);

        $responses = $this->buildResponses($version, depressionRaw: 4, anxietyRaw: 3, stressRaw: 5);

        $this->actingAs($psychometrician)
            ->post(route('assessments.create.questionnaire.store'), ['responses' => $responses])
            ->assertRedirect(route('assessments.create.result'));

        $submitResponse = $this->actingAs($psychometrician)->post(route('assessments.create.submit'));

        $this->assertDatabaseCount('assessments', 1);
        $this->assertDatabaseCount('dass_results', 1);

        $assessment = Assessment::first();
        $submitResponse->assertRedirect(route('assessments.show', $assessment));

        $this->assertSame(8, $assessment->result->depression_final_score);
        $this->assertSame(6, $assessment->result->anxiety_final_score);
        $this->assertSame(10, $assessment->result->stress_final_score);
    }

    public function test_submit_evaluates_flagging_end_to_end_for_a_severe_case(): void
    {
        $psychometrician = $this->psychometrician();
        $this->guidanceCounselor();
        $this->seedOfficialThresholds();
        $version = $this->createActiveQuestionnaireVersion();
        $ids = $this->lookupIds();

        $this->actingAs($psychometrician)->post(route('assessments.create.student'), [
            'full_name' => 'Pedro Reyes',
            'gender' => 'Male',
            'privacy_consent' => '1',
            ...$ids,
        ]);

        // Stress raw 14 -> final 28, Severe (26-33 band).
        $responses = $this->buildResponses($version, depressionRaw: 0, anxietyRaw: 0, stressRaw: 14);
        $this->actingAs($psychometrician)->post(route('assessments.create.questionnaire.store'), ['responses' => $responses]);
        $this->actingAs($psychometrician)->post(route('assessments.create.submit'));

        $this->assertDatabaseHas('flagged_cases', ['flag_type' => FlaggedCase::FLAG_TYPE_COUNSELING_ENDORSEMENT]);
        $this->assertDatabaseCount('system_notifications', 1);
    }

    public function test_submitting_without_completing_step_one_is_rejected(): void
    {
        $psychometrician = $this->psychometrician();

        $response = $this->actingAs($psychometrician)->post(route('assessments.create.submit'));

        $response->assertRedirect(route('assessments.create'));
        $this->assertDatabaseCount('assessments', 0);
    }

    public function test_guidance_counselor_cannot_access_the_wizard(): void
    {
        $counselor = $this->guidanceCounselor();

        $this->actingAs($counselor)->get(route('assessments.create'))->assertForbidden();
    }
}
