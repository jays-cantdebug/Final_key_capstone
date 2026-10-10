<?php

declare(strict_types=1);

namespace Tests\Feature\StudentDevice;

use App\Models\QuestionnaireVersion;
use App\Models\RemoteAssessmentDraft;
use App\Services\RemoteAssessmentService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithDomainData;
use Tests\Concerns\InteractsWithStudentDevice;
use Tests\TestCase;

/**
 * The link to the student PC (/s/t/{token}, from the live page's Copy
 * link), next to the typed code: opening it never claims (link previews
 * and scanners can't use it up), Begin claims once, one device per draft
 * whichever credential is used first, dead tokens get the generic 404, the
 * allowlist covers it, the token never reaches a student-facing response or
 * NORMI's logs, and the 140000 migration that restored its column.
 */
class StudentDeviceLinkTest extends TestCase
{
    use InteractsWithDomainData;
    use InteractsWithStudentDevice;
    use RefreshDatabase;

    /** User agents of link previews and safe-browsing scanners. */
    private const PREVIEW_AGENTS = [
        'Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)',
        'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
        'Mozilla/5.0 (compatible; Discordbot/2.0; +https://discordapp.com)',
        'TelegramBot (like TwitterBot)',
        'WhatsApp/2.23.20.0',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36 (compatible; Microsoft SafeLinks)',
        'Googlebot/2.1 (+http://www.google.com/bot.html)',
    ];

