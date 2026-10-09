<?php

declare(strict_types=1);

namespace Tests\Feature\StudentDevice;

use App\Models\AuditLog;
use App\Models\QuestionnaireVersion;
use App\Models\RemoteAssessmentDraft;
use App\Services\RemoteAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithDomainData;
use Tests\Concerns\InteractsWithStudentDevice;
use Tests\TestCase;

/**
 * The student device's own flow: claiming by short code or link token (one
 * device only), the on-device privacy notice, autosave, Done and the lock,
 * expiry and pruning, the same-origin rule, rate limits, and the
 * staff-browser refusal.
 */
class StudentDeviceFlowTest extends TestCase
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

    public function test_credentials_are_stored_only_as_hashes(): void
    {
        ['draft' => $draft, 'token' => $token, 'short_code' => $shortCode] = $this->createRemoteDraft();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $token);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}$/', $shortCode);

        $row = (array) DB::table('remote_assessment_drafts')->where('id', $draft->id)->first();
        $flattened = implode('|', array_map('strval', $row));

        $this->assertStringNotContainsString($token, $flattened);
        $this->assertStringNotContainsString(str_replace('-', '', $shortCode), $flattened);
        $this->assertSame(64, strlen($row['token_hash']));
        $this->assertSame(64, strlen($row['short_code_hash']));
        $this->assertNull($row['device_hash']);
        $this->assertSame(RemoteAssessmentDraft::STATUS_PENDING, $row['status']);
    }

    public function test_the_draft_expires_after_the_configured_minutes(): void
    {
        config(['remote_assessment.ttl_minutes' => 5]);
        $this->freezeSecond();

        ['draft' => $draft] = $this->createRemoteDraft();

        $this->assertTrue($draft->expires_at->equalTo(now()->addMinutes(5)));
    }

    public function test_a_new_draft_replaces_the_psychometricians_earlier_one(): void
    {
        $psychometrician = $this->psychometrician();
        ['draft' => $first] = $this->createRemoteDraft($psychometrician);
        ['draft' => $second] = $this->createRemoteDraft($psychometrician);

        $this->assertModelMissing($first);
        $this->assertModelExists($second);
    }

    public function test_short_code_entry_is_forgiving_about_case_dashes_and_lookalike_letters(): void
    {
        $service = app(RemoteAssessmentService::class);

        $this->assertSame('0113ABCD', $service->normalizeShortCode(' oIl3-abcd '));
        $this->assertSame('K7M4QXPZ', $service->normalizeShortCode('k7m4 qxpz'));
        $this->assertNull($service->normalizeShortCode('K7M4QXP'));
        $this->assertNull($service->normalizeShortCode('K7M4QXPU'));
        $this->assertNull($service->normalizeShortCode('K7M4QXPZ1'));
    }

    public function test_a_typed_short_code_claims_the_device(): void
    {
        ['draft' => $draft, 'short_code' => $shortCode] = $this->createRemoteDraft();

        $device = $this->claimWithCode(strtolower($shortCode));

        $draft->refresh();
        $this->assertSame(RemoteAssessmentDraft::STATUS_ANSWERING, $draft->status);
        $this->assertNotNull($draft->claimed_at);
        $this->assertSame(app(RemoteAssessmentService::class)->hash('device', $device), $draft->device_hash);
    }

    public function test_the_device_cookie_is_http_only_strict_limited_to_s_and_expires_with_the_draft(): void
    {
        ['draft' => $draft, 'short_code' => $shortCode] = $this->createRemoteDraft();

        $response = $this->studentRequest('POST', route('student-device.code'), ['code' => $shortCode]);
        $cookie = $response->getCookie(RemoteAssessmentService::DEVICE_COOKIE, decrypt: false);

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('strict', $cookie->getSameSite());
        $this->assertSame('/s', $cookie->getPath());
        $this->assertSame($draft->fresh()->expires_at->getTimestamp(), $cookie->getExpiresTime());
    }

    public function test_opening_the_link_shows_begin_and_claims_nothing(): void
    {
        ['draft' => $draft, 'token' => $token] = $this->createRemoteDraft();

        $this->studentRequest('GET', route('student-device.begin', $token))
            ->assertOk()
            ->assertSee(__('student_device.begin_submit'))
            // The form posts back to its own address: no action, no token in the HTML.
            ->assertSee('<form method="POST" class=', false)
            ->assertDontSee($token, false);

        $this->assertSame(RemoteAssessmentDraft::STATUS_PENDING, $draft->fresh()->status);
        $this->assertNull($draft->fresh()->device_hash);
    }

    public function test_pressing_begin_claims_the_device(): void
    {
        ['draft' => $draft, 'token' => $token] = $this->createRemoteDraft();

        $response = $this->studentRequest('POST', route('student-device.claim', $token));

        $response->assertStatus(303)->assertRedirect(route('student-device.show'));
        $this->assertNotNull($response->getCookie(RemoteAssessmentService::DEVICE_COOKIE));
        $this->assertSame(RemoteAssessmentDraft::STATUS_ANSWERING, $draft->fresh()->status);
    }

    public function test_a_second_device_is_refused_by_code_and_by_token_and_counted(): void
    {
        ['draft' => $draft, 'token' => $token, 'short_code' => $shortCode] = $this->createRemoteDraft();
        $device = $this->claimWithCode($shortCode);

        $this->studentRequest('POST', route('student-device.code'), ['code' => $shortCode])
            ->assertNotFound()
            ->assertSee(__('student_device.unavailable_body'))
            ->assertCookieMissing(RemoteAssessmentService::DEVICE_COOKIE);
        $this->studentRequest('GET', route('student-device.begin', $token))->assertNotFound();
        $this->studentRequest('POST', route('student-device.claim', $token))->assertNotFound();

        // A different device with its own (unrelated) cookie is refused too.
        $this->studentRequest('POST', route('student-device.claim', $token), device: 'not-the-device')->assertNotFound();

        // Claim attempts are counted; merely opening the Begin page isn't.
        $draft->refresh();
        $this->assertSame(3, $draft->refused_device_attempts);
        $this->assertSame(app(RemoteAssessmentService::class)->hash('device', $device), $draft->device_hash);
    }

    public function test_the_claim_is_atomic_only_the_first_of_two_claims_wins(): void
    {
        ['short_code' => $shortCode, 'token' => $token] = $this->createRemoteDraft();
        $service = app(RemoteAssessmentService::class);

        $first = $service->claimWithShortCode($shortCode);
        $second = $service->claimWithToken($token);

        $this->assertNotNull($first);
        $this->assertNull($second);
    }

    public function test_the_same_device_reopening_the_link_or_the_code_page_resumes(): void
    {
        ['token' => $token, 'short_code' => $shortCode] = $this->createRemoteDraft();
        $device = $this->claimWithCode($shortCode);

        $this->studentRequest('GET', route('student-device.begin', $token), device: $device)
            ->assertStatus(303)->assertRedirect(route('student-device.show'));
        $this->studentRequest('POST', route('student-device.claim', $token), device: $device)
            ->assertStatus(303)->assertRedirect(route('student-device.show'));
        $this->studentRequest('GET', route('student-device.entry'), device: $device)
            ->assertStatus(303)->assertRedirect(route('student-device.show'));
    }

    public function test_invalid_codes_and_tokens_get_the_one_generic_message(): void
    {
        $this->createRemoteDraft();

        foreach ([
            $this->studentRequest('POST', route('student-device.code'), ['code' => 'ZZZZ-ZZZZ']),
            $this->studentRequest('POST', route('student-device.code'), ['code' => 'nonsense']),
            $this->studentRequest('POST', route('student-device.code'), ['code' => ['array']]),
            $this->studentRequest('GET', route('student-device.begin', str_repeat('a', 43))),
            $this->studentRequest('POST', route('student-device.claim', str_repeat('a', 43))),
            $this->studentRequest('GET', '/s/t/too-short'),
        ] as $response) {
            $response->assertNotFound()
                ->assertSee(__('student_device.unavailable_heading'))
                ->assertSee(__('student_device.unavailable_body'))
                ->assertSee('<form method="GET" action="'.route('student-device.entry').'"', false);
            $this->assertSame(1, substr_count((string) $response->getContent(), '<form'));
            $this->assertSame(1, substr_count((string) $response->getContent(), '<button'));
        }

        // /s/q with no live session goes back to the code form.
        $this->studentRequest('GET', route('student-device.show'))->assertRedirect(route('student-device.entry'));
    }

    public function test_the_privacy_notice_comes_first_and_answers_are_refused_until_it_is_acknowledged(): void
    {
        ['draft' => $draft, 'short_code' => $shortCode] = $this->createRemoteDraft();
        $device = $this->claimWithCode($shortCode);
        $question = $this->version->questions->first();

        $this->studentRequest('GET', route('student-device.show'), device: $device)
            ->assertOk()
            ->assertSee(__('student_device.consent_heading'))
            ->assertSee(__('student_device.consent_placeholder_marker'))
            ->assertDontSee($question->question_text);

        $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)
            ->assertOk()->assertExactJson(['state' => 'consent']);
        $this->studentRequest('POST', route('student-device.answer'), ['question_id' => $question->id, 'value' => 2], $device, json: true)
            ->assertStatus(409)->assertExactJson(['state' => 'consent']);
        $this->studentRequest('POST', route('student-device.done'), [], $device, json: true)
            ->assertStatus(409)->assertExactJson(['state' => 'consent']);
        $this->assertNull($draft->fresh()->responses);

        $this->consentOnDevice($device);

        $this->assertNotNull($draft->fresh()->consented_at);
        $this->studentRequest('GET', route('student-device.show'), device: $device)
            ->assertOk()
            ->assertSee($question->question_text);
    }

    public function test_declining_strips_the_draft_and_asks_for_the_device_back(): void
    {
        ['draft' => $draft, 'short_code' => $shortCode] = $this->createRemoteDraft();
        $device = $this->claimWithCode($shortCode);

        $this->studentRequest('POST', route('student-device.decline'), device: $device)
            ->assertOk()
            ->assertSee(__('student_device.declined_body'))
            ->assertCookieExpired(RemoteAssessmentService::DEVICE_COOKIE);

        $draft->refresh();
        $this->assertSame(RemoteAssessmentDraft::STATUS_DECLINED, $draft->status);
        $this->assertNull($draft->token_hash);
        $this->assertNull($draft->short_code_hash);
        $this->assertNull($draft->device_hash);
        $this->assertNull($draft->responses);

        $this->studentRequest('GET', route('student-device.show'), device: $device)->assertRedirect(route('student-device.entry'));
        $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)
            ->assertNotFound()->assertExactJson(['state' => 'unavailable']);
    }

    public function test_with_the_consent_flag_off_the_questions_come_straight_away(): void
    {
        config(['remote_assessment.student_consent' => false]);
        ['draft' => $draft, 'short_code' => $shortCode] = $this->createRemoteDraft();
        $device = $this->claimWithCode($shortCode);

        $this->assertFalse($draft->fresh()->requires_consent);
        $this->studentRequest('GET', route('student-device.show'), device: $device)
            ->assertOk()
            ->assertSee($this->version->questions->first()->question_text)
            ->assertDontSee(__('student_device.consent_heading'));

        // Decline only exists while the notice is on screen.
        $this->studentRequest('POST', route('student-device.decline'), device: $device)
            ->assertStatus(303)->assertRedirect(route('student-device.show'));
        $this->assertSame(RemoteAssessmentDraft::STATUS_ANSWERING, $draft->fresh()->status);
    }

    public function test_answers_are_autosaved_encrypted_at_rest(): void
    {
        ['draft' => $draft, 'short_code' => $shortCode] = $this->createRemoteDraft();
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        [$first, $second] = $this->version->questions->take(2)->values()->all();

        $this->studentRequest('POST', route('student-device.answer'), ['question_id' => $first->id, 'value' => 3], $device, json: true)
            ->assertOk()->assertExactJson(['state' => 'answering', 'answered' => 1]);
        $this->studentRequest('POST', route('student-device.answer'), ['question_id' => $second->id, 'value' => 0], $device, json: true)
            ->assertOk()->assertExactJson(['state' => 'answering', 'answered' => 2]);
        // Changing an answer replaces it.
        $this->studentRequest('POST', route('student-device.answer'), ['question_id' => $first->id, 'value' => 1], $device, json: true)
            ->assertOk()->assertExactJson(['state' => 'answering', 'answered' => 2]);

        $draft->refresh();
        $this->assertSame([$first->id => 1, $second->id => 0], $draft->responses);
        // Claim, consent, then three saves.
        $this->assertSame(5, $draft->revision);

        $raw = DB::table('remote_assessment_drafts')->where('id', $draft->id)->value('responses');
        // Ciphertext (base64), not the plain JSON map.
        $this->assertStringNotContainsString('{', $raw);
        $this->assertSame([$first->id => 1, $second->id => 0], json_decode(decrypt($raw, false), true));

        // The questionnaire page shows the saved answers again.
        $this->studentRequest('GET', route('student-device.show'), device: $device)
            ->assertOk()
            ->assertSee(__('student_device.counter', ['answered' => 2, 'total' => 21]));
    }

    public function test_each_statement_carries_its_saved_answer_so_early_clicks_can_be_sent(): void
    {
        // An answer tapped before student-device.js has run fires no change
        // event; the script compares each checked answer with data-saved and
        // sends the ones the server doesn't have. autocomplete="off" stops
        // the browser restoring stale choices on reload.
        ['short_code' => $shortCode] = $this->createRemoteDraft();
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        [$first, $second] = $this->version->questions->take(2)->values()->all();
        $this->studentRequest('POST', route('student-device.answer'), ['question_id' => $first->id, 'value' => 2], $device, json: true)->assertOk();

        $html = (string) $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-question="'.$first->id.'"\s+data-saved="2"/', $html);
        $this->assertMatchesRegularExpression('/data-question="'.$second->id.'"\s+data-saved=""/', $html);
        $this->assertSame(84, substr_count($html, 'autocomplete="off"'));
    }

    public function test_answers_outside_the_pinned_version_or_range_are_refused(): void
    {
        ['draft' => $draft, 'short_code' => $shortCode] = $this->createRemoteDraft();
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);

        $other = QuestionnaireVersion::factory()->create();
        $this->addDassQuestions($other, 1, 0, 0);
        $question = $this->version->questions->first();

        foreach ([
            ['question_id' => $other->questions()->first()->id, 'value' => 1],
            ['question_id' => $question->id, 'value' => 4],
            ['question_id' => $question->id, 'value' => -1],
            ['question_id' => $question->id, 'value' => 'abc'],
            ['question_id' => 'x', 'value' => 1],
            ['value' => 1],
        ] as $payload) {
            $this->studentRequest('POST', route('student-device.answer'), $payload, $device, json: true)
                ->assertStatus(422)->assertExactJson(['state' => 'unavailable']);
        }

        $this->assertNull($draft->fresh()->responses);
    }

    public function test_done_lists_missing_items_then_locks_and_the_page_shows_only_thanks(): void
    {
        ['draft' => $draft, 'short_code' => $shortCode] = $this->createRemoteDraft();
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $questions = $this->version->questions;

        foreach ($questions->slice(0, 19) as $question) {
            $this->studentRequest('POST', route('student-device.answer'), ['question_id' => $question->id, 'value' => 2], $device, json: true)->assertOk();
        }

        $this->studentRequest('POST', route('student-device.done'), [], $device, json: true)
            ->assertStatus(422)
            ->assertExactJson(['state' => 'answering', 'missing' => $questions->slice(19)->pluck('item_number')->values()->all()]);
        $this->assertSame(RemoteAssessmentDraft::STATUS_ANSWERING, $draft->fresh()->status);

        foreach ($questions->slice(19) as $question) {
            $this->studentRequest('POST', route('student-device.answer'), ['question_id' => $question->id, 'value' => 2], $device, json: true)->assertOk();
        }

        $this->studentRequest('POST', route('student-device.done'), [], $device, json: true)
            ->assertOk()->assertExactJson(['state' => 'locked']);

        $draft->refresh();
        $this->assertSame(RemoteAssessmentDraft::STATUS_LOCKED, $draft->status);
        $this->assertNotNull($draft->locked_at);

        // Locked: no more editing, and the page shows only the thank-you message.
        $this->studentRequest('POST', route('student-device.answer'), ['question_id' => $questions->first()->id, 'value' => 0], $device, json: true)
            ->assertStatus(409)->assertExactJson(['state' => 'locked']);
        $this->studentRequest('POST', route('student-device.done'), [], $device, json: true)
            ->assertStatus(409)->assertExactJson(['state' => 'locked']);
        $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)
            ->assertOk()->assertExactJson(['state' => 'locked']);
        $this->studentRequest('GET', route('student-device.show'), device: $device)
            ->assertOk()
            ->assertSee(__('student_device.thanks_body'))
            ->assertDontSee($questions->first()->question_text)
            ->assertDontSee('type="radio"', false);

        $this->assertSame(2, $draft->fresh()->responses[$questions->first()->id]);
    }

    public function test_an_expired_draft_is_unavailable_everywhere_and_is_pruned(): void
    {
        ['draft' => $draft, 'token' => $token, 'short_code' => $shortCode] = $this->createRemoteDraft();
        ['draft' => $unclaimed, 'token' => $unclaimedToken, 'short_code' => $unclaimedCode] = $this->createRemoteDraft($this->psychometrician());
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);

        $this->travel(61)->minutes();

        $this->studentRequest('GET', route('student-device.show'), device: $device)
            ->assertRedirect(route('student-device.entry'))
            ->assertCookieExpired(RemoteAssessmentService::DEVICE_COOKIE);
        $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)->assertNotFound()->assertExactJson(['state' => 'unavailable']);
        $this->studentRequest('POST', route('student-device.answer'), ['question_id' => $this->version->questions->first()->id, 'value' => 1], $device, json: true)
            ->assertNotFound()->assertExactJson(['state' => 'unavailable']);
        $this->studentRequest('POST', route('student-device.done'), [], $device, json: true)->assertNotFound();
        $this->studentRequest('GET', route('student-device.begin', $unclaimedToken))->assertNotFound();
        $this->studentRequest('POST', route('student-device.claim', $unclaimedToken))->assertNotFound();
        $this->studentRequest('POST', route('student-device.code'), ['code' => $unclaimedCode])->assertNotFound();
        $this->studentRequest('POST', route('student-device.claim', $token))->assertNotFound();

        $this->artisan('model:prune', ['--model' => [RemoteAssessmentDraft::class]])->assertSuccessful();

        $this->assertModelMissing($draft);
        $this->assertModelMissing($unclaimed);
    }

    public function test_creating_a_draft_prunes_every_expired_one(): void
    {
        ['draft' => $old] = $this->createRemoteDraft($this->psychometrician());
        $this->travel(61)->minutes();

        ['draft' => $new] = $this->createRemoteDraft($this->psychometrician());

        $this->assertModelMissing($old);
        $this->assertModelExists($new);
    }

    public function test_the_draft_is_never_audited(): void
    {
        $psychometrician = $this->psychometrician();
        $before = AuditLog::query()->count();

        ['short_code' => $shortCode] = $this->createRemoteDraft($psychometrician);
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $this->answerAllOnDevice($device, $this->version);
        $this->studentRequest('POST', route('student-device.done'), [], $device, json: true)->assertOk();

        $this->assertSame($before, AuditLog::query()->count());
    }

    public function test_posts_without_a_same_origin_signal_are_refused(): void
    {
        ['short_code' => $shortCode] = $this->createRemoteDraft();

        // No Origin and no Sec-Fetch-Site.
        $this->studentRequest('POST', route('student-device.code'), ['code' => $shortCode], sameOrigin: false)
            ->assertForbidden()->assertSee(__('student_device.unavailable_body'));
        // Another site.
        $this->studentRequest('POST', route('student-device.code'), ['code' => $shortCode], sameOrigin: false, server: ['HTTP_ORIGIN' => 'http://evil.example'])
            ->assertForbidden();
        $this->studentRequest('POST', route('student-device.code'), ['code' => $shortCode], sameOrigin: false, server: ['HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_ORIGIN' => $this->studentOrigin()])
            ->assertForbidden();
        // Origin: null (sent under a no-referrer policy).
        $this->studentRequest('POST', route('student-device.code'), ['code' => $shortCode], sameOrigin: false, server: ['HTTP_ORIGIN' => 'null'])
            ->assertForbidden();

        $this->assertSame(RemoteAssessmentDraft::STATUS_PENDING, RemoteAssessmentDraft::query()->sole()->status);

        // Sec-Fetch-Site: same-origin alone is enough.
        $this->studentRequest('POST', route('student-device.code'), ['code' => $shortCode], sameOrigin: false, server: ['HTTP_SEC_FETCH_SITE' => 'same-origin'])
            ->assertStatus(303);
    }

    public function test_json_posts_also_need_the_same_origin_signal(): void
    {
        ['short_code' => $shortCode] = $this->createRemoteDraft();
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);

        $this->studentRequest('POST', route('student-device.answer'), ['question_id' => $this->version->questions->first()->id, 'value' => 1], $device, json: true, sameOrigin: false)
            ->assertForbidden()->assertExactJson(['state' => 'unavailable']);
        $this->studentRequest('POST', route('student-device.done'), [], $device, json: true, sameOrigin: false)
            ->assertForbidden();
    }

    public function test_successful_claims_never_use_up_the_per_ip_failed_attempt_limit(): void
    {
        // A whole class behind one school NAT: one IP, many devices.
        for ($i = 0; $i < 9; $i++) {
            $this->studentRequest('POST', route('student-device.code'), ['code' => 'ZZZZ-ZZZZ'])->assertNotFound();
        }

        for ($i = 0; $i < 25; $i++) {
            ['short_code' => $shortCode, 'token' => $token] = $this->createRemoteDraft($this->psychometrician());

            if ($i % 2 === 0) {
                $this->claimWithCode($shortCode);
            } else {
                // Opening a valid link and pressing Begin don't count either.
                $this->studentRequest('GET', route('student-device.begin', $token))->assertOk();
                $this->studentRequest('POST', route('student-device.claim', $token))->assertStatus(303);
            }
        }

        // The tenth failure is still answered normally; the eleventh is refused.
        $this->studentRequest('POST', route('student-device.code'), ['code' => 'ZZZZ-ZZZZ'])->assertNotFound();
        $this->studentRequest('POST', route('student-device.code'), ['code' => 'ZZZZ-ZZZZ'])->assertStatus(429);
    }

    public function test_the_rate_limits_are_configurable(): void
    {
        config([
            'remote_assessment.limits.failed_entry_per_ip' => 3,
            'remote_assessment.limits.actions_per_device' => 5,
            'remote_assessment.limits.pages_per_device' => 4,
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->studentRequest('POST', route('student-device.code'), ['code' => 'ZZZZ-ZZZZ'])->assertNotFound();
        }
        $this->studentRequest('GET', route('student-device.begin', str_repeat('c', 43)))->assertStatus(429);

        ['short_code' => $shortCode] = $this->createRemoteDraft();
        $device = app(RemoteAssessmentService::class)->claimWithShortCode($shortCode);

        for ($i = 0; $i < 5; $i++) {
            $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)->assertOk();
        }
        $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)->assertStatus(429);

        for ($i = 0; $i < 4; $i++) {
            $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk();
        }
        $this->studentRequest('GET', route('student-device.show'), device: $device)->assertStatus(429);
    }

    public function test_page_limits_are_per_device_so_a_class_behind_one_ip_shares_nothing(): void
    {
        config(['remote_assessment.limits.pages_per_device' => 3]);

        foreach (range(1, 5) as $ignored) {
            ['short_code' => $shortCode] = $this->createRemoteDraft($this->psychometrician());
            $device = $this->claimWithCode($shortCode);

            for ($i = 0; $i < 3; $i++) {
                $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk();
            }
        }
    }

    public function test_done_extends_a_short_remaining_expiry_by_the_grace_period(): void
    {
        config(['remote_assessment.locked_grace_minutes' => 15]);
        $this->freezeSecond();
        ['draft' => $draft, 'short_code' => $shortCode] = $this->createRemoteDraft();
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $this->answerAllOnDevice($device, $this->version);

        // 10 minutes left when the student presses Done.
        $this->travel(50)->minutes();

        $response = $this->studentRequest('POST', route('student-device.done'), [], $device, json: true)->assertOk();

        $this->assertTrue($draft->fresh()->expires_at->equalTo(now()->addMinutes(15)));

        // The device cookie follows the new expiry.
        $cookie = $response->getCookie(RemoteAssessmentService::DEVICE_COOKIE, decrypt: false);
        $this->assertNotNull($cookie);
        $this->assertSame(now()->addMinutes(15)->getTimestamp(), $cookie->getExpiresTime());

        // Still there 14 minutes later, gone after 15.
        $this->travel(14)->minutes();
        $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)->assertOk()->assertExactJson(['state' => 'locked']);
        $this->travel(2)->minutes();
        $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)->assertNotFound();
    }

    public function test_done_never_shortens_a_longer_remaining_expiry(): void
    {
        config(['remote_assessment.locked_grace_minutes' => 15]);
        $this->freezeSecond();
        ['draft' => $draft, 'short_code' => $shortCode] = $this->createRemoteDraft();
        $originalExpiry = $draft->expires_at->copy();
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $this->answerAllOnDevice($device, $this->version);

        $this->travel(5)->minutes();
        $this->studentRequest('POST', route('student-device.done'), [], $device, json: true)->assertOk();

        $this->assertTrue($draft->fresh()->expires_at->equalTo($originalExpiry));
    }

    public function test_the_grace_period_is_configurable(): void
    {
        config(['remote_assessment.locked_grace_minutes' => 30]);
        $this->freezeSecond();
        ['draft' => $draft, 'short_code' => $shortCode] = $this->createRemoteDraft();
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $this->answerAllOnDevice($device, $this->version);
        $this->travel(55)->minutes();

        $this->studentRequest('POST', route('student-device.done'), [], $device, json: true)->assertOk();

        $this->assertTrue($draft->fresh()->expires_at->equalTo(now()->addMinutes(30)));
    }

    public function test_a_page_built_for_another_version_is_told_to_reload(): void
    {
        ['draft' => $draft, 'short_code' => $shortCode] = $this->createRemoteDraft();
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $question = $this->version->questions->first();

        $this->studentRequest('GET', route('student-device.state', ['version' => $this->version->id]), device: $device, json: true)
            ->assertOk()->assertExactJson(['state' => 'answering']);

        $newVersion = QuestionnaireVersion::factory()->create();
        $this->addDassQuestions($newVersion);
        app(RemoteAssessmentService::class)->restartOn($draft->fresh(), $newVersion);

        $this->studentRequest('GET', route('student-device.state', ['version' => $this->version->id]), device: $device, json: true)
            ->assertOk()->assertExactJson(['state' => 'reload']);
        $this->studentRequest('POST', route('student-device.answer'), ['question_id' => $question->id, 'value' => 1, 'version' => $this->version->id], $device, json: true)
            ->assertStatus(409)->assertExactJson(['state' => 'reload']);
        $this->studentRequest('POST', route('student-device.done'), ['version' => $this->version->id], $device, json: true)
            ->assertStatus(409)->assertExactJson(['state' => 'reload']);

        // The reloaded page shows the new version's questions.
        $this->studentRequest('GET', route('student-device.show'), device: $device)
            ->assertOk()
            ->assertSee('data-version="'.$newVersion->id.'"', false);
    }

    public function test_code_entry_is_rate_limited_per_ip(): void
    {
        ['short_code' => $shortCode] = $this->createRemoteDraft();

        for ($i = 0; $i < 10; $i++) {
            $this->studentRequest('POST', route('student-device.code'), ['code' => 'ZZZZ-ZZZZ'])->assertNotFound();
        }

        // Even the right code is refused once the limit is reached.
        $this->studentRequest('POST', route('student-device.code'), ['code' => $shortCode])
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertSee(__('student_device.unavailable_body'));

        $this->assertSame(RemoteAssessmentDraft::STATUS_PENDING, RemoteAssessmentDraft::query()->sole()->status);
    }

    public function test_token_pages_are_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->studentRequest('GET', route('student-device.begin', str_repeat('b', 43)))->assertNotFound();
        }

        $this->studentRequest('GET', route('student-device.begin', str_repeat('b', 43)))->assertStatus(429);
    }

    public function test_device_actions_are_rate_limited_per_device(): void
    {
        ['short_code' => $shortCode] = $this->createRemoteDraft();
        $device = $this->claimWithCode($shortCode);

        for ($i = 0; $i < 180; $i++) {
            $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)->assertOk();
        }

        $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)
            ->assertStatus(429)->assertExactJson(['state' => 'unavailable']);
    }

    public function test_a_browser_signed_in_to_a_staff_account_is_refused(): void
    {
        ['token' => $token, 'short_code' => $shortCode] = $this->createRemoteDraft();
        $staff = $this->guidanceCounselor();
        $sessionCookie = (string) config('session.cookie');

        DB::table('sessions')->insert([
            'id' => 'live-staff-session',
            'user_id' => $staff->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);

        foreach ([
            $this->studentRequest('GET', route('student-device.entry'), cookies: [$sessionCookie => 'live-staff-session']),
            $this->studentRequest('GET', route('student-device.begin', $token), cookies: [$sessionCookie => 'live-staff-session']),
            $this->studentRequest('POST', route('student-device.code'), ['code' => $shortCode], cookies: [$sessionCookie => 'live-staff-session']),
            $this->studentRequest('POST', route('student-device.claim', $token), cookies: [$sessionCookie => 'live-staff-session']),
            $this->studentRequest('GET', route('student-device.entry'), cookies: [Auth::guard('web')->getRecallerName() => '1|token|hash']),
        ] as $response) {
            $response->assertForbidden()
                ->assertSee(__('student_device.staff_body'))
                ->assertDontSee($staff->name)
                ->assertDontSee($staff->email);
        }

        $this->assertSame(RemoteAssessmentDraft::STATUS_PENDING, RemoteAssessmentDraft::query()->sole()->status);
    }

    public function test_an_expired_or_guest_session_cookie_is_not_a_staff_browser(): void
    {
        $sessionCookie = (string) config('session.cookie');

        DB::table('sessions')->insert([
            ['id' => 'expired-staff', 'user_id' => $this->psychometrician()->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => now()->subMinutes((int) config('session.lifetime') + 1)->getTimestamp()],
            ['id' => 'guest', 'user_id' => null, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => now()->getTimestamp()],
        ]);

        $this->studentRequest('GET', route('student-device.entry'), cookies: [$sessionCookie => 'expired-staff'])->assertOk();
        $this->studentRequest('GET', route('student-device.entry'), cookies: [$sessionCookie => 'guest'])->assertOk();
    }
}
