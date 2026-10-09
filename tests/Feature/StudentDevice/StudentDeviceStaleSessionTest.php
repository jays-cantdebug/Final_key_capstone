<?php

declare(strict_types=1);

namespace Tests\Feature\StudentDevice;

use App\Models\QuestionnaireVersion;
use App\Models\RemoteAssessmentDraft;
use App\Services\RemoteAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithDomainData;
use Tests\Concerns\InteractsWithStudentDevice;
use Tests\TestCase;

/**
 * A student PC left on /s/q after its session ended (submitted, cancelled,
 * expired, New code, declined) goes back to the code form on reload, with
 * the device cookie cleared, and the same reply whatever the reason. Wrong
 * codes and links keep the generic "not available" 404. When the session
 * ends while the questionnaire is open, the script goes to the code form.
 */
class StudentDeviceStaleSessionTest extends TestCase
{
    use InteractsWithDomainData;
    use InteractsWithStudentDevice;
    use RefreshDatabase;

    private QuestionnaireVersion $version;

    protected function setUp(): void
    {
        parent::setUp();
        config(['remote_assessment.student_consent' => true, 'remote_assessment.ttl_minutes' => 60]);
        $this->version = $this->createActiveQuestionnaireVersion();
    }

    public function test_every_ended_or_unknown_session_goes_to_the_code_form_with_one_identical_reply(): void
    {
        $replies = [
            'never claimed (no cookie)' => $this->studentRequest('GET', route('student-device.show')),
            'unknown cookie' => $this->studentRequest('GET', route('student-device.show'), device: str_repeat('x', 43)),
            'submitted' => $this->afterEnding(fn (RemoteAssessmentDraft $draft) => $draft->delete(), finish: true),
            'cancelled' => $this->afterEnding(fn (RemoteAssessmentDraft $draft) => $draft->delete()),
            'expired' => $this->afterEnding(fn () => $this->travel(61)->minutes()),
            'new code' => $this->afterEnding(fn (RemoteAssessmentDraft $draft) => app(RemoteAssessmentService::class)->reissue($draft)),
            'declined' => $this->afterEnding(fn (RemoteAssessmentDraft $draft, string $device) => $this->studentRequest('POST', route('student-device.decline'), device: $device)->assertOk(), consent: false),
        ];

        $fingerprints = [];
        foreach ($replies as $reason => $response) {
            $response->assertStatus(303)->assertRedirect(route('student-device.entry'));
            $response->assertCookieExpired(RemoteAssessmentService::DEVICE_COOKIE);
            $this->assertSame('', (string) $response->getContent(), $reason);
            $fingerprints[$reason] = $this->fingerprint($response);
        }

        $this->assertCount(1, array_unique($fingerprints), 'The reply must not differ by reason: '.json_encode($fingerprints));
    }

    public function test_the_code_form_then_takes_a_new_code(): void
    {
        $device = $this->afterEnding(fn (RemoteAssessmentDraft $draft) => $draft->delete(), returnDevice: true);

        $this->studentRequest('GET', route('student-device.show'), device: $device)->assertRedirect(route('student-device.entry'));
        $this->studentRequest('GET', route('student-device.entry'))->assertOk()->assertSee('name="code"', false);

        ['short_code' => $shortCode] = $this->createRemoteDraft($this->psychometrician());
        $this->claimWithCode($shortCode);
    }

    public function test_wrong_codes_and_links_still_get_the_generic_404(): void
    {
        $this->createRemoteDraft();

        foreach ([
            $this->studentRequest('POST', route('student-device.code'), ['code' => 'ZZZZ-ZZZZ']),
            $this->studentRequest('POST', route('student-device.claim', str_repeat('a', 43))),
            $this->studentRequest('GET', route('student-device.begin', str_repeat('a', 43))),
            $this->studentRequest('GET', '/s/nonexistent'),
        ] as $response) {
            $response->assertNotFound()->assertSee(__('student_device.unavailable_body'));
        }
    }

    public function test_the_questionnaire_script_goes_to_the_code_form_when_the_session_ends(): void
    {
        ['short_code' => $shortCode] = $this->createRemoteDraft();
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);

        $page = (string) $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk()->getContent();

        $this->assertStringContainsString('data-entry-url="'.route('student-device.entry').'"', $page);
        $this->assertStringNotContainsString('data-unavailable-template', $page, 'The "not available" swap-in is gone.');
        $this->assertStringNotContainsString(__('student_device.unavailable_body'), $page);

        $script = (string) file_get_contents(resource_path('js/student-device.js'));
        $this->assertStringNotContainsString('data-unavailable-template', $script);
        $this->assertSame(2, substr_count($script, 'dataset.entryUrl'), 'Both "draft ended" paths (details still open, answering) go to the code form.');
    }

    public function test_the_held_page_is_unchanged(): void
    {
        $page = view('student-device.held')->render();

        $this->assertStringContainsString('data-student-device="held"', $page);
        $this->assertStringContainsString(__('student_device.held_body'), $page);
    }

    /**
     * Claim a fresh draft (optionally answer everything and press Done),
     * end it, then reload /s/q with that device's cookie.
     */
    private function afterEnding(callable $end, bool $finish = false, bool $returnDevice = false, bool $consent = true): TestResponse|string
    {
        ['draft' => $draft, 'short_code' => $shortCode] = $this->createRemoteDraft($this->psychometrician());
        $device = $this->claimWithCode($shortCode);

        if ($consent) {
            $this->consentOnDevice($device);
        }

        if ($finish) {
            $this->answerAllOnDevice($device, $this->version);
            $this->studentRequest('POST', route('student-device.done'), device: $device, json: true)->assertOk();
        }

        $end(RemoteAssessmentDraft::query()->findOrFail($draft->id), $device);

        if ($returnDevice) {
            return $device;
        }

        $response = $this->studentRequest('GET', route('student-device.show'), device: $device);
        $this->travelBack();

        return $response;
    }

    /**
     * Status, Location, body and every header except Date; the device
     * cookie's name and attributes (its value is encrypted afresh with a
     * random IV on every response, whatever the reason).
     */
    private function fingerprint(TestResponse $response): string
    {
        $headers = collect($response->headers->allPreserveCase())
            ->except(['Date', 'Set-Cookie'])
            ->map(fn (array $values): string => implode(',', $values))
            ->sortKeys()
            ->all();
        $cookie = $response->getCookie(RemoteAssessmentService::DEVICE_COOKIE, decrypt: false);

        return json_encode([
            $response->getStatusCode(),
            $headers,
            (string) $response->getContent(),
            [$cookie?->getName(), $cookie?->getExpiresTime(), $cookie?->getPath(), $cookie?->isHttpOnly(), $cookie?->isSecure(), $cookie?->getSameSite()],
        ], JSON_THROW_ON_ERROR);
    }
}
