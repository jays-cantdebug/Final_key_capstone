<?php

declare(strict_types=1);

namespace Tests\Feature\RemoteAssessment;

use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\QuestionnaireVersion;
use App\Models\RemoteAssessmentDraft;
use App\Models\Student;
use App\Models\User;
use App\Services\RemoteAssessmentService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithDomainData;
use Tests\Concerns\InteractsWithStudentDevice;
use Tests\TestCase;

/**
 * Who can touch a student-device draft, and that it disappears on every
 * way out of the wizard: only the Psychometrician who created it, from the
 * session that created it; a Guidance Counselor is refused everywhere;
 * answers never travel in a URL; drafts are never audited.
 */
class RemoteAssessmentSecurityTest extends TestCase
{
    use InteractsWithDomainData;
    use InteractsWithStudentDevice;
    use RefreshDatabase;

    /** Every Psychometrician-side remote route, [method, route name]. */
    private const OWNER_ROUTES = [
        ['GET', 'assessments.create.remote'],
        ['GET', 'assessments.create.remote.status'],
        ['POST', 'assessments.create.remote.new-code'],
        ['POST', 'assessments.create.remote.return'],
        ['POST', 'assessments.create.remote.restart'],
        ['DELETE', 'assessments.create.remote.cancel'],
        ['POST', 'assessments.create.remote.submit'],
    ];

    private User $owner;

