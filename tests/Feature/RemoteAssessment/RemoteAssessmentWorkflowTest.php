<?php

declare(strict_types=1);

namespace Tests\Feature\RemoteAssessment;

use App\Http\Controllers\AssessmentWizardController;
use App\Http\Controllers\RemoteAssessmentController;
use App\Models\Assessment;
use App\Models\DassResult;
use App\Models\QuestionnaireVersion;
use App\Models\RemoteAssessmentDraft;
use App\Models\Student;
use App\Models\User;
use App\Services\RemoteAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDomainData;
use Tests\Concerns\InteractsWithStudentDevice;
use Tests\TestCase;

/**
 * The Psychometrician's side of a student-device assessment: the Step 2
 * choice, the live page and its polling, New code, Return to student,
 * Restart on the new version, Cancel, Submit into the unchanged Step 3 and
 * final save, read-only Step 2 afterwards, and `administration_mode`.
 */
class RemoteAssessmentWorkflowTest extends TestCase
{
    use InteractsWithDomainData;
    use InteractsWithStudentDevice;
    use RefreshDatabase;

    private User $psychometrician;

    private QuestionnaireVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        config(['remote_assessment.student_consent' => true, 'remote_assessment.url' => 'http://192.168.1.10']);
        $this->seedOfficialThresholds();
        $this->version = $this->createActiveQuestionnaireVersion();
        $this->psychometrician = $this->psychometrician();
        $this->actingAs($this->psychometrician);
    }

    public function test_step_two_offers_the_student_device_for_a_new_student_and_for_take_again(): void
    {
        $this->submitStepOne();

        $this->get(route('assessments.create.questionnaire'))
            ->assertOk()
            ->assertSee(__('Send to student device'))
            ->assertSee('action="'.route('assessments.create.remote.store').'"', false)
            // The same-device form is still there, unchanged.
            ->assertSee('action="'.route('assessments.create.questionnaire.store').'"', false);

        $student = Student::factory()->create();
        $this->get(route('assessments.create.retake', $student))->assertRedirect(route('assessments.create.questionnaire'));

        $this->get(route('assessments.create.questionnaire'))
            ->assertOk()
            ->assertSee(__('Send to student device'))
            ->assertSee('action="'.route('assessments.create.remote.store').'"', false);
    }

    public function test_sending_creates_a_draft_pinned_to_the_active_version(): void
    {
        $this->submitStepOne();
        ['draft' => $draft, 'short_code' => $shortCode, 'token' => $token] = $this->sendToStudentDevice();

        $this->assertSame($this->version->id, $draft->questionnaire_version_id);
        $this->assertSame($this->psychometrician->id, $draft->psychometrician_id);
        $this->assertSame(RemoteAssessmentDraft::STATUS_PENDING, $draft->status);
        $this->assertNotSame('', $shortCode);
        $this->assertNotSame('', $token);
    }

    public function test_the_live_page_shows_the_code_address_note_and_countdown(): void
    {
        $this->freezeSecond();
        $this->submitStepOne('Mara', 'Lagdameo');
        ['short_code' => $shortCode] = $this->sendToStudentDevice();

        $this->get(route('assessments.create.remote'))
            ->assertOk()
            ->assertSee('Mara S. Lagdameo')
            ->assertSeeInOrder(['data-student-address', 'http://192.168.1.10/s', 'data-short-code', $shortCode], false)
            ->assertSee('On the student PC, open this address in Chrome (or use the desktop shortcut), then type the code.')
            ->assertSee('60:00')
            ->assertSee('Waiting for the student device')
            ->assertDontSee('data-loopback-warning', false)
            ->assertDontSee('data-duplicate-warning', false)
            ->assertDontSee('data-allowed-ips', false);
    }

    public function test_the_live_page_has_no_qr_code_and_no_scan_wording(): void
    {
        $this->submitStepOne();
        $this->sendToStudentDevice();

        $page = (string) $this->get(route('assessments.create.remote'))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-qr', $page);
        $this->assertSame(1, preg_match('#<div[^>]*x-show="state === \'pending\'".*?data-browser-advice.*?</p>#s', $page, $panel));
        $this->assertStringNotContainsString('<svg', $panel[0], 'The code panel has an image.');
        foreach (['scan', 'QR', 'tablet', 'phone', 'Begin'] as $word) {
            $this->assertDoesNotMatchRegularExpression('#\b'.$word.'\b#i', strip_tags($page), "The live page says \"{$word}\".");
        }
        $this->assertFalse(method_exists(RemoteAssessmentService::class, 'qrSvg'));
        $this->assertFalse(class_exists('BaconQrCode\Writer'));
    }

    public function test_copy_link_puts_the_link_only_in_the_buttons_data_attribute_with_the_warning(): void
    {
        $this->submitStepOne();
        ['token' => $token] = $this->sendToStudentDevice();
        $link = 'http://192.168.1.10/s/t/'.$token;

        $page = (string) $this->get(route('assessments.create.remote'))->assertOk()->getContent();

        // Exactly once, as the Copy link button's data-link — never as text,
        // never as an <a>, never anywhere else on the page.
        $this->assertSame(1, substr_count($page, $token));
        $this->assertMatchesRegularExpression('#<button\b[^>]*\bx-ref="button"[^>]*\bdata-link="'.preg_quote($link, '#').'"[^>]*>\s*Copy link\s*</button>#', $page);
        $this->assertStringNotContainsString($token, strip_tags($page));
        $this->assertDoesNotMatchRegularExpression('#<a\b[^>]*'.preg_quote($token, '#').'#', $page);
        $this->assertStringContainsString('Don’t paste the link into a public chat. It works once and expires with this session. Typing the code avoids passing the link through a messaging service.', $page);

        // Not in the polling JSON either.
        $this->assertStringNotContainsString($token, (string) $this->getJson(route('assessments.create.remote.status'))->assertOk()->getContent());

        // New code: a new link; the old one is dead on the student device.
        $this->post(route('assessments.create.remote.new-code'))->assertRedirect(route('assessments.create.remote'));
        $newToken = (string) session('assessment_wizard.remote_token');
        $this->assertNotSame($token, $newToken);
        $this->get(route('assessments.create.remote'))->assertOk()->assertSee('/s/t/'.$newToken, false)->assertDontSee($token, false);
        $this->studentRequest('GET', route('student-device.begin', $token))->assertNotFound();
        $this->studentRequest('GET', route('student-device.begin', $newToken))->assertOk();

        // Once a device has the draft, the link isn't on the page at all.
        $this->studentRequest('POST', route('student-device.claim', $newToken))->assertStatus(303);
        $this->actingAs($this->psychometrician);
        $this->get(route('assessments.create.remote'))->assertOk()
            ->assertDontSee($newToken, false)
            ->assertDontSee('data-copy-link-panel', false);
    }

    public function test_a_loopback_address_shows_a_warning(): void
    {
        foreach (['http://127.0.0.1:8000', 'http://localhost', 'http://normi.localhost', 'http://[::1]'] as $url) {
            config(['remote_assessment.url' => $url]);
            $this->assertTrue(app(RemoteAssessmentService::class)->baseUrlIsLoopback(), $url);
        }
        foreach (['http://192.168.1.10', 'https://normi.school.edu.ph', 'http://10.0.0.5:8080'] as $url) {
            config(['remote_assessment.url' => $url]);
            $this->assertFalse(app(RemoteAssessmentService::class)->baseUrlIsLoopback(), $url);
        }

        config(['remote_assessment.url' => 'http://127.0.0.1:8000']);
        $this->submitStepOne();
        $this->sendToStudentDevice();

        $this->get(route('assessments.create.remote'))
            ->assertOk()
            ->assertSee('data-loopback-warning', false)
            ->assertSee('http://127.0.0.1:8000/s');
    }

    public function test_the_extra_duplicate_recheck_warns_but_does_not_block(): void
    {
        $this->submitStepOne('Ana', 'Villareal', 'B.');
        // Registered from elsewhere after Step 1.
        Student::factory()->create(['first_name' => 'Ana', 'middle_name' => 'B.', 'last_name' => 'Villareal']);

        ['draft' => $draft] = $this->sendToStudentDevice();

        $this->assertModelExists($draft);
        $this->get(route('assessments.create.remote'))->assertOk()->assertSee('data-duplicate-warning', false);
    }

    public function test_polling_sends_everything_first_and_a_tiny_response_when_nothing_changed(): void
    {
        $this->submitStepOne();
        ['draft' => $draft, 'short_code' => $shortCode] = $this->sendToStudentDevice();

        $full = $this->getJson(route('assessments.create.remote.status'))->assertOk()->json();
        $this->assertSame('pending', $full['state']);
        $this->assertFalse($full['unchanged']);
        $this->assertSame(21, $full['total']);

        $tiny = $this->getJson(route('assessments.create.remote.status', ['rev' => $full['rev']]))->assertOk()->json();
        $this->assertEqualsCanonicalizing(['rev', 'last_seen_seconds', 'seconds_left', 'version_changed', 'unchanged'], array_keys($tiny));
        $this->assertTrue($tiny['unchanged']);

        $device = $this->claimWithCode($shortCode);
        $this->actingAs($this->psychometrician);

        $afterClaim = $this->getJson(route('assessments.create.remote.status', ['rev' => $full['rev']]))->assertOk()->json();
        $this->assertFalse($afterClaim['unchanged']);
        $this->assertSame('consent', $afterClaim['state']);
        $this->assertTrue($afterClaim['claimed']);
        $this->assertFalse($afterClaim['consented']);

        $this->consentOnDevice($device);
        $question = $this->version->questions->first();
        $this->studentRequest('POST', route('student-device.answer'), ['question_id' => $question->id, 'value' => 3], $device, json: true)->assertOk();
        $this->actingAs($this->psychometrician);

        $answering = $this->getJson(route('assessments.create.remote.status', ['rev' => $afterClaim['rev']]))->assertOk()->json();
        $this->assertSame('answering', $answering['state']);
        $this->assertTrue($answering['consented']);
        $this->assertSame([(string) $question->id => 3], $answering['answers']);
        $this->assertSame(1, $answering['answered']);
        $this->assertNotNull($answering['last_seen_seconds']);
    }

    public function test_polling_reports_a_second_device_trying_the_code(): void
    {
        $this->submitStepOne();
        ['short_code' => $shortCode] = $this->sendToStudentDevice();
        $this->claimWithCode($shortCode);
        $this->studentRequest('POST', route('student-device.code'), ['code' => $shortCode])->assertNotFound();
        $this->actingAs($this->psychometrician);

        $this->getJson(route('assessments.create.remote.status'))->assertOk()->assertJsonPath('refused_device_attempts', 1);
    }

    public function test_submit_feeds_the_answers_into_the_unchanged_review_and_save(): void
    {
        $this->submitStepOne('Lia', 'Santos');
        ['draft' => $draft, 'short_code' => $shortCode] = $this->sendToStudentDevice();
        $responses = $this->buildResponses($this->version, 21, 10, 4);

        $this->completeOnStudentDevice($shortCode, $this->version, $responses);
        $this->actingAs($this->psychometrician);

        $this->post(route('assessments.create.remote.submit'))->assertRedirect(route('assessments.create.result'));

        $this->assertModelMissing($draft);
        $staged = session('assessment_wizard.responses');
        ksort($staged);
        ksort($responses);
        $this->assertSame($responses, $staged);
        $this->assertSame($this->version->id, session('assessment_wizard.questionnaire_version_id'));
        $this->assertNull(session('assessment_wizard.remote_draft_id'));

        $this->get(route('assessments.create.result'))->assertOk()->assertSee('Lia S. Santos');
        $this->post(route('assessments.create.submit'), ['is_confirmed' => '1'])->assertRedirect();

        $assessment = Assessment::query()->with('result', 'responses')->latest('id')->firstOrFail();
        $this->assertSame(Assessment::ADMINISTRATION_STUDENT_DEVICE, $assessment->administration_mode);
        $this->assertSame($this->version->id, $assessment->questionnaire_version_id);
        $this->assertCount(21, $assessment->responses);
        $this->assertSame(21, $assessment->result->depression_raw_score);
        $this->assertSame(10, $assessment->result->anxiety_raw_score);
        $this->assertSame(4, $assessment->result->stress_raw_score);
        $this->assertSame('rule_based', $assessment->result->ai_provider);

        // The same answers given on this device score and classify identically.
        $sameDevice = $this->saveAssessmentThroughWizard($this->version, 21, 10, 4, firstName: 'Ben', lastName: 'Cruz');
        $sameDeviceResult = DassResult::query()->where('assessment_id', $sameDevice->id)->sole();
        foreach (['depression_final_score', 'anxiety_final_score', 'stress_final_score', 'depression_level', 'anxiety_level', 'stress_level'] as $field) {
            $this->assertSame($sameDeviceResult->{$field}, $assessment->result->{$field}, $field);
        }
        $this->assertNull($sameDevice->administration_mode);
    }

    public function test_the_students_own_acknowledgment_time_is_recorded_as_the_consent(): void
    {
        $this->travelTo(now()->setTime(9, 0));
        $this->submitStepOne('Lia', 'Santos');
        ['short_code' => $shortCode] = $this->sendToStudentDevice();

        $this->travelTo(now()->setTime(9, 7));
        $this->completeOnStudentDevice($shortCode, $this->version);
        $this->actingAs($this->psychometrician);
        $this->post(route('assessments.create.remote.submit'))->assertRedirect(route('assessments.create.result'));
        $this->reviewAndSaveAssessment()->assertRedirect();

        $student = Student::query()->where('last_name', 'Santos')->sole();
        $this->assertSame('09:07', $student->privacy_consent_at->format('H:i'));
    }

    public function test_take_again_requires_the_consent_box_on_submit_and_records_the_students_time(): void
    {
        $student = Student::factory()->create();
        $this->get(route('assessments.create.retake', $student));
        ['short_code' => $shortCode] = $this->sendToStudentDevice();

        $this->travelTo(now()->addMinutes(3)->startOfMinute());
        $consentTime = now()->copy();
        $this->completeOnStudentDevice($shortCode, $this->version);
        $this->actingAs($this->psychometrician);

        $this->get(route('assessments.create.remote'))->assertOk()->assertSee('name="privacy_consent"', false);
        $this->from(route('assessments.create.remote'))
            ->post(route('assessments.create.remote.submit'))
            ->assertRedirect(route('assessments.create.remote'))
            ->assertSessionHasErrors('privacy_consent');
        $this->assertNull(session('assessment_wizard.responses'));

        $this->travel(2)->minutes();
        $this->post(route('assessments.create.remote.submit'), ['privacy_consent' => '1'])->assertRedirect(route('assessments.create.result'));
        $this->reviewAndSaveAssessment()->assertRedirect();

        $assessment = Assessment::query()->latest('id')->firstOrFail();
        $this->assertSame($student->id, $assessment->student_id);
        $this->assertTrue($assessment->privacy_consent_at->equalTo($consentTime));
        $this->assertSame(Assessment::ADMINISTRATION_STUDENT_DEVICE, $assessment->administration_mode);
    }

    public function test_submit_is_refused_until_the_student_presses_done(): void
    {
        $this->submitStepOne();
        ['draft' => $draft, 'short_code' => $shortCode] = $this->sendToStudentDevice();
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $this->answerAllOnDevice($device, $this->version);
        $this->actingAs($this->psychometrician);

        $this->post(route('assessments.create.remote.submit'))
            ->assertRedirect(route('assessments.create.remote'))
            ->assertSessionHasErrors(['remote' => RemoteAssessmentController::NOT_LOCKED_MESSAGE]);

        $this->assertModelExists($draft);
        $this->assertNull(session('assessment_wizard.responses'));
    }

    public function test_after_a_remote_submit_step_two_is_read_only(): void
    {
        $this->submitStepOne();
        ['short_code' => $shortCode] = $this->sendToStudentDevice();
        $this->completeOnStudentDevice($shortCode, $this->version, value: 2);
        $this->actingAs($this->psychometrician);
        $this->post(route('assessments.create.remote.submit'))->assertRedirect(route('assessments.create.result'));
        $staged = session('assessment_wizard.responses');

        $page = $this->get(route('assessments.create.questionnaire'))->assertOk();
        $page->assertSee('These answers were given on the student device')
            ->assertDontSee('action="'.route('assessments.create.questionnaire.store').'"', false)
            ->assertSee('href="'.route('assessments.create.result').'"', false);
        // 21 statements x 4 answer buttons, every one disabled.
        $this->assertSame(84, preg_match_all('/<input[^>]*\sdisabled[\s>][^>]*>?/', $page->getContent()));
        $this->assertSame(84, substr_count($page->getContent(), 'type="radio"'));

        // Posting other answers anyway is refused and changes nothing.
        $this->post(route('assessments.create.questionnaire.store'), [
            'questionnaire_version_id' => $this->version->id,
            'responses' => $this->buildResponses($this->version, 0, 0, 0),
        ])->assertRedirect(route('assessments.create.questionnaire'))
            ->assertSessionHasErrors(['questionnaire' => AssessmentWizardController::ANSWERED_ON_STUDENT_DEVICE_MESSAGE]);

        $this->assertSame($staged, session('assessment_wizard.responses'));
    }

    public function test_sending_again_from_read_only_step_two_starts_a_fresh_draft(): void
    {
        $this->submitStepOne();
        ['short_code' => $shortCode] = $this->sendToStudentDevice();
        $this->completeOnStudentDevice($shortCode, $this->version);
        $this->actingAs($this->psychometrician);
        $this->post(route('assessments.create.remote.submit'));

        ['draft' => $draft] = $this->sendToStudentDevice();

        $this->assertSame(RemoteAssessmentDraft::STATUS_PENDING, $draft->status);
        $this->assertNull(session('assessment_wizard.responses'));
        $this->assertNull(session('assessment_wizard.administration_mode'));
    }

    public function test_new_code_keeps_the_answers_and_unbinds_the_device(): void
    {
        $this->submitStepOne();
        ['draft' => $draft, 'short_code' => $oldCode] = $this->sendToStudentDevice();
        $oldDevice = $this->claimWithCode($oldCode);
        $this->consentOnDevice($oldDevice);
        $question = $this->version->questions->first();
        $this->studentRequest('POST', route('student-device.answer'), ['question_id' => $question->id, 'value' => 3], $oldDevice, json: true)->assertOk();
        $this->actingAs($this->psychometrician);

        $this->post(route('assessments.create.remote.new-code'))->assertRedirect(route('assessments.create.remote'));
        $newCode = (string) session('assessment_wizard.remote_short_code');
        $this->assertNotSame($oldCode, $newCode);

        $this->studentRequest('GET', route('student-device.show'), device: $oldDevice)->assertNotFound();
        $this->studentRequest('POST', route('student-device.code'), ['code' => $oldCode])->assertNotFound();

        $newDevice = $this->claimWithCode($newCode);
        // Same student, consent already given: straight to the questions, answer kept.
        $this->studentRequest('GET', route('student-device.show'), device: $newDevice)
            ->assertOk()
            ->assertSee(__('student_device.counter', ['answered' => 1, 'total' => 21]));
        $this->assertSame([$question->id => 3], $draft->fresh()->responses);
    }

    public function test_return_to_student_unlocks_for_editing(): void
    {
        $this->submitStepOne();
        ['draft' => $draft, 'short_code' => $shortCode] = $this->sendToStudentDevice();
        $device = $this->completeOnStudentDevice($shortCode, $this->version);
        $this->actingAs($this->psychometrician);

        $this->post(route('assessments.create.remote.return'))->assertRedirect(route('assessments.create.remote'));

        $this->assertSame(RemoteAssessmentDraft::STATUS_ANSWERING, $draft->fresh()->status);
        $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)->assertExactJson(['state' => 'answering']);
        $this->studentRequest('POST', route('student-device.answer'), ['question_id' => $this->version->questions->first()->id, 'value' => 0], $device, json: true)->assertOk();
        $this->studentRequest('POST', route('student-device.done'), [], $device, json: true)->assertOk();

        // Returning a draft that isn't locked does nothing.
        $this->actingAs($this->psychometrician);
        $this->post(route('assessments.create.remote.return'));
        $this->post(route('assessments.create.remote.return'))->assertSessionHasErrors('remote');
    }

    public function test_a_version_change_blocks_submit_until_restarted_on_the_new_version(): void
    {
        $this->submitStepOne();
        ['draft' => $draft, 'short_code' => $shortCode] = $this->sendToStudentDevice();
        $device = $this->completeOnStudentDevice($shortCode, $this->version);

        $this->version->update(['status' => QuestionnaireVersion::STATUS_ARCHIVED]);
        $newVersion = $this->createActiveQuestionnaireVersion();
        $this->actingAs($this->psychometrician);

        $this->getJson(route('assessments.create.remote.status'))->assertJsonPath('version_changed', true);
        $this->post(route('assessments.create.remote.submit'))
            ->assertSessionHasErrors(['remote' => RemoteAssessmentController::VERSION_CHANGED_MESSAGE]);
        $this->assertNull(session('assessment_wizard.responses'));

        $this->post(route('assessments.create.remote.restart'))->assertRedirect(route('assessments.create.remote'));

        $draft->refresh();
        $this->assertSame($newVersion->id, $draft->questionnaire_version_id);
        $this->assertNull($draft->responses);
        $this->assertSame(RemoteAssessmentDraft::STATUS_ANSWERING, $draft->status);
        $this->getJson(route('assessments.create.remote.status'))->assertJsonPath('version_changed', false);

        // The device reloads onto the new questions; then it can finish and be submitted.
        $this->studentRequest('GET', route('student-device.state', ['version' => $this->version->id]), device: $device, json: true)->assertExactJson(['state' => 'reload']);
        $this->answerAllOnDevice($device, $newVersion);
        $this->studentRequest('POST', route('student-device.done'), ['version' => $newVersion->id], $device, json: true)->assertOk();
        $this->actingAs($this->psychometrician);
        $this->post(route('assessments.create.remote.submit'))->assertRedirect(route('assessments.create.result'));
        $this->assertSame($newVersion->id, session('assessment_wizard.questionnaire_version_id'));
    }

    public function test_cancel_discards_the_draft_and_returns_to_the_choice(): void
    {
        $this->submitStepOne();
        ['draft' => $draft, 'short_code' => $shortCode] = $this->sendToStudentDevice();
        $device = $this->claimWithCode($shortCode);
        $this->actingAs($this->psychometrician);

        $this->delete(route('assessments.create.remote.cancel'))->assertRedirect(route('assessments.create.questionnaire'));

        $this->assertModelMissing($draft);
        $this->assertNull(session('assessment_wizard.remote_draft_id'));
        $this->assertNull(session('assessment_wizard.remote_short_code'));
        $this->assertNotNull(session('assessment_wizard.student_data'));
        $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)->assertNotFound();
        $this->actingAs($this->psychometrician);
        $this->get(route('assessments.create.questionnaire'))->assertOk()->assertSee(__('Send to student device'));
    }

    public function test_a_declined_notice_is_shown_and_can_be_sent_again(): void
    {
        $this->submitStepOne();
        ['short_code' => $shortCode] = $this->sendToStudentDevice();
        $device = $this->claimWithCode($shortCode);
        $this->studentRequest('POST', route('student-device.decline'), device: $device)->assertOk();
        $this->actingAs($this->psychometrician);

        $this->getJson(route('assessments.create.remote.status'))->assertOk()->assertJsonPath('state', 'declined')->assertJsonPath('answers', []);
        $this->get(route('assessments.create.remote'))->assertOk()->assertSee('The student declined the privacy notice');
        $this->post(route('assessments.create.remote.new-code'))->assertSessionHasErrors('remote');

        ['draft' => $draft] = $this->sendToStudentDevice();
        $this->assertSame(RemoteAssessmentDraft::STATUS_PENDING, $draft->status);
    }

    public function test_an_expired_draft_shows_expired_and_sends_no_answers(): void
    {
        $this->submitStepOne();
        ['short_code' => $shortCode] = $this->sendToStudentDevice();
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $this->answerAllOnDevice($device, $this->version);
        $this->actingAs($this->psychometrician);

        $this->travel(59)->minutes();
        $this->getJson(route('assessments.create.remote.status'))->assertOk()->assertJsonPath('state', 'answering');

        $this->travel(2)->minutes();
        // Pruned on this poll: the draft is gone.
        $this->getJson(route('assessments.create.remote.status'))->assertNotFound()->assertExactJson(['state' => 'gone']);
        $this->assertSame(0, RemoteAssessmentDraft::query()->count());
    }

    public function test_the_live_page_answer_grid_is_read_only(): void
    {
        $this->submitStepOne();
        ['short_code' => $shortCode] = $this->sendToStudentDevice();
        $this->completeOnStudentDevice($shortCode, $this->version, value: 2);
        $this->actingAs($this->psychometrician);

        $page = $this->get(route('assessments.create.remote'))->assertOk();

        $this->assertSame(0, substr_count($page->getContent(), 'type="radio"'));
        $this->assertSame(21, substr_count($page->getContent(), 'data-selected'));
        $page->assertSee('The student pressed Done');
    }
}
