<?php

declare(strict_types=1);

namespace Tests\Feature\StudentDevice;

use App\Models\Assessment;
use App\Models\QuestionnaireVersion;
use App\Models\RemoteAssessmentDraft;
use App\Services\RemoteAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithDomainData;
use Tests\Concerns\InteractsWithStudentDevice;
use Tests\TestCase;

/**
 * The optional allowlist for the student device (REMOTE_ASSESSMENT_ALLOWED_IPS,
 * RestrictStudentDeviceNetwork): listed addresses and ranges get in; any
 * other address gets exactly the 404 an invalid code gets, before any work;
 * a forged X-Forwarded-For doesn't help; invalid entries fail closed; the
 * list is shown to the Psychometrician only. Empty: nothing changes. Also:
 * no X-RateLimit-* headers on any student-device response.
 */
class StudentDeviceNetworkTest extends TestCase
{
    use InteractsWithDomainData;
    use InteractsWithStudentDevice;
    use RefreshDatabase;

    private const STUDENT_PC = '192.168.1.20';

    private const OTHER_PC = '192.168.1.99';

    private QuestionnaireVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        config(['remote_assessment.student_consent' => true]);
        $this->seedOfficialThresholds();
        $this->version = $this->createActiveQuestionnaireVersion();
    }

    public function test_an_empty_allowlist_changes_nothing(): void
    {
        config(['remote_assessment.allowed_ips' => []]);
        ['short_code' => $shortCode] = $this->createRemoteDraft();

        foreach (['127.0.0.1', self::OTHER_PC, '203.0.113.9', '2001:db8::5'] as $ip) {
            $this->studentRequest('GET', route('student-device.entry'), server: ['REMOTE_ADDR' => $ip])->assertOk()->assertSee(__('student_device.code_heading'));
        }

        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $this->answerAllOnDevice($device, $this->version);
        $this->studentRequest('POST', route('student-device.done'), [], $device, json: true)->assertOk()->assertExactJson(['state' => 'locked']);
    }

    public function test_listed_addresses_ranges_and_ipv6_get_in(): void
    {
        config(['remote_assessment.allowed_ips' => [self::STUDENT_PC, '10.20.30.0/24', 'fd00:1::/64', '2001:db8::7']]);

        foreach ([self::STUDENT_PC, '10.20.30.1', '10.20.30.254', 'fd00:1::abcd', '2001:db8::7'] as $ip) {
            $this->studentRequest('GET', route('student-device.entry'), server: ['REMOTE_ADDR' => $ip])->assertOk()->assertSee(__('student_device.code_heading'));
        }

        foreach ([self::OTHER_PC, '10.20.31.1', 'fd00:2::1', '2001:db8::8', '127.0.0.1', '::1'] as $ip) {
            $this->studentRequest('GET', route('student-device.entry'), server: ['REMOTE_ADDR' => $ip])->assertNotFound();
        }
    }

    public function test_another_address_gets_exactly_the_404_an_invalid_code_gets(): void
    {
        config(['remote_assessment.allowed_ips' => [self::STUDENT_PC]]);
        ['draft' => $draft, 'short_code' => $shortCode] = $this->createRemoteDraft();

        // What a listed PC gets for a wrong code, page and JSON.
        $invalidCode = $this->studentRequest('POST', route('student-device.code'), ['code' => 'ZZZZ-ZZZZ'], server: ['REMOTE_ADDR' => self::STUDENT_PC])->assertNotFound();
        $invalidJson = $this->studentRequest('GET', route('student-device.state'), json: true, server: ['REMOTE_ADDR' => self::STUDENT_PC])->assertNotFound();

        // Another PC, even with the RIGHT code: the same reply, byte for byte.
        $refused = [
            'POST /s (right code)' => $this->studentRequest('POST', route('student-device.code'), ['code' => $shortCode], server: ['REMOTE_ADDR' => self::OTHER_PC]),
            'GET /s' => $this->studentRequest('GET', route('student-device.entry'), server: ['REMOTE_ADDR' => self::OTHER_PC]),
            'GET /s/q' => $this->studentRequest('GET', route('student-device.show'), device: 'some-device', server: ['REMOTE_ADDR' => self::OTHER_PC]),
            'GET unknown /s path' => $this->studentRequest('GET', '/s/nothing-here', server: ['REMOTE_ADDR' => self::OTHER_PC]),
        ];
        foreach ($refused as $label => $response) {
            $this->assertSame($this->comparable($invalidCode), $this->comparable($response), $label);
        }

        $refusedJson = [
            'GET /s/state' => $this->studentRequest('GET', route('student-device.state'), device: 'some-device', json: true, server: ['REMOTE_ADDR' => self::OTHER_PC]),
            'POST /s/answer' => $this->studentRequest('POST', route('student-device.answer'), ['question_id' => 1, 'value' => 1], 'some-device', json: true, server: ['REMOTE_ADDR' => self::OTHER_PC]),
            // A wrong method is the same 404 for a listed PC too.
            'PUT /s/answer' => $this->studentRequest('PUT', route('student-device.answer'), [], json: true, server: ['REMOTE_ADDR' => self::OTHER_PC]),
        ];
        foreach ($refusedJson as $label => $response) {
            $this->assertSame($this->comparable($invalidJson), $this->comparable($response), $label);
        }
        $this->studentRequest('PUT', route('student-device.answer'), [], json: true, server: ['REMOTE_ADDR' => self::STUDENT_PC])->assertNotFound()->assertExactJson(['state' => 'unavailable']);

        // Nothing hints at an allowlist, and the right code was not used.
        foreach ([...$refused, ...$refusedJson] as $label => $response) {
            $haystack = strtolower((string) $response->getContent()."\n".$response->headers);
            foreach (['allow', 'ip address', self::STUDENT_PC, self::OTHER_PC, 'network', 'forbidden'] as $term) {
                $this->assertStringNotContainsString($term, $haystack, "{$label} contains \"{$term}\".");
            }
        }
        $this->assertSame(RemoteAssessmentDraft::STATUS_PENDING, $draft->fresh()->status);
        $this->assertNull($draft->fresh()->device_hash);
        $this->assertSame(0, $draft->fresh()->refused_device_attempts);
    }

    public function test_a_refused_address_is_answered_before_any_other_work(): void
    {
        config(['remote_assessment.allowed_ips' => [self::STUDENT_PC]]);
        $this->createRemoteDraft();
        $failedKey = 'student-device-failed-entry|'.self::OTHER_PC;

        DB::flushQueryLog();
        DB::enableQueryLog();
        for ($i = 0; $i < 15; $i++) {
            $this->studentRequest('POST', route('student-device.code'), ['code' => 'ZZZZ-ZZZZ'], server: ['REMOTE_ADDR' => self::OTHER_PC])->assertNotFound();
        }
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // No database query, no failed-entry count, no page limiter hit:
        // even after 15 tries it is still the 404, never a 429.
        $this->assertSame([], $queries);
        $this->assertSame(0, RateLimiter::attempts($failedKey));
        $this->assertSame(0, RateLimiter::attempts($this->pageLimiterKey(self::OTHER_PC)));
        $this->studentRequest('POST', route('student-device.code'), ['code' => 'ZZZZ-ZZZZ'], server: ['REMOTE_ADDR' => self::OTHER_PC])->assertNotFound();

        // The same requests from the listed PC do reach the limiters (so the
        // keys above are the right ones).
        $this->studentRequest('POST', route('student-device.code'), ['code' => 'ZZZZ-ZZZZ'], server: ['REMOTE_ADDR' => self::STUDENT_PC])->assertNotFound();
        $this->assertSame(1, RateLimiter::attempts('student-device-failed-entry|'.self::STUDENT_PC));
        $this->assertSame(1, RateLimiter::attempts($this->pageLimiterKey(self::STUDENT_PC)));

        // A staff-browser cookie isn't even looked at.
        $this->studentRequest('GET', route('student-device.entry'), cookies: [(string) config('session.cookie') => 'anything'], server: ['REMOTE_ADDR' => self::OTHER_PC])->assertNotFound();
    }

    public function test_a_forged_x_forwarded_for_does_not_bypass_the_allowlist(): void
    {
        config(['remote_assessment.allowed_ips' => [self::STUDENT_PC]]);
        ['short_code' => $shortCode] = $this->createRemoteDraft();

        foreach ([
            ['HTTP_X_FORWARDED_FOR' => self::STUDENT_PC],
            ['HTTP_X_FORWARDED_FOR' => self::STUDENT_PC.', 10.0.0.1'],
            ['HTTP_X_REAL_IP' => self::STUDENT_PC],
            ['HTTP_FORWARDED' => 'for='.self::STUDENT_PC],
            ['HTTP_CLIENT_IP' => self::STUDENT_PC],
        ] as $forged) {
            $this->studentRequest('POST', route('student-device.code'), ['code' => $shortCode], server: ['REMOTE_ADDR' => self::OTHER_PC, ...$forged])
                ->assertNotFound();
        }
        $this->assertSame(RemoteAssessmentDraft::STATUS_PENDING, RemoteAssessmentDraft::query()->sole()->status);

        // Only the trusted proxy (127.0.0.1, bootstrap/app.php) may say who
        // the client is, and then its word is used both ways.
        $this->studentRequest('GET', route('student-device.entry'), server: ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => self::OTHER_PC])->assertNotFound();
        $this->studentRequest('GET', route('student-device.entry'), server: ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => self::STUDENT_PC])->assertOk();
    }

    public function test_invalid_entries_match_nothing_and_fail_closed(): void
    {
        config(['remote_assessment.allowed_ips' => ['192.168.1.300', 'student-pc', '10.0.0.0/40', '192.168.1.20/abc']]);

        foreach (['192.168.1.20', '10.0.0.1', '127.0.0.1', '::1'] as $ip) {
            $this->studentRequest('GET', route('student-device.entry'), server: ['REMOTE_ADDR' => $ip])->assertNotFound();
        }

        // One valid entry among typos still works for that address only.
        config(['remote_assessment.allowed_ips' => ['student-pc', self::STUDENT_PC]]);
        $this->studentRequest('GET', route('student-device.entry'), server: ['REMOTE_ADDR' => self::STUDENT_PC])->assertOk();
        $this->studentRequest('GET', route('student-device.entry'), server: ['REMOTE_ADDR' => self::OTHER_PC])->assertNotFound();
    }

    public function test_the_live_page_shows_the_allowlist_and_invalid_entries_to_staff_only(): void
    {
        config(['remote_assessment.allowed_ips' => [self::STUDENT_PC, '10.0.0.0/40']]);
        $psychometrician = $this->psychometrician();
        $this->actingAs($psychometrician);
        $this->submitStepOne();
        ['short_code' => $shortCode] = $this->sendToStudentDevice();

        $this->get(route('assessments.create.remote'))
            ->assertOk()
            ->assertSee('data-allowed-ips', false)
            ->assertSee(self::STUDENT_PC)
            ->assertSee('data-invalid-allowed-ips', false)
            ->assertSee('10.0.0.0/40');

        // Nothing of it on the student device, refused or allowed.
        $device = $this->studentRequest('POST', route('student-device.code'), ['code' => $shortCode], server: ['REMOTE_ADDR' => self::STUDENT_PC])->assertStatus(303)
            ->getCookie(RemoteAssessmentService::DEVICE_COOKIE)->getValue();
        $this->studentRequest('GET', route('student-device.show'), device: $device, server: ['REMOTE_ADDR' => self::STUDENT_PC])->assertOk();
        $this->studentRequest('GET', route('student-device.entry'), server: ['REMOTE_ADDR' => self::OTHER_PC])->assertNotFound();
        foreach ($this->studentResponses as ['label' => $label, 'response' => $response]) {
            foreach ([self::STUDENT_PC, '10.0.0.0', 'allowed', 'REMOTE_ASSESSMENT'] as $term) {
                $this->assertStringNotContainsStringIgnoringCase($term, (string) $response->getContent()."\n".$response->headers, "{$label} contains \"{$term}\".");
            }
        }

        // No list configured: no line about it on the live page.
        config(['remote_assessment.allowed_ips' => []]);
        $this->actingAs($psychometrician);
        $this->get(route('assessments.create.remote'))->assertOk()->assertDontSee('data-allowed-ips', false)->assertDontSee('data-invalid-allowed-ips', false);
    }

    public function test_no_student_device_response_carries_rate_limit_counters(): void
    {
        ['short_code' => $shortCode] = $this->createRemoteDraft();
        $responses = [
            $this->studentRequest('GET', route('student-device.entry')),
            $this->studentRequest('POST', route('student-device.code'), ['code' => 'ZZZZ-ZZZZ']),
            $this->studentRequest('GET', route('student-device.state'), json: true),
        ];
        $device = $this->claimWithCode($shortCode);
        $responses[] = $this->studentRequest('GET', route('student-device.show'), device: $device);
        for ($i = 0; $i < 10; $i++) {
            $this->studentRequest('POST', route('student-device.code'), ['code' => 'ZZZZ-ZZZZ']);
        }
        $responses[] = $this->studentRequest('POST', route('student-device.code'), ['code' => 'ZZZZ-ZZZZ'])->assertStatus(429)->assertHeader('Retry-After');

        foreach ($responses as $response) {
            foreach (['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset'] as $header) {
                $this->assertFalse($response->headers->has($header), "{$header} on a {$response->getStatusCode()}.");
            }
        }
    }

    public function test_the_typed_code_works_end_to_end_from_a_listed_pc(): void
    {
        config(['remote_assessment.allowed_ips' => [self::STUDENT_PC]]);
        $psychometrician = $this->psychometrician();
        $this->actingAs($psychometrician);
        ['draft' => $draft, 'short_code' => $shortCode] = $this->sendStepOneToStudentDevice();
        $pc = ['REMOTE_ADDR' => self::STUDENT_PC];

        $this->studentRequest('GET', route('student-device.entry'), server: $pc)->assertOk();
        $device = (string) $this->studentRequest('POST', route('student-device.code'), ['code' => strtolower(str_replace('-', ' ', $shortCode))], server: $pc)
            ->assertStatus(303)->getCookie(RemoteAssessmentService::DEVICE_COOKIE)->getValue();
        $this->studentRequest('POST', route('student-device.consent'), device: $device, server: $pc)->assertStatus(303);
        $this->studentRequest('POST', route('student-device.identity'), $this->studentDetails('Nina', 'O.', 'Pascual'), $device, server: $pc)
            ->assertStatus(303)->assertRedirect(route('student-device.show').'#questions');
        foreach ($this->version->questions as $question) {
            $this->studentRequest('POST', route('student-device.answer'), ['question_id' => $question->id, 'value' => 2], $device, json: true, server: $pc)->assertOk();
        }
        $this->studentRequest('POST', route('student-device.done'), [], $device, json: true, server: $pc)->assertOk()->assertExactJson(['state' => 'locked']);

        // The same device cookie from another address is refused.
        $this->studentRequest('GET', route('student-device.show'), device: $device, server: ['REMOTE_ADDR' => self::OTHER_PC])->assertNotFound();

        $this->actingAs($psychometrician);
        $this->post(route('assessments.create.remote.submit'), ['privacy_consent' => '1'])->assertRedirect(route('assessments.create.result'));
        $this->reviewAndSaveAssessment()->assertRedirect();

        $assessment = Assessment::query()->with('student')->sole();
        $this->assertSame('Nina O. Pascual', $assessment->student->full_name);
        $this->assertSame(Assessment::ADMINISTRATION_STUDENT_DEVICE, $assessment->administration_mode);
        $this->assertModelMissing($draft);
    }

    /**
     * The `student-device-page` limiter's key for a device without a cookie
     * (AppServiceProvider; ThrottleRequests hashes named-limiter keys).
     */
    private function pageLimiterKey(string $ip): string
    {
        return md5('student-device-page'.'page|ip|'.$ip);
    }

    /**
     * Status, headers apart from Date, and body.
     *
     * @return array<string, mixed>
     */
    private function comparable(TestResponse $response): array
    {
        $headers = $response->headers->all();
        unset($headers['date']);
        ksort($headers);

        return ['status' => $response->getStatusCode(), 'headers' => $headers, 'body' => (string) $response->getContent()];
    }
}
