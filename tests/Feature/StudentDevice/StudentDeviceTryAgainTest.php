<?php

declare(strict_types=1);

namespace Tests\Feature\StudentDevice;

use App\Models\Student;
use App\Services\RemoteAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\Concerns\InteractsWithDomainData;
use Tests\Concerns\InteractsWithStudentDevice;
use Tests\TestCase;

/**
 * The generic "Not available" page has one "Try again" button back to the
 * code form: a plain GET form to /s with no fields and no script, the same
 * bytes whatever the reason. No other student-device page has it.
 */
class StudentDeviceTryAgainTest extends TestCase
{
    use InteractsWithDomainData;
    use InteractsWithStudentDevice;
    use RefreshDatabase;

    public function test_every_reason_gets_the_same_page_with_the_try_again_button(): void
    {
        $this->createActiveQuestionnaireVersion();
        $ip = fn (int $n): array => ['REMOTE_ADDR' => "10.0.0.{$n}"];
        $replies = [];

        $replies['wrong code'] = [404, $this->studentRequest('POST', route('student-device.code'), ['code' => 'ZZZZ-ZZZZ'], server: $ip(1))];

        ['short_code' => $used] = $this->createRemoteDraft();
        $this->claimWithCode($used);
        $replies['used code'] = [404, $this->studentRequest('POST', route('student-device.code'), ['code' => $used], server: $ip(2))];

        ['draft' => $draft, 'short_code' => $replaced] = $this->createRemoteDraft();
        app(RemoteAssessmentService::class)->reissue($draft);
        $replies['replaced code (New code)'] = [404, $this->studentRequest('POST', route('student-device.code'), ['code' => $replaced], server: $ip(3))];

        ['short_code' => $expired, 'token' => $expiredToken] = $this->createRemoteDraft();
        $this->travel(61)->minutes();
        $replies['expired code'] = [404, $this->studentRequest('POST', route('student-device.code'), ['code' => $expired], server: $ip(4))];
        $replies['expired link'] = [404, $this->studentRequest('GET', route('student-device.begin', $expiredToken), server: $ip(5))];
        $this->travelBack();

        $replies['unknown path'] = [404, $this->studentRequest('GET', '/s/nonexistent', server: $ip(6))];
        $replies['wrong method'] = [405, $this->studentRequest('GET', '/s/consent', server: $ip(7))];

        for ($i = 0; $i < 10; $i++) {
            $this->studentRequest('POST', route('student-device.code'), ['code' => 'ZZZZ-ZZZZ'], server: $ip(8))->assertNotFound();
        }
        $replies['lockout'] = [429, $this->studentRequest('POST', route('student-device.code'), ['code' => 'ZZZZ-ZZZZ'], server: $ip(8))];

        config(['remote_assessment.allowed_ips' => ['10.0.0.1']]);
        $replies['refused address'] = [404, $this->studentRequest('GET', route('student-device.entry'), server: $ip(9))];
        config(['remote_assessment.allowed_ips' => []]);

        $this->partialMock(RemoteAssessmentService::class)
            ->shouldReceive('claimWithShortCode')->andThrow(new RuntimeException('forced failure'));
        $replies['server error'] = [404, $this->studentRequest('POST', route('student-device.code'), ['code' => 'ABCD-EFGH'], server: $ip(10))];

        $button = '<form method="GET" action="'.route('student-device.entry').'" class="mx-auto mt-6 max-w-md text-center">';
        $bodies = [];

        /** @var TestResponse $response */
        foreach ($replies as $reason => [$status, $response]) {
            $response->assertStatus($status);
            $body = (string) $response->getContent();

            $this->assertSame(1, substr_count($body, $button), "{$reason}: the Try again form");
            $this->assertSame(1, substr_count($body, '>'.__('student_device.unavailable_retry').'</button>'), "{$reason}: the button");
            $this->assertSame(1, substr_count($body, '<form'), "{$reason}: one form only");
            $this->assertStringNotContainsString('<input', $body, "{$reason}: no fields, so the click goes to plain /s");
            $this->assertStringNotContainsString('<script>', $body, "{$reason}: no inline script");
            $this->assertStringNotContainsString(' style=', $body, "{$reason}: no inline style");
            $this->assertStringContainsString("form-action 'self'", (string) $response->headers->get('Content-Security-Policy'));
            $bodies[$reason] = $body;
        }

        $this->assertCount(1, array_unique($bodies), 'The page must be byte-identical for every reason.');
    }

    public function test_the_button_takes_the_student_back_to_the_code_form(): void
    {
        $this->studentRequest('GET', route('student-device.entry'))
            ->assertOk()
            ->assertSee('name="code"', false)
            ->assertDontSee(__('student_device.unavailable_retry'));
    }

    public function test_no_other_student_device_page_has_the_button(): void
    {
        $version = $this->createActiveQuestionnaireVersion();
        $pages = [];

        $pages['code form'] = $this->studentRequest('GET', route('student-device.entry'))->assertOk();
        $pages['staff browser'] = $this->studentRequest('GET', route('student-device.entry'), cookies: [Auth::guard('web')->getRecallerName() => '1|token|hash'])->assertForbidden();

        ['token' => $token] = $this->createRemoteDraft();
        $pages['begin (link)'] = $this->studentRequest('GET', route('student-device.begin', $token))->assertOk();

        ['short_code' => $shortCode] = $this->createRemoteDraft();
        $device = $this->claimWithCode($shortCode);
        $pages['consent'] = $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk();
        $this->consentOnDevice($device);
        $pages['questionnaire'] = $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk();
        $this->answerAllOnDevice($device, $version);
        $this->studentRequest('POST', route('student-device.done'), device: $device, json: true)->assertOk();
        $pages['thank-you'] = $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk()->assertSee(__('student_device.thanks_body'));

        ['short_code' => $shortCode] = $this->createRemoteDraft();
        $pages['declined'] = $this->studentRequest('POST', route('student-device.decline'), device: $this->claimWithCode($shortCode))->assertOk();

        Student::factory()->create(['first_name' => 'Rhea', 'middle_name' => 'Dalisay', 'last_name' => 'Baculio']);
        ['short_code' => $shortCode] = $this->createRemoteDraft(collectsIdentity: true);
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $pages['details and questionnaire'] = $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk();
        $this->sendDetailsOnDevice($device, $this->studentDetails('Rhea', 'D.', 'Baculio'));
        $pages['held'] = $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk()->assertSee('data-student-device="held"', false);

        foreach ($pages as $label => $response) {
            $body = (string) $response->getContent();
            $this->assertStringNotContainsString(__('student_device.unavailable_retry'), $body, "{$label}: no Try again");
            $this->assertStringNotContainsString('method="GET"', $body, "{$label}: no GET form");
        }
    }
}
