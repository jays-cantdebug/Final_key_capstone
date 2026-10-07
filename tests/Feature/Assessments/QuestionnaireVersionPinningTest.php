<?php

declare(strict_types=1);

namespace Tests\Feature\Assessments;

use App\Exceptions\QuestionnaireVersionMismatchException;
use App\Http\Controllers\AssessmentWizardController;
use App\Http\Requests\AssessmentResponseFormRequest;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\Questionnaire;
use App\Models\QuestionnaireVersion;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Models\YearLevel;
use App\Services\AssessmentService;
use App\Services\QuestionnaireVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

/**
 * The wizard is pinned to the questionnaire version its answers were given
 * for (the one Active when Step 2 was submitted): Step 3 and the final save
 * use it even if another version is activated in the meantime, from this
 * tab or another one.
 */
class QuestionnaireVersionPinningTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    private User $psychometrician;

    private QuestionnaireVersion $versionA;

    private QuestionnaireVersion $versionB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedOfficialThresholds();
        $this->psychometrician = $this->psychometrician();
        $this->actingAs($this->psychometrician);

        $this->versionA = $this->createActiveQuestionnaireVersion();
        $this->versionB = QuestionnaireVersion::factory()->create([
            'questionnaire_id' => Questionnaire::factory()->create(['title' => 'DASS-21 (Filipino)'])->id,
        ]);
        $this->addDassQuestions($this->versionB);
        $this->versionB->load('questions');
    }

    public function test_a_switch_between_step_2_and_step_3_reviews_and_saves_the_pinned_version(): void
    {
        $this->startNewStudent();
        $this->submitStep2($this->versionA, depressionRaw: 14);

        $this->activate($this->versionB);

        $this->get(route('assessments.create.result'))->assertOk()
            ->assertViewHas('version', fn (QuestionnaireVersion $version): bool => $version->is($this->versionA));
        $this->post(route('assessments.create.submit'), ['is_confirmed' => '1'])->assertRedirect();

        $this->assertSavedUnder($this->versionA, depressionFinal: 28);
    }

    public function test_a_switch_after_step_3_saves_under_the_version_that_was_reviewed(): void
    {
        $this->startNewStudent();
        $this->submitStep2($this->versionA, depressionRaw: 14);
        $this->get(route('assessments.create.result'))->assertOk();

        $this->activate($this->versionB);

        $this->post(route('assessments.create.submit'), ['is_confirmed' => '1'])->assertRedirect();

        $this->assertSavedUnder($this->versionA, depressionFinal: 28);
    }

    public function test_a_step_2_form_submitted_after_a_switch_in_another_tab_gets_one_clear_message(): void
    {
        $this->startNewStudent();
        $this->get(route('assessments.create.questionnaire'))->assertSee('name="questionnaire_version_id" value="'.$this->versionA->id.'"', false);

        $this->activate($this->versionB);

        $response = $this->from(route('assessments.create.questionnaire'))
            ->post(route('assessments.create.questionnaire.store'), $this->step2Payload($this->versionA, depressionRaw: 14));

        $response->assertRedirect(route('assessments.create.questionnaire'));
        $response->assertSessionHasErrors(['questionnaire_version_id' => AssessmentResponseFormRequest::QUESTIONNAIRE_CHANGED_MESSAGE]);
        $this->assertCount(1, session('errors')->getBag('default')->all());
        $this->assertFalse(session()->has('assessment_wizard.responses'));
        $this->get(route('assessments.create.questionnaire'))
            ->assertSee(AssessmentResponseFormRequest::QUESTIONNAIRE_CHANGED_MESSAGE)
            ->assertSee('name="questionnaire_version_id" value="'.$this->versionB->id.'"', false);

        // Answering the version now shown works end to end.
        $this->submitStep2($this->versionB, depressionRaw: 7);
        $this->get(route('assessments.create.result'))->assertOk();
        $this->post(route('assessments.create.submit'), ['is_confirmed' => '1'])->assertRedirect();

        $this->assertSavedUnder($this->versionB, depressionFinal: 14);
    }

    public function test_going_back_and_resubmitting_step_2_after_a_switch_pins_the_new_version(): void
    {
        $this->startNewStudent();
        $this->submitStep2($this->versionA, depressionRaw: 14);
        $this->get(route('assessments.create.result'))->assertOk();

        $this->activate($this->versionB);

        $this->get(route('assessments.create.questionnaire'))->assertOk()
            ->assertSee('The active questionnaire has changed since these questions were answered.')
            ->assertSee('Depression question 3');
        $this->submitStep2($this->versionB, depressionRaw: 7);

        $this->assertSame($this->versionB->id, session('assessment_wizard.questionnaire_version_id'));
        $this->get(route('assessments.create.result'))->assertOk()
            ->assertViewHas('version', fn (QuestionnaireVersion $version): bool => $version->is($this->versionB));
        $this->post(route('assessments.create.submit'), ['is_confirmed' => '1'])->assertRedirect();

        $this->assertSavedUnder($this->versionB, depressionFinal: 14);
    }

    public function test_take_again_is_pinned_the_same_way(): void
    {
        $student = Student::factory()->create();
        $this->get(route('assessments.create.retake', $student));
        $this->post(route('assessments.create.questionnaire.store'), [
            ...$this->step2Payload($this->versionA, depressionRaw: 14),
            'privacy_consent' => '1',
        ])->assertRedirect(route('assessments.create.result'));

        $this->activate($this->versionB);

        $this->get(route('assessments.create.result'))->assertOk();
        $this->post(route('assessments.create.submit'), ['is_confirmed' => '1'])->assertRedirect();

        $assessment = $this->assertSavedUnder($this->versionA, depressionFinal: 28);
        $this->assertTrue($assessment->student->is($student));
    }

    public function test_take_again_clears_a_pin_left_by_an_earlier_wizard(): void
    {
        $this->startNewStudent();
        $this->submitStep2($this->versionA, depressionRaw: 14);

        $this->get(route('assessments.create.retake', Student::factory()->create()));

        $this->assertFalse(session()->has('assessment_wizard.questionnaire_version_id'));
    }

    public function test_a_wizard_session_without_a_pinned_version_is_sent_back_to_step_2(): void
    {
        $this->startNewStudent();
        $this->submitStep2($this->versionA, depressionRaw: 14);
        session()->forget('assessment_wizard.questionnaire_version_id');

        $this->get(route('assessments.create.result'))
            ->assertRedirect(route('assessments.create.questionnaire'))
            ->assertSessionHasErrors(['questionnaire' => AssessmentWizardController::SUBMIT_QUESTIONNAIRE_FIRST_MESSAGE]);
    }

    public function test_a_review_that_does_not_match_the_pinned_version_saves_nothing(): void
    {
        $this->startNewStudent();
        $this->submitStep2($this->versionA, depressionRaw: 14);
        $this->get(route('assessments.create.result'))->assertOk();

        // A stale or tampered session: the pin no longer matches the
        // answers and the review.
        session()->put('assessment_wizard.questionnaire_version_id', $this->versionB->id);

        $response = $this->post(route('assessments.create.submit'), ['is_confirmed' => '1']);

        $response->assertRedirect(route('assessments.create.questionnaire'));
        $response->assertSessionHasErrors(['questionnaire' => (new QuestionnaireVersionMismatchException)->getMessage()]);
        $this->assertDatabaseCount('assessments', 0);
        $this->assertDatabaseCount('students', 0);
        $this->assertFalse(session()->has('assessment_wizard.responses'));
        $this->assertTrue(session()->has('assessment_wizard.student_data'));
        $this->get(route('assessments.create.questionnaire'))
            ->assertSee((new QuestionnaireVersionMismatchException)->getMessage());
    }

    public function test_save_refuses_responses_for_another_versions_questions(): void
    {
        $service = app(AssessmentService::class);
        $responses = $this->buildResponses($this->versionA, 14, 0, 0);
        $review = $service->reviewAssessment($this->versionA, $responses);
        $foreign = $this->versionB->questions->first();

        $this->expectException(QuestionnaireVersionMismatchException::class);

        try {
            $service->save(
                $this->studentData(),
                $this->versionA,
                $this->psychometrician,
                [...$responses, $foreign->id => 3],
                $review,
                ['is_confirmed' => true],
            );
        } finally {
            $this->assertDatabaseCount('students', 0);
            $this->assertDatabaseCount('assessments', 0);
            $this->assertDatabaseCount('dass_responses', 0);
        }
    }

    private function startNewStudent(): void
    {
        $this->post(route('assessments.create.student'), [...$this->studentData(), 'privacy_consent' => '1'])
            ->assertRedirect(route('assessments.create.questionnaire'));
    }

    /**
     * @return array<string, mixed>
     */
    private function studentData(): array
    {
        return [
            'first_name' => 'Lia',
            'middle_name' => 'C.',
            'last_name' => 'Reyes',
            'gender' => 'Female',
            'course_id' => Course::factory()->create()->id,
            'year_level_id' => YearLevel::factory()->create()->id,
            'section_id' => Section::factory()->create()->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function step2Payload(QuestionnaireVersion $version, int $depressionRaw): array
    {
        return [
            'questionnaire_version_id' => $version->id,
            'responses' => $this->buildResponses($version, $depressionRaw, 0, 0),
        ];
    }

    private function submitStep2(QuestionnaireVersion $version, int $depressionRaw): void
    {
        $this->post(route('assessments.create.questionnaire.store'), $this->step2Payload($version, $depressionRaw))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('assessments.create.result'));
    }

    /**
     * Activate through the service, as another tab or user would.
     */
    private function activate(QuestionnaireVersion $version): void
    {
        app(QuestionnaireVersionService::class)->activate($version);
    }

    private function assertSavedUnder(QuestionnaireVersion $version, int $depressionFinal): Assessment
    {
        $assessment = Assessment::query()->with('result')->sole();

        $this->assertSame($version->id, $assessment->questionnaire_version_id);
        $this->assertSame($depressionFinal, $assessment->result->depression_final_score);
        $this->assertSame(
            [$version->id],
            DB::table('dass_responses')
                ->join('dass_questions', 'dass_questions.id', '=', 'dass_responses.dass_question_id')
                ->where('dass_responses.assessment_id', $assessment->id)
                ->distinct()
                ->pluck('dass_questions.questionnaire_version_id')
                ->map(fn ($id): int => (int) $id)
                ->all()
        );
        $this->assertSame(21, DB::table('dass_responses')->where('assessment_id', $assessment->id)->count());

        return $assessment;
    }
}
