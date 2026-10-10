<?php

declare(strict_types=1);

namespace Tests\Feature\RemoteAssessment;

use App\Http\Controllers\RemoteAssessmentController;
use App\Models\QuestionnaireVersion;
use App\Models\RemoteAssessmentDraft;
use App\Models\User;
use App\Services\RemoteAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithDomainData;
use Tests\Concerns\InteractsWithStudentDevice;
use Tests\TestCase;

/**
 * "Add 15 minutes" on the live page (docs/BUG_LOG.md N7): never past 120
 * minutes from creation, never after Done or Submit; and the student
 * device's neutral notice in the last 5 minutes while the student can
 * still answer (no PII, nothing else added to the device's JSON).
 */
class RemoteDraftExtendTest extends TestCase
{
    use InteractsWithDomainData;
    use InteractsWithStudentDevice;
    use RefreshDatabase;

    private QuestionnaireVersion $version;

    private User $psychometrician;

    protected function setUp(): void
    {
        parent::setUp();

        config(['remote_assessment.student_consent' => true, 'remote_assessment.ttl_minutes' => 60, 'remote_assessment.extend_minutes' => 15, 'remote_assessment.max_lifetime_minutes' => 120]);
        $this->seedOfficialThresholds();
        $this->version = $this->createActiveQuestionnaireVersion();
        $this->psychometrician = $this->psychometrician();
        $this->freezeSecond();
    }

    public function test_each_press_adds_15_minutes_up_to_120_minutes_from_creation(): void
    {
        ['draft' => $draft] = $this->startDraft();
        $created = $draft->created_at->copy();

        foreach ([75, 90, 105, 120] as $minutes) {
            $this->extend()->assertRedirect(route('assessments.create.remote'))->assertSessionHas('status');
            $this->assertTrue($draft->fresh()->expires_at->equalTo($created->copy()->addMinutes($minutes)), "after a press: {$minutes}");
        }

        // At the maximum: refused, nothing changes, and the button is gone.
        $this->extend()->assertSessionHasErrors(['remote' => RemoteAssessmentController::CANNOT_EXTEND_MESSAGE]);
        $this->assertTrue($draft->fresh()->expires_at->equalTo($created->copy()->addMinutes(120)));
        $this->actingAs($this->psychometrician)->get(route('assessments.create.remote'))->assertOk()->assertDontSee('data-extend', false);
    }

    public function test_a_press_near_the_maximum_stops_exactly_at_it(): void
    {
        config(['remote_assessment.extend_minutes' => 25]);
        ['draft' => $draft] = $this->startDraft();
        $created = $draft->created_at->copy();

        $this->extend();
        $this->extend();
        $this->assertTrue($draft->fresh()->expires_at->equalTo($created->copy()->addMinutes(110)));

        $this->extend()->assertSessionHas('status');
        $this->assertTrue($draft->fresh()->expires_at->equalTo($created->copy()->addMinutes(120)));
    }

    public function test_the_live_page_offers_it_while_the_student_can_still_answer(): void
    {
        ['short_code' => $shortCode] = $this->startDraft();

        $this->actingAs($this->psychometrician)->get(route('assessments.create.remote'))->assertOk()->assertSee('Add 15 minutes');

        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $this->actingAs($this->psychometrician)->get(route('assessments.create.remote'))->assertOk()->assertSee('data-extend', false);
        $this->extend()->assertSessionHas('status');
    }

    public function test_no_extension_after_done(): void
    {
        ['draft' => $draft, 'short_code' => $shortCode] = $this->startDraft();
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $this->answerAllOnDevice($device, $this->version);
        $this->studentRequest('POST', route('student-device.done'), [], $device, json: true)->assertOk();
        $expiry = $draft->fresh()->expires_at->copy();

        $this->extend()->assertSessionHasErrors(['remote' => RemoteAssessmentController::CANNOT_EXTEND_MESSAGE]);
        $this->assertTrue($draft->fresh()->expires_at->equalTo($expiry));
        $this->actingAs($this->psychometrician)->get(route('assessments.create.remote'))->assertOk()->assertDontSee('data-extend', false);
    }