    private QuestionnaireVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        config(['remote_assessment.student_consent' => true]);
        $this->version = $this->createActiveQuestionnaireVersion();
    }

    public function test_opening_the_link_never_claims_even_for_link_previews_and_scanners(): void
    {
        ['draft' => $draft, 'token' => $token] = $this->createRemoteDraft();

        foreach ([...self::PREVIEW_AGENTS, ...self::PREVIEW_AGENTS] as $agent) {
            $this->studentRequest('GET', route('student-device.begin', $token), server: ['HTTP_USER_AGENT' => $agent])
                ->assertOk()
                ->assertSee(__('student_device.begin_submit'))
                ->assertCookieMissing(RemoteAssessmentService::DEVICE_COOKIE);
            $this->studentRequest('HEAD', route('student-device.begin', $token), server: ['HTTP_USER_AGENT' => $agent])->assertOk();
        }

        $draft->refresh();
        $this->assertSame(RemoteAssessmentDraft::STATUS_PENDING, $draft->status);
        $this->assertNull($draft->device_hash);
        $this->assertSame(0, $draft->refused_device_attempts);
        $this->assertSame(0, RateLimiter::attempts('student-device-failed-entry|127.0.0.1'));

        // The student's Begin still works afterwards.
        $this->studentRequest('POST', route('student-device.claim', $token))->assertStatus(303)->assertRedirect(route('student-device.show'));
        $this->assertSame(RemoteAssessmentDraft::STATUS_ANSWERING, $draft->fresh()->status);
    }

    public function test_begin_claims_exactly_once(): void
    {
        ['draft' => $draft, 'token' => $token] = $this->createRemoteDraft();

        $first = $this->studentRequest('POST', route('student-device.claim', $token))->assertStatus(303);
        $device = (string) $first->getCookie(RemoteAssessmentService::DEVICE_COOKIE)->getValue();
        $this->assertSame(app(RemoteAssessmentService::class)->hash('device', $device), $draft->fresh()->device_hash);

        // Another browser pressing Begin on the same link: refused, counted.
        $this->studentRequest('POST', route('student-device.claim', $token))->assertNotFound()->assertCookieMissing(RemoteAssessmentService::DEVICE_COOKIE);
        $this->studentRequest('GET', route('student-device.begin', $token))->assertNotFound();
        // The same device pressing it again just resumes.
        $this->studentRequest('POST', route('student-device.claim', $token), device: $device)->assertStatus(303)->assertRedirect(route('student-device.show'));

        $draft->refresh();
        $this->assertSame(1, $draft->refused_device_attempts);
        $this->assertSame(app(RemoteAssessmentService::class)->hash('device', $device), $draft->device_hash);
    }

    public function test_whichever_of_the_link_and_the_code_is_used_first_wins(): void
    {
        // The code first: the link is then refused.
        ['draft' => $byCode, 'token' => $token, 'short_code' => $code] = $this->createRemoteDraft();
        $this->claimWithCode($code);
        $this->studentRequest('GET', route('student-device.begin', $token))->assertNotFound()->assertSee(__('student_device.unavailable_body'));
        $this->studentRequest('POST', route('student-device.claim', $token))->assertNotFound()->assertSee(__('student_device.unavailable_body'));
        $this->assertSame(1, $byCode->fresh()->refused_device_attempts);

        // The link first: the code is then refused.
        ['draft' => $byLink, 'token' => $token, 'short_code' => $code] = $this->createRemoteDraft($this->psychometrician());
        $this->studentRequest('POST', route('student-device.claim', $token))->assertStatus(303);
        $this->studentRequest('POST', route('student-device.code'), ['code' => $code])->assertNotFound()->assertSee(__('student_device.unavailable_body'));
        $this->assertSame(1, $byLink->fresh()->refused_device_attempts);

        // And the replies are the generic ones, the same as for a made-up token or code.
        $this->assertSame(
            $this->comparable($this->studentRequest('POST', route('student-device.claim', str_repeat('Q', 43)))),
            $this->comparable($this->studentRequest('POST', route('student-device.claim', $token))),
        );
    }

    public function test_an_expired_a_revoked_or_a_declined_link_gets_the_generic_404(): void
    {
        $generic = fn (TestResponse $response) => $response->assertNotFound()
            ->assertSee(__('student_device.unavailable_body'))
            ->assertDontSee(__('student_device.begin_submit'));

        // Revoked by New code.
        ['draft' => $draft, 'token' => $old] = $this->createRemoteDraft();
        ['token' => $new] = app(RemoteAssessmentService::class)->reissue($draft);
        $generic($this->studentRequest('GET', route('student-device.begin', $old)));
        $generic($this->studentRequest('POST', route('student-device.claim', $old)));
        $this->studentRequest('GET', route('student-device.begin', $new))->assertOk();

        // Declined (stripped of its credentials).
        ['token' => $declinedToken, 'short_code' => $declinedCode] = $this->createRemoteDraft($this->psychometrician());
        $declining = $this->claimWithCode($declinedCode);
        $this->studentRequest('POST', route('student-device.decline'), device: $declining)->assertOk();
        $generic($this->studentRequest('GET', route('student-device.begin', $declinedToken)));

        // Cancelled (the draft is gone).
        ['draft' => $cancelled, 'token' => $cancelledToken] = $this->createRemoteDraft($this->psychometrician());
        $cancelled->delete();
        $generic($this->studentRequest('GET', route('student-device.begin', $cancelledToken)));

        // Expired: one expiry for the code and the link.
        ['token' => $expiring, 'short_code' => $expiringCode] = $this->createRemoteDraft($this->psychometrician());
        $this->travel(61)->minutes();
        $generic($this->studentRequest('GET', route('student-device.begin', $expiring)));
        $generic($this->studentRequest('POST', route('student-device.claim', $expiring)));
        $generic($this->studentRequest('POST', route('student-device.code'), ['code' => $expiringCode]));
        $this->studentRequest('GET', route('student-device.begin', $expiring), json: true)->assertNotFound()->assertExactJson(['state' => 'unavailable']);

        // One cleanup for both: the expired draft is pruned with its token hash.
        $this->artisan('model:prune', ['--model' => [RemoteAssessmentDraft::class]])->assertSuccessful();
        $this->assertSame(0, RemoteAssessmentDraft::query()->where('token_hash', app(RemoteAssessmentService::class)->hash('token', $expiring))->count());
    }

    public function test_a_refused_address_gets_the_identical_404_on_the_token_routes(): void
    {
        config(['remote_assessment.allowed_ips' => ['192.168.1.20']]);
        ['draft' => $draft, 'token' => $token] = $this->createRemoteDraft();
        $listed = ['REMOTE_ADDR' => '192.168.1.20'];
        $other = ['REMOTE_ADDR' => '192.168.1.99'];

        // What the listed PC gets for a made-up token.
        $invalid = $this->studentRequest('GET', route('student-device.begin', str_repeat('Z', 43)), server: $listed)->assertNotFound();
        $invalidJson = $this->studentRequest('GET', route('student-device.begin', str_repeat('Z', 43)), json: true, server: $listed)->assertNotFound();

        // Another PC with the REAL token, by GET and by POST, page and JSON.
        foreach ([
            $this->studentRequest('GET', route('student-device.begin', $token), server: $other),
            $this->studentRequest('POST', route('student-device.claim', $token), server: $other),
            $this->studentRequest('PUT', route('student-device.claim', $token), server: $other),
        ] as $response) {
            $this->assertSame($this->comparable($invalid), $this->comparable($response));
        }
        $this->assertSame($this->comparable($invalidJson), $this->comparable($this->studentRequest('POST', route('student-device.claim', $token), json: true, server: $other)));

        $this->assertSame(RemoteAssessmentDraft::STATUS_PENDING, $draft->fresh()->status);
        $this->assertSame(0, $draft->fresh()->refused_device_attempts);

        // The listed PC gets in by the link.
        $this->studentRequest('GET', route('student-device.begin', $token), server: $listed)->assertOk();
        $this->studentRequest('POST', route('student-device.claim', $token), server: $listed)->assertStatus(303);
    }

    public function test_the_token_never_appears_in_any_student_facing_response(): void
    {
        ['token' => $token] = $this->createRemoteDraft(collectsIdentity: true);

        $this->studentRequest('GET', route('student-device.begin', $token))->assertOk();
        $device = (string) $this->studentRequest('POST', route('student-device.claim', $token))->assertStatus(303)
            ->getCookie(RemoteAssessmentService::DEVICE_COOKIE)->getValue();
        $this->studentRequest('GET', route('student-device.begin', $token), device: $device)->assertStatus(303);
        $this->studentRequest('POST', route('student-device.claim', $token), device: $device)->assertStatus(303);
        $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk();
        $this->consentOnDevice($device);
        $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk();
        $this->studentRequest('POST', route('student-device.identity'), ['first_name' => 'x'], $device)->assertStatus(422);
        $this->sendDetailsOnDevice($device, $this->studentDetails());
        $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk();
        $this->answerAllOnDevice($device, $this->version);
        $this->studentRequest('POST', route('student-device.done'), [], $device, json: true)->assertOk();
        $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk();
        $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)->assertOk();
        $this->studentRequest('POST', route('student-device.claim', $token))->assertNotFound();
        $this->studentRequest('GET', route('student-device.begin', $token), json: true)->assertNotFound();

        $this->assertGreaterThan(30, count($this->studentResponses));
        foreach ($this->studentResponses as ['label' => $label, 'response' => $response]) {
            $haystack = (string) $response->getContent()."\n".$response->headers;
            foreach ([$token, substr($token, 0, 15), '/s/t/'] as $term) {
                $this->assertStringNotContainsString($term, $haystack, "A response to {$response->getStatusCode()} {$this->stripToken($label, $token)} contains the token.");
            }
        }
    }

    public function test_the_token_never_appears_in_the_logs_even_when_the_token_routes_fail(): void
    {
        $logPath = storage_path('logs/token-leak-test-'.bin2hex(random_bytes(6)).'.log');
        config([
            'logging.default' => 'token-leak-test',
            'logging.channels.token-leak-test' => ['driver' => 'single', 'path' => $logPath, 'level' => 'debug'],
        ]);

        try {
            ['token' => $token] = $this->createRemoteDraft();

            foreach ([true, false] as $debug) {
                config(['app.debug' => $debug]);
                // Thrown during the call (not built up front), so the stack
                // trace that gets logged runs through the controller and its
                // `$token` argument, as a real failure's would.
                $this->mock(RemoteAssessmentService::class, function ($mock): void {
                    $mock->shouldReceive('deviceDraft')->andReturn(null);
                    $mock->shouldReceive('pendingDraftForToken')->andReturnUsing(fn (string $token) => throw new \RuntimeException('begin failed'));
                    $mock->shouldReceive('claimWithToken')->andReturnUsing(fn (string $token) => throw new \RuntimeException('claim failed'));
                });
                $this->forgetTokenRouteControllers();

                $this->studentRequest('GET', route('student-device.begin', $token))->assertStatus(500);
                $this->studentRequest('POST', route('student-device.claim', $token))->assertStatus(500);
                $this->studentRequest('POST', route('student-device.claim', $token), json: true)->assertStatus(500);
            }

            $this->app->forgetInstance(RemoteAssessmentService::class);
            $this->forgetTokenRouteControllers();
            config(['app.debug' => false]);

            // Ordinary failures too: a made-up token, a wrong method, rate limiting.
            for ($i = 0; $i < 11; $i++) {
                $this->studentRequest('GET', route('student-device.begin', str_repeat('k', 43)));
            }
            $this->studentRequest('PUT', route('student-device.claim', $token));

            $this->assertFileExists($logPath, 'Nothing was logged, so the check proves nothing.');
            $written = (string) file_get_contents($logPath);
            $this->assertStringContainsString('begin failed', $written);
            $this->assertStringContainsString('claim failed', $written);

            foreach (glob(storage_path('logs/*')) ?: [] as $file) {
                if (! is_file($file)) {
                    continue;
                }
                $contents = (string) file_get_contents($file);
                foreach ([$token, substr($token, 0, 15)] as $term) {
                    $this->assertStringNotContainsString($term, $contents, basename($file).' contains the token.');
                }
            }
        } finally {
            if (is_file($logPath)) {
                unlink($logPath);
            }
        }
    }

    public function test_the_restore_migration_works_whether_or_not_the_drop_ran(): void
    {
        $restore = $this->migration('2026_10_08_140000_restore_token_hash_on_remote_assessment_drafts_table');
        $drop = $this->migration('2026_10_08_130000_drop_token_hash_from_remote_assessment_drafts_table');
        $state = fn (): array => [
            'column' => Schema::hasColumn('remote_assessment_drafts', 'token_hash'),
            'unique' => Schema::hasIndex('remote_assessment_drafts', ['token_hash'], 'unique'),
        ];

        // Migrated in order (create, drop, restore): the column is back, unique.
        $this->assertSame(['column' => true, 'unique' => true], $state());

        // Rolling back the restore lands on the state after the drop…
        $restore->down();
        $this->assertSame(['column' => false, 'unique' => false], $state());
        $restore->down(); // …and doing it again is harmless.
        $this->assertSame(['column' => false, 'unique' => false], $state());

        // A database where the drop ran: the restore adds the column.
        $restore->up();
        $this->assertSame(['column' => true, 'unique' => true], $state());

        // A database where the column is still there (the drop never ran,
        // or it was added back by hand): the restore does nothing.
        $restore->up();
        $this->assertSame(['column' => true, 'unique' => true], $state());

        // Rolling back both, in order, brings back the original column.
        $restore->down();
        $drop->down();
        $this->assertSame(['column' => true, 'unique' => true], $state());
        $drop->up();
        $restore->up();
        $this->assertSame(['column' => true, 'unique' => true], $state());

        // A column added without its index: down() still removes it cleanly.
        $restore->down();
        Schema::table('remote_assessment_drafts', fn ($table) => $table->char('token_hash', 64)->nullable());
        $this->assertSame(['column' => true, 'unique' => false], $state());
        $restore->down();
        $this->assertSame(['column' => false, 'unique' => false], $state());
        $restore->up();

        // And the restored column works: the link claims a draft.
        ['token' => $token] = $this->createRemoteDraft();
        $this->studentRequest('POST', route('student-device.claim', $token))->assertStatus(303);
    }

    private function migration(string $name): Migration
    {
        return require database_path("migrations/{$name}.php");
    }

    private function forgetTokenRouteControllers(): void
    {
        foreach (['student-device.begin', 'student-device.claim'] as $name) {
            Route::getRoutes()->getByName($name)->controller = null;
        }
    }

    private function stripToken(string $label, string $token): string
    {
        return str_replace($token, '{token}', $label);
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
