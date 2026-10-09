<?php

declare(strict_types=1);

namespace Tests\Feature\StudentDevice;

use App\Http\Middleware\EnsureSingleActiveSession;
use App\Models\Course;
use App\Models\FlaggedCase;
use App\Models\QuestionnaireVersion;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Models\YearLevel;
use App\Services\RemoteAssessmentService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Tests\Concerns\InteractsWithDomainData;
use Tests\Concerns\InteractsWithStudentDevice;
use Tests\TestCase;

/**
 * What the student device can reach and what it is sent: the exact route
 * list, that no other route is public, that a device holding only its
 * cookie is sent to the login page by every staff route, that it never
 * gets a Laravel session, the security headers on every response, and that
 * no response — pages, JSON, error pages, headers — contains any student
 * data, scores, levels, flags, or a link into the app.
 */
class StudentDeviceExposureTest extends TestCase
{
    use InteractsWithDomainData;
    use InteractsWithStudentDevice;
    use RefreshDatabase;

    /**
     * The only routes the student device can use.
     */
    private const STUDENT_DEVICE_ROUTES = [
        'GET|HEAD s' => 'student-device.entry',
        'POST s' => 'student-device.code',
        'GET|HEAD s/t/{token}' => 'student-device.begin',
        'POST s/t/{token}' => 'student-device.claim',
        'GET|HEAD s/q' => 'student-device.show',
        'POST s/consent' => 'student-device.consent',
        'POST s/decline' => 'student-device.decline',
        'POST s/identity' => 'student-device.identity',
        'POST s/answer' => 'student-device.answer',
        'POST s/done' => 'student-device.done',
        'GET|HEAD s/state' => 'student-device.state',
    ];

    /**
     * Every other route that needs no login: the root redirect, the
     * login and password-reset pages (guest only), and the health check.
     */
    private const OTHER_PUBLIC_ROUTES = [
        'GET|HEAD /',
        'GET|HEAD login',
        'POST login',
        'GET|HEAD forgot-password',
        'POST forgot-password',
        'GET|HEAD reset-password/{token}',
        'POST reset-password',
        'GET|HEAD up',
    ];

    private const FORBIDDEN_MIDDLEWARE = [
        StartSession::class,
        AuthenticateSession::class,
        ShareErrorsFromSession::class,
        ValidateCsrfToken::class,
        VerifyCsrfToken::class,
        Authenticate::class,
        EnsureSingleActiveSession::class,
    ];