    public function test_no_extension_after_submit(): void
    {
        ['draft' => $draft, 'short_code' => $shortCode] = $this->startDraft();
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $this->answerAllOnDevice($device, $this->version);
        $this->studentRequest('POST', route('student-device.done'), [], $device, json: true)->assertOk();
        $this->actingAs($this->psychometrician)->post(route('assessments.create.remote.submit'), ['privacy_consent' => '1'])
            ->assertRedirect(route('assessments.create.result'));
        $this->assertNull(RemoteAssessmentDraft::query()->find($draft->id));

        // The draft is gone: the action has nothing to extend.
        $this->extend()->assertNotFound();
    }

    public function test_only_the_owning_psychometrician_can_extend(): void
    {
        ['draft' => $draft] = $this->startDraft();
        $expiry = $draft->expires_at->copy();

        $this->actingAs($this->guidanceCounselor())->post(route('assessments.create.remote.extend'))->assertForbidden();
        $this->actingAs($this->psychometrician(['email' => 'second.psych@example.test']))->post(route('assessments.create.remote.extend'))->assertNotFound();

        $this->assertTrue($draft->fresh()->expires_at->equalTo($expiry));
    }

    public function test_the_device_warns_in_the_last_5_minutes_while_answering_only(): void
    {
        ['short_code' => $shortCode] = $this->startDraft();
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $notice = __('student_device.closing_soon');
        $this->assertSame('This session will close in 5 minutes. Please finish your answers.', $notice);

        // 6 minutes left: no notice, the reply is just {state}.
        $this->travel(54)->minutes();
        $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)->assertOk()->assertExactJson(['state' => 'answering']);
        $page = (string) $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<p [^>]*data-closing-soon[^>]*\bhidden\b#', $page);

        // Exactly 5 minutes left: the notice, on the page and in the poll.
        $this->travel(1)->minutes();
        $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)
            ->assertOk()->assertExactJson(['state' => 'answering', 'closing_soon' => true]);
        $page = (string) $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('#<p [^>]*data-closing-soon[^>]*\bhidden\b#', $page);
        $this->assertStringContainsString($notice, $page);
    }

    public function test_no_warning_after_done_even_in_the_last_minutes(): void
    {
        ['short_code' => $shortCode] = $this->startDraft();
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $this->answerAllOnDevice($device, $this->version);
        $this->travel(57)->minutes();
        $this->studentRequest('POST', route('student-device.done'), [], $device, json: true)->assertOk();

        // The Done grace keeps it alive; the thank-you page never gets the notice.
        $this->travel(12)->minutes();
        $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)->assertOk()->assertExactJson(['state' => 'locked']);
    }

    public function test_adding_time_clears_the_warning_and_the_device_cookie_follows_the_new_expiry(): void
    {
        ['draft' => $draft, 'short_code' => $shortCode] = $this->startDraft();
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);

        $this->travel(56)->minutes();
        $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)->assertExactJson(['state' => 'answering', 'closing_soon' => true]);

        $this->extend()->assertSessionHas('status');
        $newExpiry = $draft->fresh()->expires_at;

        $response = $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)->assertOk()->assertExactJson(['state' => 'answering']);
        $cookie = $response->getCookie(RemoteAssessmentService::DEVICE_COOKIE, decrypt: false);
        $this->assertNotNull($cookie);
        $this->assertSame($newExpiry->getTimestamp(), $cookie->getExpiresTime());

        // Still answering past the original 60 minutes.
        $this->travel(10)->minutes();
        $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)->assertOk()->assertJsonPath('state', 'answering');
    }

    public function test_an_unknown_device_still_gets_the_generic_404_with_nothing_added(): void
    {
        $this->startDraft();
        $this->travel(56)->minutes();

        $this->studentRequest('GET', route('student-device.state'), device: str_repeat('x', 43), json: true)
            ->assertNotFound()->assertExactJson(['state' => 'unavailable'])->assertCookieMissing(RemoteAssessmentService::DEVICE_COOKIE);
    }

    /**
     * @return array{draft: RemoteAssessmentDraft, token: string, short_code: string}
     */
    private function startDraft(): array
    {
        $this->actingAs($this->psychometrician);
        $this->submitStepOne();

        return $this->sendToStudentDevice();
    }

    private function extend(): TestResponse
    {
        return $this->actingAs($this->psychometrician)->from(route('assessments.create.remote'))->post(route('assessments.create.remote.extend'));
    }
}
