<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Course;
use App\Models\QuestionnaireVersion;
use App\Models\RemoteAssessmentDraft;
use App\Models\Section;
use App\Models\User;
use App\Models\YearLevel;
use App\Services\RemoteAssessmentService;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Testing\TestResponse;

/**
 * Student-device test helpers. Requests go through `studentRequest()`,
 * which sends only what a real student browser would — the device cookie
 * (encrypted like a real one) and same-origin headers — never the test's
 * default cookies or an acting-as user, and records every response so the
 * leak and header tests can inspect all of them.
 */
trait InteractsWithStudentDevice
{
    /** @var array<int, array{label: string, response: TestResponse}> */
    protected array $studentResponses = [];

    /**
     * @return array{draft: RemoteAssessmentDraft, token: string, short_code: string}
     */
    protected function createRemoteDraft(?User $psychometrician = null, ?QuestionnaireVersion $version = null, bool $collectsIdentity = false): array
    {
        return app(RemoteAssessmentService::class)->create(
            $psychometrician ?? $this->psychometrician(),
            $version ?? QuestionnaireVersion::query()->where('status', QuestionnaireVersion::STATUS_ACTIVE)->firstOrFail(),
            $collectsIdentity,
        );
    }

    protected function studentOrigin(): string
    {
        return rtrim(url('/'), '/');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $cookies  Plain cookie values; encrypted here like the browser's would be.
     * @param  array<string, string>  $server
     */
    protected function studentRequest(
        string $method,
        string $uri,
        array $data = [],
        ?string $device = null,
        bool $json = false,
        array $cookies = [],
        array $server = [],
        bool $sameOrigin = true,
    ): TestResponse {
        if ($device !== null) {
            $cookies[RemoteAssessmentService::DEVICE_COOKIE] = $device;
        }

        $encrypted = [];
        foreach ($cookies as $name => $value) {
            $encrypted[$name] = encrypt(CookieValuePrefix::create($name, app('encrypter')->getKey()).$value, false);
        }

        $server = [
            ...($sameOrigin && $method !== 'GET' ? ['HTTP_ORIGIN' => $this->studentOrigin()] : []),
            ...($json ? ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'] : ['HTTP_ACCEPT' => 'text/html']),
            ...$server,
        ];

        // A real student browser has no staff identity: drop any acting-as
        // user left on the guard by an earlier staff request in the test.
        $this->app['auth']->forgetGuards();

        $response = $json
            ? $this->call($method, $uri, [], $encrypted, [], $server, json_encode($data, JSON_THROW_ON_ERROR))
            : $this->call($method, $uri, $data, $encrypted, [], $server);

        $this->studentResponses[] = ['label' => "{$method} {$uri}", 'response' => $response];

        return $response;
    }

    /**
     * Claim a draft by short code and return the device secret from the
     * response's cookie.
     */
    protected function claimWithCode(string $shortCode): string
    {
        $response = $this->studentRequest('POST', route('student-device.code'), ['code' => $shortCode]);
        $response->assertStatus(303)->assertRedirect(route('student-device.show'));

        $cookie = $response->getCookie(RemoteAssessmentService::DEVICE_COOKIE);
        $this->assertNotNull($cookie, 'The claim did not set a device cookie.');

        return (string) $cookie->getValue();
    }

    protected function consentOnDevice(string $device): void
    {
        $this->studentRequest('POST', route('student-device.consent'), device: $device)
            ->assertStatus(303)
            ->assertRedirect(route('student-device.show'));
    }

    /**
     * Answer every question of the version through the autosave endpoint,
     * with `$value` or, when given, the value in `$responses` per question.
     *
     * @param  array<int, int>|null  $responses
     */
    protected function answerAllOnDevice(string $device, QuestionnaireVersion $version, int $value = 1, ?array $responses = null): void
    {
        foreach ($version->questions as $question) {
            $this->studentRequest('POST', route('student-device.answer'), ['question_id' => $question->id, 'value' => $responses[$question->id] ?? $value], $device, json: true)
                ->assertOk();
        }
    }

    /**
     * Psychometrician side: Step 1 for a new student (the caller is acting
     * as the Psychometrician).
     */
    protected function submitStepOne(string $firstName = 'Mara', string $lastName = 'Lagdameo', string $middleName = 'S.'): void
    {
        $this->post(route('assessments.create.student'), [
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'gender' => 'Female',
            'privacy_consent' => '1',
            'course_id' => Course::factory()->create()->id,
            'year_level_id' => YearLevel::factory()->create()->id,
            'section_id' => Section::factory()->create()->id,
        ])->assertRedirect(route('assessments.create.questionnaire'));
    }

    /**
     * Psychometrician side: "Send to student device" from Step 2.
     *
     * @return array{draft: RemoteAssessmentDraft, token: string, short_code: string}
     */
    protected function sendToStudentDevice(): array
    {
        $this->post(route('assessments.create.remote.store'))->assertRedirect(route('assessments.create.remote'));

        return [
            'draft' => RemoteAssessmentDraft::query()->findOrFail(session('assessment_wizard.remote_draft_id')),
            'token' => (string) session('assessment_wizard.remote_token'),
            'short_code' => (string) session('assessment_wizard.remote_short_code'),
        ];
    }

    /**
     * Psychometrician side: "Let the student fill this in on their device"
     * from Step 1.
     *
     * @return array{draft: RemoteAssessmentDraft, token: string, short_code: string}
     */
    protected function sendStepOneToStudentDevice(): array
    {
        $this->post(route('assessments.create.remote.store-student'))->assertRedirect(route('assessments.create.remote'));

        return [
            'draft' => RemoteAssessmentDraft::query()->findOrFail(session('assessment_wizard.remote_draft_id')),
            'token' => (string) session('assessment_wizard.remote_token'),
            'short_code' => (string) session('assessment_wizard.remote_short_code'),
        ];
    }

    /**
     * The Step 1 fields as the student device sends them, on lookups made
     * here (Active) unless given.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function studentDetails(string $firstName = 'Rhea', string $middleName = 'D.', string $lastName = 'Baculio', array $overrides = []): array
    {
        return [
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'gender' => 'Female',
            'course_id' => $overrides['course_id'] ?? Course::factory()->create()->id,
            'year_level_id' => $overrides['year_level_id'] ?? YearLevel::factory()->create()->id,
            'section_id' => $overrides['section_id'] ?? Section::factory()->create()->id,
            ...$overrides,
        ];
    }

    /**
     * Student side: send the details form; always an empty 303 to /s/q
     * when they pass validation.
     *
     * @param  array<string, mixed>  $details
     */
    protected function sendDetailsOnDevice(string $device, array $details): TestResponse
    {
        return $this->studentRequest('POST', route('student-device.identity'), $details, $device)
            ->assertStatus(303)
            ->assertRedirect(route('student-device.show'));
    }

    /**
     * Student side, for a draft that collects the details: claim,
     * acknowledge the notice, send the details, answer everything, Done.
     * Returns the device secret.
     *
     * @param  array<string, mixed>  $details
     * @param  array<int, int>|null  $responses
     */
    protected function completeWithDetailsOnStudentDevice(string $shortCode, QuestionnaireVersion $version, array $details, ?array $responses = null, int $value = 1): string
    {
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $this->sendDetailsOnDevice($device, $details);
        $this->answerAllOnDevice($device, $version, $value, $responses);
        $this->studentRequest('POST', route('student-device.done'), [], $device, json: true)
            ->assertOk()->assertExactJson(['state' => 'locked']);

        return $device;
    }

    /**
     * Student side: claim, acknowledge the notice, answer everything, Done.
     * Returns the device secret.
     *
     * @param  array<int, int>|null  $responses
     */
    protected function completeOnStudentDevice(string $shortCode, QuestionnaireVersion $version, ?array $responses = null, int $value = 1): string
    {
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $this->answerAllOnDevice($device, $version, $value, $responses);
        $this->studentRequest('POST', route('student-device.done'), [], $device, json: true)
            ->assertOk()->assertExactJson(['state' => 'locked']);

        return $device;
    }
}