    private QuestionnaireVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        config(['remote_assessment.student_consent' => true]);
        $this->seedOfficialThresholds();
        $this->version = $this->createActiveQuestionnaireVersion();
        $this->owner = $this->psychometrician();
        $this->actingAs($this->owner);
    }

    public function test_another_psychometrician_cannot_reach_someone_elses_draft(): void
    {
        $this->submitStepOne();
        ['draft' => $draft, 'short_code' => $shortCode] = $this->sendToStudentDevice();
        $device = $this->completeOnStudentDevice($shortCode, $this->version, value: 3);
        $before = $this->snapshot($draft->fresh());

        $other = $this->psychometrician();

        // Their own wizard, with no draft at all.
        $this->flushSession();
        $this->actingAs($other);
        $this->submitStepOne('Other', 'Person');
        $this->assertEveryOwnerRouteIsNotFound();

        // Even with the owner's draft id planted in their session.
        $this->withSession(['assessment_wizard.remote_draft_id' => $draft->id]);
        $this->assertEveryOwnerRouteIsNotFound();

        // And their own draft doesn't give them the owner's.
        ['draft' => $theirs] = $this->sendToStudentDevice();
        $this->assertNotSame($draft->id, $theirs->id);
        $this->getJson(route('assessments.create.remote.status'))->assertOk()->assertJsonPath('state', 'pending')->assertJsonPath('answers', []);

        $this->assertSame($before, $this->snapshot($draft->fresh()));
        $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)->assertExactJson(['state' => 'locked']);
    }

    public function test_a_draft_that_does_not_exist_is_not_found(): void
    {
        $this->submitStepOne();

        $this->assertEveryOwnerRouteIsNotFound();

        $this->withSession(['assessment_wizard.remote_draft_id' => 999999]);
        $this->assertEveryOwnerRouteIsNotFound();

        $this->withSession(['assessment_wizard.remote_draft_id' => 'not-a-number']);
        $this->assertEveryOwnerRouteIsNotFound();
    }

    public function test_a_guidance_counselor_is_refused_on_every_remote_route(): void
    {
        $this->submitStepOne();
        ['draft' => $draft] = $this->sendToStudentDevice();
        $before = $this->snapshot($draft->fresh());

        $this->actingAs($this->guidanceCounselor());

        foreach ([['POST', 'assessments.create.remote.store'], ...self::OWNER_ROUTES] as [$method, $name]) {
            $this->call($method, route($name), ['privacy_consent' => '1'])->assertForbidden();
            $this->json($method, route($name))->assertForbidden();
        }

        $this->assertSame($before, $this->snapshot($draft->fresh()));
        $this->assertSame(1, RemoteAssessmentDraft::query()->count());
    }

    public function test_the_polling_json_is_only_for_the_owner(): void
    {
        $this->submitStepOne();
        ['short_code' => $shortCode] = $this->sendToStudentDevice();
        $this->completeOnStudentDevice($shortCode, $this->version, value: 3);

        $this->actingAs($this->owner);
        $ownerJson = $this->getJson(route('assessments.create.remote.status'))->assertOk();
        $this->assertCount(21, $ownerJson->json('answers'));

        $this->flushSession();
        $this->actingAs($this->psychometrician());
        $response = $this->withSession(['assessment_wizard.remote_draft_id' => RemoteAssessmentDraft::query()->sole()->id])
            ->getJson(route('assessments.create.remote.status'));
        $response->assertNotFound()->assertExactJson(['state' => 'gone']);

        $this->actingAs($this->guidanceCounselor());
        $this->getJson(route('assessments.create.remote.status'))->assertForbidden()->assertJsonMissingPath('answers');

        // Not logged in at all.
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->getJson(route('assessments.create.remote.status'))->assertUnauthorized()->assertJsonMissingPath('answers');
    }

    public function test_answers_never_travel_in_a_url(): void
    {
        $responses = [];
        $record = function (TestResponse $response) use (&$responses): TestResponse {
            $responses[] = $response;

            return $response;
        };

        $this->submitStepOne();
        $record($this->post(route('assessments.create.remote.store')));
        $shortCode = (string) session('assessment_wizard.remote_short_code');
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $this->answerAllOnDevice($device, $this->version, 2);
        $this->studentRequest('POST', route('student-device.done'), [], $device, json: true)->assertOk();
        $this->actingAs($this->owner);

        $record($this->get(route('assessments.create.remote')));
        $record($this->getJson(route('assessments.create.remote.status')));
        $record($this->post(route('assessments.create.remote.return')));
        $this->studentRequest('POST', route('student-device.done'), [], $device, json: true)->assertOk();
        $this->actingAs($this->owner);
        $record($this->post(route('assessments.create.remote.submit')));
        $record($this->get(route('assessments.create.questionnaire')));
        $record($this->get(route('assessments.create.result')));
        $record($this->post(route('assessments.create.submit'), ['is_confirmed' => '1']));

        foreach ([...$responses, ...array_column($this->studentResponses, 'response')] as $response) {
            $location = (string) $response->headers->get('Location');
            $this->assertStringNotContainsString('?', $location, "Redirect with a query string: {$location}");

            preg_match_all('#(?:href|action|src)="([^"]*)"|"statusUrl":"([^"]*)"|(?:Url|url)\\\\?":\\\\?"([^"\\\\]*)#', (string) $response->getContent(), $matches);
            foreach (array_filter([...$matches[1], ...$matches[2], ...$matches[3]]) as $url) {
                $this->assertDoesNotMatchRegularExpression('#[?&](responses|answers?|value|rev=\d+&)#i', html_entity_decode($url), "URL carries answers: {$url}");
                $this->assertStringNotContainsString('responses', html_entity_decode($url));
            }
        }

    }

    public function test_drafts_are_never_audited(): void
    {
        $this->submitStepOne();
        $before = AuditLog::query()->count();

        ['short_code' => $shortCode] = $this->sendToStudentDevice();
        $this->get(route('assessments.create.remote'));
        $this->getJson(route('assessments.create.remote.status'));
        $this->post(route('assessments.create.remote.new-code'));
        $shortCode = (string) session('assessment_wizard.remote_short_code');
        $this->completeOnStudentDevice($shortCode, $this->version);
        $this->actingAs($this->owner);
        $this->post(route('assessments.create.remote.return'));
        $this->delete(route('assessments.create.remote.cancel'));
        ['short_code' => $shortCode] = $this->sendToStudentDevice();
        $this->completeOnStudentDevice($shortCode, $this->version);
        $this->actingAs($this->owner);
        $this->post(route('assessments.create.remote.submit'))->assertRedirect(route('assessments.create.result'));

        $this->assertSame($before, AuditLog::query()->count());
        $this->assertSame(0, AuditLog::query()->where('module', 'like', '%emote%')->count());
    }

    public function test_step_one_post_discards_the_draft(): void
    {
        $this->submitStepOne();
        ['draft' => $draft] = $this->sendToStudentDevice();

        $this->submitStepOne('Someone', 'Else');

        $this->assertModelMissing($draft);
        $this->assertNull(session('assessment_wizard.remote_draft_id'));
    }

    public function test_starting_take_again_discards_the_draft(): void
    {
        $this->submitStepOne();
        ['draft' => $draft] = $this->sendToStudentDevice();

        $this->get(route('assessments.create.retake', Student::factory()->create()));

        $this->assertModelMissing($draft);
        $this->assertNull(session('assessment_wizard.remote_draft_id'));
        $this->assertNull(session('assessment_wizard.remote_short_code'));
        $this->assertNull(session('assessment_wizard.remote_token'));
    }

    public function test_answering_on_this_device_instead_discards_the_draft(): void
    {
        $this->submitStepOne();
        ['draft' => $draft] = $this->sendToStudentDevice();

        $this->post(route('assessments.create.questionnaire.store'), [
            'questionnaire_version_id' => $this->version->id,
            'responses' => $this->buildResponses($this->version, 1, 1, 1),
        ])->assertRedirect(route('assessments.create.result'));

        $this->assertModelMissing($draft);
        $this->assertNull(session('assessment_wizard.remote_draft_id'));
        $this->assertNull(session('assessment_wizard.administration_mode'));
    }

    public function test_a_successful_save_discards_any_draft(): void
    {
        $this->submitStepOne();
        ['short_code' => $shortCode] = $this->sendToStudentDevice();
        $this->completeOnStudentDevice($shortCode, $this->version);
        $this->actingAs($this->owner);
        $this->post(route('assessments.create.remote.submit'));
        // A draft started meanwhile (another tab) ends with the save.
        ['draft' => $stray] = app(RemoteAssessmentService::class)->create($this->owner, $this->version);

        $this->reviewAndSaveAssessment()->assertRedirect();

        $this->assertModelMissing($stray);
        $this->assertSame(Assessment::ADMINISTRATION_STUDENT_DEVICE, Assessment::query()->sole()->administration_mode);
    }

    public function test_the_duplicate_conflict_at_save_discards_any_draft(): void
    {
        $this->submitStepOne('Ana', 'Villareal', 'B.');
        ['short_code' => $shortCode] = $this->sendToStudentDevice();
        $this->completeOnStudentDevice($shortCode, $this->version);
        $this->actingAs($this->owner);
        $this->post(route('assessments.create.remote.submit'));
        $this->get(route('assessments.create.result'));

        Student::factory()->create(['first_name' => 'Ana', 'middle_name' => 'B.', 'last_name' => 'Villareal']);
        ['draft' => $stray] = app(RemoteAssessmentService::class)->create($this->owner, $this->version);

        $this->post(route('assessments.create.submit'), ['is_confirmed' => '1'])->assertRedirect(route('assessments.create'));

        $this->assertModelMissing($stray);
        $this->assertSame(0, Assessment::query()->count());
    }

    public function test_logging_out_discards_the_draft_of_that_session(): void
    {
        $this->submitStepOne();
        ['draft' => $draft] = $this->sendToStudentDevice();

        $this->post(route('logout'))->assertRedirect('/');

        $this->assertModelMissing($draft);
    }

    public function test_a_logout_from_a_session_without_the_draft_leaves_it_alone(): void
    {
        $this->submitStepOne();
        ['draft' => $draft] = $this->sendToStudentDevice();

        // E.g. a second session of the same account being signed out by
        // EnsureSingleActiveSession: it never held this draft's id.
        $this->flushSession();
        $this->actingAs($this->owner);
        $this->post(route('logout'));

        $this->assertModelExists($draft);
    }

    public function test_force_logout_discards_the_users_draft(): void
    {
        $this->submitStepOne();
        ['draft' => $draft] = $this->sendToStudentDevice();

        $this->flushSession();
        $this->actingAs($this->psychometrician())->patch(route('users.force-logout', $this->owner))->assertRedirect();

        $this->assertModelMissing($draft);
    }

    public function test_deactivation_discards_the_users_draft(): void
    {
        $this->submitStepOne();
        ['draft' => $draft] = $this->sendToStudentDevice();

        $this->flushSession();
        $this->actingAs($this->psychometrician())->patch(route('users.deactivate', $this->owner))->assertRedirect();

        $this->assertFalse($this->owner->fresh()->is_active);
        $this->assertModelMissing($draft);
    }

    public function test_expired_drafts_are_pruned_on_every_create_and_poll(): void
    {
        ['draft' => $expiredOnCreate] = app(RemoteAssessmentService::class)->create($this->psychometrician(), $this->version);
        $this->travel(61)->minutes();

        $this->submitStepOne();
        $this->sendToStudentDevice();
        $this->assertModelMissing($expiredOnCreate);

        ['draft' => $expiredOnPoll] = app(RemoteAssessmentService::class)->create($this->psychometrician(), $this->version);
        $this->travel(61)->minutes();
        ['draft' => $live] = app(RemoteAssessmentService::class)->create($this->psychometrician(), $this->version);

        $this->getJson(route('assessments.create.remote.status'));

        $this->assertModelMissing($expiredOnPoll);
        $this->assertModelExists($live);
    }

    public function test_the_prune_is_scheduled_every_minute(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('model:prune')
            ->assertSuccessful();

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'model:prune'));
        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertStringContainsString('RemoteAssessmentDraft', (string) $event->command);
    }

    public function test_the_same_device_flow_never_creates_a_draft_and_records_no_mode(): void
    {
        $assessment = $this->saveAssessmentThroughWizard($this->version, 3, 3, 3);

        $this->assertNull($assessment->administration_mode);
        $this->assertSame(0, RemoteAssessmentDraft::query()->count());
        $this->assertSame(21, $assessment->responses()->count());
    }

    private function assertEveryOwnerRouteIsNotFound(): void
    {
        foreach (self::OWNER_ROUTES as [$method, $name]) {
            $response = $this->call($method, route($name), ['privacy_consent' => '1']);
            $this->assertSame(404, $response->getStatusCode(), "{$method} {$name}");
            $this->assertStringNotContainsString('"answers"', (string) $response->getContent());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(RemoteAssessmentDraft $draft): array
    {
        return [
            ...$draft->getAttributes(),
            'responses' => $draft->responses,
        ];
    }
}
