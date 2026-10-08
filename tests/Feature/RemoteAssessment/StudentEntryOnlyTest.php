<?php

declare(strict_types=1);

namespace Tests\Feature\RemoteAssessment;

use App\Http\Middleware\RefuseWhenStudentEntryOnly;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\QuestionnaireVersion;
use App\Models\RemoteAssessmentDraft;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Models\YearLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithDomainData;
use Tests\Concerns\InteractsWithStudentDevice;
use Tests\TestCase;

/**
 * REMOTE_ASSESSMENT_STUDENT_ENTRY_ONLY on (phpunit.xml pins it off for the
 * rest of the suite): the student enters Step 1 on the student device, so
 * there is no manual Step 1 form; "New Assessment" is a POST that resumes a
 * run in progress or starts one, straight to the live page; a GET, Cancel
 * or an error never creates a draft; the manual Step 1 POST, same-device
 * answers and sending staff-typed details are refused; Take Again works on
 * the student device only. Off: everything as before.
 */
class StudentEntryOnlyTest extends TestCase
{
    use InteractsWithDomainData;
    use InteractsWithStudentDevice;
    use RefreshDatabase;

    private User $owner;

    private QuestionnaireVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        config(['remote_assessment.student_entry_only' => true, 'remote_assessment.student_consent' => true, 'remote_assessment.url' => 'http://192.168.1.10']);
        $this->seedOfficialThresholds();
        $this->version = $this->createActiveQuestionnaireVersion();
        $this->owner = $this->psychometrician();
        $this->actingAs($this->owner);
    }

    public function test_there_is_no_step_one_form_only_a_start_button(): void
    {
        $page = $this->get(route('assessments.create'))->assertOk();

        $page->assertSee('data-start-page', false)
            ->assertSee('action="'.route('assessments.create.start').'"', false)
            ->assertSee('Start a new assessment')
            ->assertDontSee('name="first_name"', false)
            ->assertDontSee('name="course_id"', false)
            ->assertDontSee('name="privacy_consent"', false)
            ->assertDontSee('The student has acknowledged the data privacy consent notice for this assessment.')
            ->assertDontSee('Continue to Questionnaire')
            ->assertDontSee('Who will fill in the details?')
            ->assertDontSee('Let the student fill this in on their device')
            ->assertDontSee('Step 1: Student Information');
        $this->assertSame(0, RemoteAssessmentDraft::query()->count());
    }

    public function test_the_sidebar_new_assessment_is_a_post_button_not_a_link(): void
    {
        $page = (string) $this->get(route('psychometrician.dashboard'))->assertOk()->getContent();

        // Desktop sidebar and mobile drawer.
        $this->assertSame(2, preg_match_all('#<form method="POST" action="'.preg_quote(route('assessments.create.start'), '#').'" data-new-assessment-start>\s*<input type="hidden" name="_token"#', $page));
        $this->assertDoesNotMatchRegularExpression('#<a\b[^>]*href="'.preg_quote(route('assessments.create'), '#').'"#', $page);
        $this->assertSame(2, substr_count($page, '>New Assessment</span>'));
    }

    public function test_new_assessment_reaches_the_live_page_with_one_post_and_a_303(): void
    {
        $response = $this->post(route('assessments.create.start'));

        $response->assertStatus(303)->assertRedirect(route('assessments.create.remote'));
        $draft = RemoteAssessmentDraft::query()->sole();
        $this->assertTrue($draft->collects_identity);
        $this->assertSame($draft->id, session('assessment_wizard.remote_draft_id'));

        $this->get(route('assessments.create.remote'))
            ->assertOk()
            ->assertSee('Steps 1 and 2 on the student device')
            ->assertSee('http://192.168.1.10/s')
            ->assertSee((string) session('assessment_wizard.remote_short_code'))
            ->assertSee('Student details (by the student)')
            ->assertDontSee('Student Information');
    }

    public function test_new_assessment_resumes_a_live_draft_and_never_discards_it(): void
    {
        $this->post(route('assessments.create.start'))->assertStatus(303);
        $draft = RemoteAssessmentDraft::query()->sole();
        $code = (string) session('assessment_wizard.remote_short_code');
        $token = (string) session('assessment_wizard.remote_token');

        // The student is part-way: details in, one answer.
        $device = $this->claimWithCode($code);
        $this->consentOnDevice($device);
        $this->sendDetailsOnDevice($device, $this->studentDetails('Rhea', 'D.', 'Baculio'));
        $question = $this->version->questions->first();
        $this->studentRequest('POST', route('student-device.answer'), ['question_id' => $question->id, 'value' => 2], $device, json: true)->assertOk();
        $this->actingAs($this->owner);
        $before = [$draft->fresh()->revision, $draft->fresh()->identity, $draft->fresh()->responses];

        // New Assessment again (sidebar), "Send to student device again", and the plain GET.
        $this->post(route('assessments.create.start'))->assertStatus(303)->assertRedirect(route('assessments.create.remote'));
        $this->post(route('assessments.create.remote.store-student'))->assertStatus(303)->assertRedirect(route('assessments.create.remote'));
        $this->get(route('assessments.create'))->assertStatus(303)->assertRedirect(route('assessments.create.remote'));

        $this->assertSame([$draft->id], RemoteAssessmentDraft::query()->pluck('id')->all());
        $this->assertSame($before, [$draft->fresh()->revision, $draft->fresh()->identity, $draft->fresh()->responses]);
        $this->assertSame($code, session('assessment_wizard.remote_short_code'));
        $this->assertSame($token, session('assessment_wizard.remote_token'));
        $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)->assertExactJson(['state' => 'answering']);
    }

    public function test_new_assessment_resumes_a_live_take_again_draft(): void
    {
        $student = Student::factory()->create();
        $this->get(route('assessments.create.retake', $student))->assertRedirect(route('assessments.create.questionnaire'));
        ['draft' => $draft] = $this->sendToStudentDevice();

        $this->post(route('assessments.create.start'))->assertStatus(303)->assertRedirect(route('assessments.create.remote'));

        $this->assertSame([$draft->id], RemoteAssessmentDraft::query()->pluck('id')->all());
        $this->assertFalse($draft->fresh()->collects_identity);
        $this->assertSame($student->id, session('assessment_wizard.existing_student_id'));
    }

    public function test_an_expired_or_declined_draft_is_replaced(): void
    {
        $this->post(route('assessments.create.start'));
        $expired = RemoteAssessmentDraft::query()->sole();
        $this->travel(61)->minutes();
        $this->post(route('assessments.create.start'))->assertStatus(303);
        $this->assertModelMissing($expired);
        $declined = RemoteAssessmentDraft::query()->sole();

        $this->studentRequest('POST', route('student-device.decline'), device: $this->claimWithCode((string) session('assessment_wizard.remote_short_code')))->assertOk();
        $this->actingAs($this->owner);
        $this->assertSame(RemoteAssessmentDraft::STATUS_DECLINED, $declined->fresh()->status);
        // The declined draft's live page stays until New Assessment.
        $this->get(route('assessments.create'))->assertOk()->assertSee('data-start-page', false);
        $this->post(route('assessments.create.start'))->assertStatus(303);
        $this->assertModelMissing($declined);
        $this->assertSame(1, RemoteAssessmentDraft::query()->count());
    }

    public function test_a_get_never_creates_a_draft(): void
    {
        foreach ([
            [],
            ['HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_SEC_FETCH_MODE' => 'navigate'],
            ['HTTP_SEC_FETCH_SITE' => 'same-origin'],
            ['HTTP_PURPOSE' => 'prefetch', 'HTTP_SEC_PURPOSE' => 'prefetch'],
            ['HTTP_REFERER' => 'https://elsewhere.example/'],
        ] as $headers) {
            $this->call('GET', route('assessments.create'), server: $headers)->assertOk();
            $this->call('HEAD', route('assessments.create'), server: $headers);
        }

        $this->assertSame(0, RemoteAssessmentDraft::query()->count());
        $this->assertNull(session('assessment_wizard.remote_draft_id'));
    }

    public function test_refused_and_error_paths_never_create_a_draft(): void
    {
        $course = Course::factory()->create();
        $stepOne = [
            'first_name' => 'Ana', 'middle_name' => 'B.', 'last_name' => 'Cruz', 'gender' => 'Female', 'privacy_consent' => '1',
            'course_id' => $course->id, 'year_level_id' => YearLevel::factory()->create()->id, 'section_id' => Section::factory()->create()->id,
        ];

        // The manual Step 1 and the same-device Step 2.
        $this->post(route('assessments.create.student'), $stepOne)->assertStatus(303)->assertRedirect(route('assessments.create'));
        $this->post(route('assessments.create.questionnaire.store'), ['responses' => $this->buildResponses($this->version, 1, 1, 1)])
            ->assertStatus(303)->assertRedirect(route('assessments.create.questionnaire'));
        // A staff-typed new student staged before the flag was turned on.
        $this->withSession(['assessment_wizard.student_data' => [...$stepOne, 'privacy_consent_at' => now()]]);
        $this->post(route('assessments.create.remote.store'))->assertRedirect(route('assessments.create'))
            ->assertSessionHasErrors(['student' => RefuseWhenStudentEntryOnly::STEP_ONE_MESSAGE]);
        // Every error redirect lands on the start page.
        foreach (['assessments.create.questionnaire', 'assessments.create.result'] as $name) {
            $this->flushSession();
            $this->get(route($name))->assertRedirect(route('assessments.create'));
            $this->get(route('assessments.create'))->assertOk()->assertSee('data-start-page', false);
        }
        $this->post(route('assessments.create.submit'), ['is_confirmed' => '1'])->assertRedirect(route('assessments.create'));
        // No active questionnaire version.
        QuestionnaireVersion::query()->update(['status' => QuestionnaireVersion::STATUS_ARCHIVED]);
        $this->post(route('assessments.create.start'))->assertStatus(303)->assertRedirect(route('assessments.create'))->assertSessionHasErrors('student');
        $this->post(route('assessments.create.remote.store-student'))->assertStatus(303)->assertRedirect(route('assessments.create'));

        $this->assertSame(0, RemoteAssessmentDraft::query()->count());
        $this->assertSame(0, Student::query()->count());
        $this->assertSame(0, Assessment::query()->count());

        // Cancel lands on the start page and doesn't create a new draft.
        QuestionnaireVersion::query()->whereKey($this->version->id)->update(['status' => QuestionnaireVersion::STATUS_ACTIVE]);
        $this->flushSession();
        $this->actingAs($this->owner);
        $this->post(route('assessments.create.start'));
        $this->delete(route('assessments.create.remote.cancel'))->assertRedirect(route('assessments.create'));
        $this->get(route('assessments.create'))->assertOk()->assertSee('data-start-page', false)->assertSee('The student device session was cancelled.');
        $this->assertSame(0, RemoteAssessmentDraft::query()->count());
    }

    public function test_a_direct_post_to_the_manual_step_one_is_refused(): void
    {
        $this->post(route('assessments.create.student'), [
            'first_name' => 'Ana', 'middle_name' => 'B.', 'last_name' => 'Cruz', 'gender' => 'Female', 'privacy_consent' => '1',
            'course_id' => Course::factory()->create()->id, 'year_level_id' => YearLevel::factory()->create()->id, 'section_id' => Section::factory()->create()->id,
        ])->assertStatus(303)->assertRedirect(route('assessments.create'))
            ->assertSessionHasErrors(['student' => RefuseWhenStudentEntryOnly::STEP_ONE_MESSAGE]);

        $this->assertNull(session('assessment_wizard.student_data'));
        $this->get(route('assessments.create'))->assertOk()->assertSee(RefuseWhenStudentEntryOnly::STEP_ONE_MESSAGE);

        // Refused before validation: even an empty or nonsense form gets the same answer.
        $this->post(route('assessments.create.student'), [])->assertSessionHasErrors(['student' => RefuseWhenStudentEntryOnly::STEP_ONE_MESSAGE]);
        $this->post(route('assessments.create.student'), ['first_name' => ['x']])->assertSessionHasErrors(['student' => RefuseWhenStudentEntryOnly::STEP_ONE_MESSAGE]);
        $this->assertSame(0, Student::query()->count());
    }

    public function test_staged_answers_are_resumed_at_step_three_with_discard(): void
    {
        // A whole student-device run up to Step 3.
        $this->post(route('assessments.create.start'));
        $this->completeWithDetailsOnStudentDevice((string) session('assessment_wizard.remote_short_code'), $this->version, $this->studentDetails('Lia', 'S.', 'Santos'));
        $this->actingAs($this->owner);
        $this->post(route('assessments.create.remote.submit'), ['privacy_consent' => '1'])->assertRedirect(route('assessments.create.result'));
        $this->get(route('assessments.create.result'))->assertOk()->assertDontSee('data-wizard-resumed', false);
        $staged = session('assessment_wizard.responses');

        // New Assessment: back to Step 3, nothing wiped.
        $this->post(route('assessments.create.start'))->assertStatus(303)->assertRedirect(route('assessments.create.result'));
        $this->assertSame($staged, session('assessment_wizard.responses'));
        $this->get(route('assessments.create.result'))->assertOk()
            ->assertSee('data-wizard-resumed', false)
            ->assertSee('Discard and start a new assessment')
            ->assertSee('action="'.route('assessments.create.discard').'"', false)
            ->assertSee('Lia S. Santos');
        $this->assertSame(0, RemoteAssessmentDraft::query()->count());

        // It can still be saved…
        $this->post(route('assessments.create.start'));
        $this->reviewAndSaveAssessment()->assertRedirect();
        $this->assertSame(1, Assessment::query()->count());

        // …or discarded, which starts a new student-device run.
        $this->post(route('assessments.create.start'));
        $this->completeWithDetailsOnStudentDevice((string) session('assessment_wizard.remote_short_code'), $this->version, $this->studentDetails('Mia', 'T.', 'Torres'));
        $this->actingAs($this->owner);
        $this->post(route('assessments.create.remote.submit'), ['privacy_consent' => '1']);
        $this->post(route('assessments.create.start'))->assertRedirect(route('assessments.create.result'));
        $this->post(route('assessments.create.discard'))->assertStatus(303)->assertRedirect(route('assessments.create.remote'));
        $this->assertNull(session('assessment_wizard.responses'));
        $this->assertNull(session('assessment_wizard.student_data'));
        $this->assertTrue(RemoteAssessmentDraft::query()->sole()->collects_identity);
        $this->assertSame(1, Assessment::query()->count());
        $this->assertSame(0, Student::query()->where('last_name', 'Torres')->count());
    }

    public function test_take_again_works_on_the_student_device_only(): void
    {
        $student = Student::factory()->create(['first_name' => 'Ramon', 'middle_name' => 'C.', 'last_name' => 'Dizon']);
        $this->get(route('assessments.create.retake', $student))->assertRedirect(route('assessments.create.questionnaire'));

        $step2 = $this->get(route('assessments.create.questionnaire'))->assertOk();
        $step2->assertSee('Retake: Questionnaire')
            ->assertSee('The student answers on the student PC')
            ->assertSee('action="'.route('assessments.create.remote.store').'"', false)
            ->assertDontSee('name="responses[', false)
            ->assertDontSee('Where will the student answer?')
            ->assertDontSee('Continue to Review')
            ->assertSee(route('students.show', $student), false);

        ['short_code' => $code] = $this->sendToStudentDevice();
        $device = $this->claimWithCode($code);
        $this->consentOnDevice($device);
        $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk()
            ->assertSee('data-student-device="questionnaire"', false)
            ->assertDontSee('data-student-device-details', false)
            ->assertDontSee('name="first_name"', false);
        $this->answerAllOnDevice($device, $this->version);
        $this->studentRequest('POST', route('student-device.done'), [], $device, json: true)->assertOk();
        $this->actingAs($this->owner);
        $this->post(route('assessments.create.remote.submit'), ['privacy_consent' => '1'])->assertRedirect(route('assessments.create.result'));
        $this->reviewAndSaveAssessment()->assertRedirect();

        $this->assertSame($student->id, Assessment::query()->sole()->student_id);
        $this->assertSame(1, Student::query()->count());
    }

    public function test_a_new_students_step_two_has_no_back_to_step_one(): void
    {
        $this->post(route('assessments.create.start'));
        $this->completeWithDetailsOnStudentDevice((string) session('assessment_wizard.remote_short_code'), $this->version, $this->studentDetails());
        $this->actingAs($this->owner);
        $this->post(route('assessments.create.remote.submit'), ['privacy_consent' => '1']);

        $page = (string) $this->get(route('assessments.create.questionnaire'))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('#<a\b[^>]*href="'.preg_quote(route('assessments.create'), '#').'"#', $page);
        $this->assertStringContainsString('Student details (by the student)', $page);
    }

    public function test_the_duplicate_conflict_at_save_shows_on_the_start_page(): void
    {
        $this->post(route('assessments.create.start'));
        $this->completeWithDetailsOnStudentDevice((string) session('assessment_wizard.remote_short_code'), $this->version, $this->studentDetails('Ana', 'B.', 'Villareal'));
        $this->actingAs($this->owner);
        $this->post(route('assessments.create.remote.submit'), ['privacy_consent' => '1']);
        $this->get(route('assessments.create.result'));
        $existing = Student::factory()->create(['first_name' => 'Ana', 'middle_name' => 'B.', 'last_name' => 'Villareal']);

        $this->post(route('assessments.create.submit'), ['is_confirmed' => '1'])->assertRedirect(route('assessments.create'));

        $this->get(route('assessments.create'))->assertOk()
            ->assertSee('This student was registered while you were working')
            ->assertSee($existing->student_number)
            ->assertSee(route('assessments.create.retake', $existing), false)
            ->assertSee('data-start-page', false)
            ->assertDontSee('name="first_name"', false);
        $this->assertSame(0, RemoteAssessmentDraft::query()->count());
        $this->assertSame(0, Assessment::query()->count());
    }

    public function test_a_guidance_counselor_gets_403_on_the_start_routes(): void
    {
        $this->actingAs($this->guidanceCounselor());

        $this->post(route('assessments.create.start'))->assertForbidden();
        $this->post(route('assessments.create.discard'))->assertForbidden();
        $this->get(route('assessments.create'))->assertForbidden();
        $this->assertSame(0, RemoteAssessmentDraft::query()->count());
    }

    public function test_the_start_route_is_staff_only_and_in_the_staff_crawl(): void
    {
        $route = Route::getRoutes()->getByName('assessments.create.start');
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('role:psychometrician', $route->gatherMiddleware());
        $this->assertNotContains('student-device', $route->gatherMiddleware());

        // A student device holding only its cookie is sent to the login page.
        ['short_code' => $code] = $this->createRemoteDraft($this->owner);
        $device = $this->claimWithCode($code);
        foreach (['assessments.create.start', 'assessments.create.discard'] as $name) {
            $this->studentRequest('POST', route($name), device: $device)->assertRedirect(route('login'));
        }
        $this->assertSame(1, RemoteAssessmentDraft::query()->count());
    }

    public function test_with_the_flag_off_everything_is_as_before(): void
    {
        config(['remote_assessment.student_entry_only' => false]);

        $this->get(route('assessments.create'))->assertOk()
            ->assertSee('Step 1: Student Information')
            ->assertSee('name="first_name"', false)
            ->assertSee('name="privacy_consent"', false)
            ->assertSee('Continue to Questionnaire')
            ->assertSee('Let the student fill this in on their device')
            ->assertDontSee('data-start-page', false);
        $this->assertMatchesRegularExpression('#<a\b[^>]*href="'.preg_quote(route('assessments.create'), '#').'"#', (string) $this->get(route('psychometrician.dashboard'))->getContent());

        $this->submitStepOne('Lia', 'Santos');
        $this->get(route('assessments.create.questionnaire'))->assertOk()
            ->assertSee('Where will the student answer?')
            ->assertSee('name="responses[', false)
            ->assertSee('Continue to Review');
        $this->post(route('assessments.create.questionnaire.store'), ['responses' => $this->buildResponses($this->version, 1, 1, 1)])
            ->assertRedirect(route('assessments.create.result'));
        $this->assertSame(0, RemoteAssessmentDraft::query()->count());
    }
}