    public function test_the_student_device_can_reach_exactly_these_routes(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route): bool => in_array('student-device', $route->gatherMiddleware(), true))
            ->mapWithKeys(fn (RoutingRoute $route): array => [$this->routeKey($route) => $route->getName()])
            ->all();

        $this->assertEqualsCanonicalizing(self::STUDENT_DEVICE_ROUTES, $routes);
    }

    public function test_student_device_routes_have_no_session_csrf_or_auth_middleware(): void
    {
        foreach (self::STUDENT_DEVICE_ROUTES as $name) {
            $route = Route::getRoutes()->getByName($name);
            $middleware = app('router')->gatherRouteMiddleware($route);

            $this->assertNotContains('web', $route->gatherMiddleware(), "{$name} is in the web group.");

            foreach ($middleware as $class) {
                foreach (self::FORBIDDEN_MIDDLEWARE as $forbidden) {
                    $this->assertFalse(is_string($class) && str_starts_with($class, $forbidden), "{$name} runs {$forbidden}.");
                }
            }
        }
    }

    public function test_every_other_route_requires_a_login_except_the_known_public_ones(): void
    {
        $public = collect(Route::getRoutes()->getRoutes())
            ->reject(fn (RoutingRoute $route): bool => in_array('student-device', $route->gatherMiddleware(), true))
            ->reject(fn (RoutingRoute $route): bool => in_array('auth', $route->gatherMiddleware(), true))
            // Ignition's debug routes (vendor tooling, present while
            // APP_DEBUG is on — which must be off on a LAN or a server).
            ->reject(fn (RoutingRoute $route): bool => str_starts_with((string) $route->getName(), 'ignition.'))
            ->map(fn (RoutingRoute $route): string => $this->routeKey($route))
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing(self::OTHER_PUBLIC_ROUTES, $public);
    }

    public function test_a_device_holding_only_its_cookie_is_sent_to_login_by_every_staff_route(): void
    {
        $this->createActiveQuestionnaireVersion();
        ['short_code' => $shortCode] = $this->createRemoteDraft();
        $device = $this->claimWithCode($shortCode);
        // Records exist for every {parameter} the crawl fills with id 1.
        $this->seedRecordsForCrawl();

        $failures = [];
        $crawled = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $key = $this->routeKey($route);

            if (array_key_exists($key, self::STUDENT_DEVICE_ROUTES) || in_array($key, self::OTHER_PUBLIC_ROUTES, true)
                || str_starts_with((string) $route->getName(), 'ignition.')) {
                continue;
            }

            $method = collect($route->methods())->reject(fn (string $method): bool => $method === 'HEAD')->first();
            $uri = '/'.ltrim((string) preg_replace('/\{[^}]+\}/', '1', $route->uri()), '/');

            $response = $this->studentRequest($method, $uri, device: $device);
            $crawled++;

            if ($response->getStatusCode() !== 302 || $response->headers->get('Location') !== route('login')) {
                $failures[] = "{$method} {$uri} → {$response->getStatusCode()} ".$response->headers->get('Location');
            }
        }

        $this->assertGreaterThan(50, $crawled);
        $this->assertSame([], $failures, "Reachable without a login:\n".implode("\n", $failures));
    }

    public function test_the_student_device_never_gets_a_laravel_session(): void
    {
        config(['session.driver' => 'database']);

        $this->runWholeStudentFlow();

        $this->assertSame(0, DB::table('sessions')->count());

        foreach ($this->studentResponses as ['label' => $label, 'response' => $response]) {
            foreach ($response->headers->getCookies() as $cookie) {
                $this->assertSame(RemoteAssessmentService::DEVICE_COOKIE, $cookie->getName(), "{$label} set the {$cookie->getName()} cookie.");
            }
        }
    }

    public function test_every_student_device_response_carries_the_security_headers(): void
    {
        $this->runWholeStudentFlow();
        $this->collectErrorResponses();

        $this->assertGreaterThan(30, count($this->studentResponses));

        foreach ($this->studentResponses as ['label' => $label, 'response' => $response]) {
            $headers = $response->headers;

            $this->assertStringContainsString('no-store', (string) $headers->get('Cache-Control'), "{$label}: Cache-Control");
            $this->assertSame('no-cache', $headers->get('Pragma'), "{$label}: Pragma");
            $this->assertSame('0', $headers->get('Expires'), "{$label}: Expires");
            $this->assertSame('same-origin', $headers->get('Referrer-Policy'), "{$label}: Referrer-Policy");
            $this->assertSame('nosniff', $headers->get('X-Content-Type-Options'), "{$label}: nosniff");
            $this->assertSame('DENY', $headers->get('X-Frame-Options'), "{$label}: X-Frame-Options");
            $this->assertSame('noindex, nofollow', $headers->get('X-Robots-Tag'), "{$label}: X-Robots-Tag");

            $csp = (string) $headers->get('Content-Security-Policy');
            foreach (["default-src 'none'", "script-src 'self'", "frame-ancestors 'none'", "form-action 'self'", "base-uri 'none'"] as $directive) {
                $this->assertStringContainsString($directive, $csp, "{$label}: CSP {$directive}");
            }
            $this->assertStringNotContainsString('unsafe', $csp, "{$label}: CSP allows unsafe-*");
            $this->assertFalse($headers->has('X-Powered-By'), "{$label}: X-Powered-By");
        }
    }

    public function test_no_student_device_response_leaks_student_data_results_or_app_links(): void
    {
        $leaks = $this->seedSensitiveData();

        $this->runWholeStudentFlow();
        $this->collectErrorResponses(exceptionMessage: implode(' ', $leaks));

        $this->assertGreaterThan(30, count($this->studentResponses));

        $forbidden = [
            ...$leaks,
            // Results and the rest of the app.
            'Normal', 'Mild', 'Moderate', 'Severe',
            'Depression', 'Anxiety', 'Stress',
            'score', 'severity', 'level', 'flag', 'classif', 'counsel', 'assessment', 'dashboard',
            'notification', 'Female', 'csrf', 'XSRF', 'laravel_session',
            // The staff attestation stays on the Psychometrician's side.
            'The student has acknowledged the data privacy consent notice', 'privacy_consent',
            // Debug output.
            'Exception', 'Stack trace', 'vendor', 'Ignition', 'Whoops',
            // Every link token handed out — and the 15-character start of
            // each (what a PHP stack trace keeps of a long string argument)
            // — even on the Begin page itself, whose form posts back to its
            // own address.
            ...$this->issuedTokens,
            ...array_map(fn (string $token): string => substr($token, 0, 15), $this->issuedTokens),
            '/s/t/',
        ];
        $this->assertGreaterThanOrEqual(4, count($this->issuedTokens));

        $identityForms = 0;

        foreach ($this->studentResponses as ['label' => $label, 'response' => $response]) {
            $haystack = (string) $response->getContent()."\n".$response->headers;

            // The open details form's own label, field name and fixed gender
            // options are the only places "Year Level" and "Female" may
            // appear, and only inside that <form> element: the rest of the
            // page (the questions below it) gets the full ban. Its lists are
            // checked in StudentDeviceIdentityTest; the marker itself is
            // checked in test_the_details_marker_and_its_allowance_appear_on_no_other_page.
            $form = $this->openDetailsForm($haystack);
            if ($form !== null) {
                $identityForms++;
                $haystack = str_replace($form, str_ireplace(['Year Level', 'year_level_id', 'Female'], '', $form), $haystack);
            }

            foreach ($forbidden as $term) {
                $this->assertStringNotContainsStringIgnoringCase($term, $haystack, "{$label} contains \"{$term}\".");
            }

            $this->assertNoAppLinks($label, $response);
        }

        // The form itself and its 422 re-render.
        $this->assertSame(2, $identityForms);
    }

    /**
     * The leak test lets "Year Level", year_level_id and "Female" through
     * only inside the form marked data-student-device-details="open". That
     * marker must only ever be on the open details form — a page whose
     * questions are still locked — and neither it nor those words may
     * appear on any other page: the questionnaire after saving, the held
     * page, the thank-you page, error pages, or JSON.
     */
    public function test_the_details_marker_and_its_allowance_appear_on_no_other_page(): void
    {
        $this->seedSensitiveData();
        $this->runWholeStudentFlow();
        $this->collectErrorResponses();

        $seen = ['open form' => 0, 'questionnaire after saving' => 0, 'held' => 0, 'thank-you' => 0, 'error page' => 0, 'json' => 0];

        foreach ($this->studentResponses as ['label' => $label, 'response' => $response]) {
            $body = (string) $response->getContent();
            $isJson = str_contains((string) $response->headers->get('Content-Type'), 'json');
            $form = $isJson ? null : $this->openDetailsForm($body);

            if ($form !== null) {
                $seen['open form']++;
                $this->assertMatchesRegularExpression('#^(GET|POST) '.preg_quote($this->studentOrigin(), '#').'/s/(q|identity)$#', $label, "{$label} has the open details form.");
                $this->assertContains($response->getStatusCode(), [200, 422], $label);
                $this->assertStringContainsString('data-locked="1"', $body, "{$label}: an open form with unlocked questions.");
                $this->assertMatchesRegularExpression('#<fieldset\b[^>]*\bdisabled\b[^>]*\bdata-questions\b#', $body, "{$label}: questions not disabled under the open form.");

                continue;
            }

            $kind = match (true) {
                $isJson => 'json',
                str_contains($body, 'data-details-saved') => 'questionnaire after saving',
                str_contains($body, 'data-student-device="held"') => 'held',
                str_contains($body, 'data-student-device="locked"') => 'thank-you',
                $response->getStatusCode() >= 400 => 'error page',
                default => null,
            };
            if ($kind !== null) {
                $seen[$kind]++;
            }

            foreach (['data-student-device-details', 'Year Level', 'year_level_id', 'Female', 'name="first_name"', 'name="course_id"'] as $term) {
                $this->assertStringNotContainsStringIgnoringCase($term, $body."\n".$response->headers, "{$label} contains \"{$term}\".");
            }
        }

        foreach ($seen as $kind => $count) {
            $this->assertGreaterThan(0, $count, "No {$kind} response was checked.");
        }
    }

    public function test_student_device_json_never_has_more_than_state_answered_and_missing(): void
    {
        $this->runWholeStudentFlow();
        $this->collectErrorResponses();

        $checked = 0;

        foreach ($this->studentResponses as ['label' => $label, 'response' => $response]) {
            if (! str_contains((string) $response->headers->get('Content-Type'), 'json')) {
                continue;
            }

            $data = $response->json();
            $this->assertIsArray($data, $label);
            $this->assertSame([], array_diff(array_keys($data), ['state', 'answered', 'missing']), "{$label}: ".json_encode($data));
            $this->assertContains($data['state'], ['consent', 'identity', 'answering', 'help', 'locked', 'reload', 'unavailable'], $label);
            $checked++;
        }

        $this->assertGreaterThan(25, $checked);
    }

    public function test_the_questionnaire_page_shows_no_subscale_or_layout_chrome(): void
    {
        $version = $this->createNeutralVersion();
        ['short_code' => $shortCode] = $this->createRemoteDraft(null, $version);
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);

        $response = $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk();

        foreach ($version->questions as $question) {
            $response->assertSee($question->item_number.'. '.$question->question_text);
        }

        $response->assertSee(__('student_device.counter', ['answered' => 0, 'total' => 21]))
            ->assertSee(__('student_device.done'))
            ->assertSee('Response Scale Instructions')
            ->assertDontSee('<nav', false)
            ->assertDontSee('<aside', false)
            ->assertDontSee('<a ', false)
            ->assertDontSee('csrf-token', false)
            ->assertDontSee('resources/js/app.js', false)
            ->assertDontSee('<script>', false);
    }

    /**
     * Claim, consent, answer everything, Done, then look at every screen
     * and endpoint again — collecting each response.
     */
    private function runWholeStudentFlow(): void
    {
        config(['remote_assessment.student_consent' => true]);

        $version = QuestionnaireVersion::query()->where('status', QuestionnaireVersion::STATUS_ACTIVE)->first()
            ?? $this->createNeutralVersion();
        $psychometrician = User::query()->whereHas('role', fn ($query) => $query->where('name', 'psychometrician'))->first();

        ['token' => $token, 'short_code' => $shortCode] = $this->createRemoteDraft($psychometrician, $version);

        $this->studentRequest('GET', route('student-device.entry'))->assertOk();
        $this->studentRequest('GET', route('student-device.begin', $token))->assertOk();
        $device = $this->claimWithCode($shortCode);

        $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk();
        $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)->assertOk();
        $this->studentRequest('POST', route('student-device.answer'), ['question_id' => $version->questions->first()->id, 'value' => 3], $device, json: true)->assertStatus(409);
        $this->consentOnDevice($device);
        $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk();

        $this->studentRequest('POST', route('student-device.done'), [], $device, json: true)->assertStatus(422);
        $this->answerAllOnDevice($device, $version, 3);
        $this->studentRequest('POST', route('student-device.answer'), ['question_id' => 999999, 'value' => 3], $device, json: true)->assertStatus(422);
        $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)->assertOk();
        $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk();
        $this->studentRequest('POST', route('student-device.done'), [], $device, json: true)->assertOk();

        $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk();
        $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)->assertOk();
        $this->studentRequest('POST', route('student-device.answer'), ['question_id' => $version->questions->first()->id, 'value' => 0], $device, json: true)->assertStatus(409);
        $this->studentRequest('GET', route('student-device.entry'), device: $device)->assertStatus(303);
        $this->studentRequest('GET', route('student-device.begin', $token), device: $device)->assertStatus(303);

        // A device claiming by the link instead.
        ['token' => $linkToken] = $this->createRemoteDraft($this->psychometrician(), $version);
        $this->studentRequest('GET', route('student-device.begin', $linkToken))->assertOk();
        $linked = $this->studentRequest('POST', route('student-device.claim', $linkToken))->assertStatus(303);
        $linkedDevice = (string) $linked->getCookie(RemoteAssessmentService::DEVICE_COOKIE)->getValue();
        $this->studentRequest('GET', route('student-device.show'), device: $linkedDevice)->assertOk();
        $this->studentRequest('POST', route('student-device.claim', $linkToken))->assertNotFound();

        // A second device, and a declined draft.
        $this->studentRequest('POST', route('student-device.code'), ['code' => $shortCode])->assertNotFound();
        ['short_code' => $declineCode] = $this->createRemoteDraft($this->psychometrician(), $version);
        $declining = $this->claimWithCode($declineCode);
        $this->studentRequest('GET', route('student-device.show'), device: $declining)->assertOk();
        $this->studentRequest('POST', route('student-device.decline'), device: $declining)->assertOk();
        $this->studentRequest('GET', route('student-device.show'), device: $declining)->assertRedirect(route('student-device.entry'));

        // The student types their own details (neutral lookups, so the
        // only Active ones are these).
        $course = Course::factory()->create(['course_code' => 'NTRL', 'course_name' => 'Neutral Programme']);
        $yearLevel = YearLevel::factory()->create(['label' => 'Year One']);
        $section = Section::factory()->create(['section_name' => 'Alpha']);
        $details = fn (string $first, ?string $middle, string $last): array => [
            'first_name' => $first, 'middle_name' => $middle, 'last_name' => $last, 'gender' => 'Male',
            'course_id' => $course->id, 'year_level_id' => $yearLevel->id, 'section_id' => $section->id,
        ];

        ['short_code' => $identityCode] = $this->createRemoteDraft($this->psychometrician(), $version, collectsIdentity: true);
        $typing = $this->claimWithCode($identityCode);
        $this->studentRequest('GET', route('student-device.show'), device: $typing)->assertOk();
        $this->studentRequest('POST', route('student-device.identity'), $details('Quentin', 'R.', 'Neutralis'), $typing)->assertStatus(303);
        $this->consentOnDevice($typing);
        $this->studentRequest('GET', route('student-device.show'), device: $typing)->assertOk();
        $this->studentRequest('GET', route('student-device.state'), device: $typing, json: true)->assertOk();
        $this->studentRequest('POST', route('student-device.answer'), ['question_id' => $version->questions->first()->id, 'value' => 1], $typing, json: true)->assertStatus(409);
        $this->studentRequest('POST', route('student-device.identity'), [...$details('Quentin', 'Rx', 'Neutralis'), 'course_id' => 999999], $typing)->assertStatus(422);
        $this->studentRequest('POST', route('student-device.identity'), $details('Quentin', 'r.', 'Neutralis'), $typing)->assertStatus(303);
        $this->studentRequest('GET', route('student-device.show'), device: $typing)->assertOk();
        $this->studentRequest('POST', route('student-device.identity'), $details('Quentin', 'R.', 'Neutralis'), $typing)->assertStatus(303);

        // Typing the name of an existing active student (the seeded one,
        // when there is one): held, with only the generic message.
        $existing = Student::query()->first();
        if ($existing !== null) {
            ['short_code' => $matchCode] = $this->createRemoteDraft($this->psychometrician(), $version, collectsIdentity: true);
            $matching = $this->claimWithCode($matchCode);
            $this->consentOnDevice($matching);
            $this->studentRequest('POST', route('student-device.identity'), $details($existing->first_name, $existing->middle_name, $existing->last_name), $matching)->assertStatus(303);
            $this->studentRequest('GET', route('student-device.show'), device: $matching)->assertOk();
            $this->studentRequest('GET', route('student-device.state'), device: $matching, json: true)->assertOk();
            $this->studentRequest('POST', route('student-device.answer'), ['question_id' => $version->questions->first()->id, 'value' => 1], $matching, json: true)->assertStatus(409);
            $this->studentRequest('POST', route('student-device.done'), [], $matching, json: true)->assertStatus(409);
            $this->studentRequest('POST', route('student-device.identity'), $details('Quentin', 'R.', 'Neutralis'), $matching)->assertStatus(303);
            $this->studentRequest('GET', route('student-device.show'), device: $matching)->assertOk();
        }
    }

    /**
     * Error responses: unknown path, invalid token, refused origin, staff
     * browser, expired, rate-limited, an address the allowlist refuses, and
     * an unexpected exception with APP_DEBUG on whose message carries
     * `$exceptionMessage`.
     */
    private function collectErrorResponses(string $exceptionMessage = 'boom'): void
    {
        $this->studentRequest('GET', '/s/does-not-exist')->assertNotFound();
        $this->studentRequest('GET', '/s/does-not-exist', json: true)->assertNotFound();
        $this->studentRequest('GET', route('student-device.begin', str_repeat('x', 43)))->assertNotFound();
        $this->studentRequest('POST', route('student-device.claim', str_repeat('x', 43)))->assertNotFound();
        config(['remote_assessment.allowed_ips' => ['192.0.2.1']]);
        $this->studentRequest('GET', route('student-device.entry'))->assertNotFound();
        $this->studentRequest('GET', route('student-device.begin', str_repeat('y', 43)))->assertNotFound();
        $this->studentRequest('PUT', route('student-device.answer'), [], json: true)->assertNotFound();
        config(['remote_assessment.allowed_ips' => []]);
        $this->studentRequest('POST', route('student-device.code'), ['code' => 'AAAA-AAAA'], sameOrigin: false)->assertForbidden();
        $this->studentRequest('POST', route('student-device.answer'), [], 'no-such-device', json: true, sameOrigin: false)->assertForbidden();
        $this->studentRequest('PUT', route('student-device.answer'), [], json: true)->assertStatus(405);

        DB::table('sessions')->insert([
            'id' => 'staff-session', 'user_id' => $this->psychometrician()->id, 'ip_address' => null,
            'user_agent' => null, 'payload' => '', 'last_activity' => now()->getTimestamp(),
        ]);
        $this->studentRequest('GET', route('student-device.entry'), cookies: [(string) config('session.cookie') => 'staff-session'])->assertForbidden();
        DB::table('sessions')->where('id', 'staff-session')->delete();

        // Expired.
        ['short_code' => $shortCode] = $this->createRemoteDraft($this->psychometrician());
        $device = $this->claimWithCode($shortCode);
        $this->travel(61)->minutes();
        $this->studentRequest('GET', route('student-device.show'), device: $device)->assertRedirect(route('student-device.entry'));
        $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)->assertNotFound();
        $this->travelBack();

        // A 500 with debug output on.
        config(['app.debug' => true, 'logging.default' => 'null']);
        $this->mock(RemoteAssessmentService::class)
            ->shouldReceive('deviceDraft')
            ->andThrow(new \RuntimeException($exceptionMessage));
        // The router caches each route's controller instance; drop it so
        // the next requests get the mocked service.
        $this->forgetStudentDeviceControllers();
        $this->studentRequest('GET', route('student-device.show'), device: 'anything')->assertStatus(500);
        $this->studentRequest('GET', route('student-device.state'), device: 'anything', json: true)->assertStatus(500);
        $this->studentRequest('POST', route('student-device.done'), [], 'anything', json: true)->assertStatus(500);
        $this->app->forgetInstance(RemoteAssessmentService::class);
        $this->forgetStudentDeviceControllers();
        config(['app.debug' => false]);

        // Rate limited (last: it blocks code entry from this IP).
        for ($i = 0; $i < 11; $i++) {
            $response = $this->studentRequest('POST', route('student-device.code'), ['code' => 'BBBB-BBBB']);
        }
        $response->assertStatus(429);
    }

    /**
     * A saved, flagged assessment for a student with distinctive details,
     * a second student staged in the Psychometrician's wizard session, and
     * the strings none of the student device's responses may contain.
     *
     * @return array<int, string>
     */
    private function seedSensitiveData(): array
    {
        $this->seedOfficialThresholds();
        $version = $this->createNeutralVersion();
        $psychometrician = $this->psychometrician(['name' => 'Ozymandias Pellucidar', 'email' => 'ozymandias.pellucidar@example.test']);

        $course = Course::factory()->create(['course_code' => 'QZXR', 'course_name' => 'Quorvexian Applied Studies']);
        $yearLevel = YearLevel::factory()->create(['label' => 'Yearzyx Nine']);
        $section = Section::factory()->create(['section_name' => 'Sectionqwv']);

        $this->actingAs($psychometrician);

        $stepOne = fn (string $first, string $middle, string $last) => $this->post(route('assessments.create.student'), [
            'first_name' => $first, 'middle_name' => $middle, 'last_name' => $last, 'gender' => 'Female',
            'privacy_consent' => '1', 'course_id' => $course->id, 'year_level_id' => $yearLevel->id, 'section_id' => $section->id,
        ])->assertRedirect(route('assessments.create.questionnaire'));

        $stepOne('Thaddeusqx', 'W.', 'Brumblewick');
        $this->post(route('assessments.create.questionnaire.store'), [
            'responses' => $this->buildResponses($version, 21, 21, 21),
        ])->assertRedirect(route('assessments.create.result'));
        $this->reviewAndSaveAssessment()->assertRedirect();

        $student = Student::query()->where('last_name', 'Brumblewick')->sole();
        $this->assertSame(3, FlaggedCase::query()->count());

        // Staged only, in the wizard session, while the device is used.
        $stepOne('Zephyrinex', 'K.', 'Quillfeather');

        // The details form lists the Active lookups, so these become
        // inactive: if they appear anywhere now, it's a leak of the saved
        // student's record. runWholeStudentFlow() adds neutral Active ones.
        $course->update(['status' => Course::STATUS_INACTIVE]);
        $yearLevel->update(['status' => YearLevel::STATUS_INACTIVE]);
        $section->update(['status' => Section::STATUS_INACTIVE]);

        return [
            'Thaddeusqx', 'Brumblewick', 'Zephyrinex', 'Quillfeather', $student->student_number,
            'QZXR', 'Quorvexian', 'Yearzyx', 'Sectionqwv', 'Ozymandias', 'Pellucidar', 'ozymandias.pellucidar',
        ];
    }

    /**
     * The active DASS-21 version with neutral statement text, so subscale
     * names can be asserted absent.
     */
    private function createNeutralVersion(): QuestionnaireVersion
    {
        $version = $this->createActiveQuestionnaireVersion();

        foreach ($version->questions as $question) {
            $question->update(['question_text' => "Statement number {$question->item_number} for the respondent"]);
        }

        return $version->fresh('questions');
    }

    private function seedRecordsForCrawl(): void
    {
        $this->seedOfficialThresholds();
        $this->actingAs($this->psychometrician());
        $this->saveAssessmentThroughWizard(QuestionnaireVersion::query()->where('status', QuestionnaireVersion::STATUS_ACTIVE)->firstOrFail(), 21, 21, 21);
        $this->app['auth']->forgetGuards();
    }

    /**
     * No anchor tags at all, and every URL in the response points at the
     * student-device paths or the built assets.
     */
    private function assertNoAppLinks(string $label, TestResponse $response): void
    {
        $body = (string) $response->getContent();
        $origin = $this->studentOrigin();

        $this->assertStringNotContainsStringIgnoringCase('<a ', $body, "{$label} has a link.");

        preg_match_all('#https?://[^\s"\'<>]+#i', $body, $absolute);
        foreach ($absolute[0] as $url) {
            $this->assertTrue(
                str_starts_with($url, $origin.'/s/') || $url === $origin.'/s' || str_starts_with($url, $origin.'/build/'),
                "{$label} links to {$url}."
            );
        }

        preg_match_all('#\b(?:href|src|action)\s*=\s*"([^"]*)"#i', $body, $attributes);
        foreach ($attributes[1] as $url) {
            $path = str_starts_with($url, $origin) ? substr($url, strlen($origin)) : $url;
            $this->assertMatchesRegularExpression('#^/(s(/|$)|build/)#', $path, "{$label} has {$url}.");
        }

        $location = $response->headers->get('Location');
        if ($location !== null) {
            $this->assertStringStartsWith($origin.'/s', $location, "{$label} redirects to {$location}.");
        }
    }

    private function forgetStudentDeviceControllers(): void
    {
        foreach (self::STUDENT_DEVICE_ROUTES as $name) {
            Route::getRoutes()->getByName($name)->controller = null;
        }
    }

    private function routeKey(RoutingRoute $route): string
    {
        return implode('|', $route->methods()).' '.$route->uri();
    }
}
