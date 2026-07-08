<?php

declare(strict_types=1);

namespace Tests\Feature\Assessments;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\FlaggedCase;
use App\Models\Section;
use App\Models\Student;
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

    public function test_step_one_registers_a_brand_new_student_every_time(): void
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
        $this->assertDatabaseCount('students', 1);

        $student = Student::first();
        $this->assertSame('Juan', $student->first_name);
        $this->assertSame('Dela', $student->middle_name);
        $this->assertSame('Cruz', $student->last_name);
        $this->assertNotEmpty($student->student_number);
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

        $student = Student::first();
        $this->assertSame($first, $student->first_name);
        $this->assertSame($middle, $student->middle_name);
        $this->assertSame($last, $student->last_name);
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
