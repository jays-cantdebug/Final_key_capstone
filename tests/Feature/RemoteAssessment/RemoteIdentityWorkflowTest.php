<?php

declare(strict_types=1);

namespace Tests\Feature\RemoteAssessment;

use App\Http\Controllers\AssessmentWizardController;
use App\Http\Controllers\RemoteAssessmentController;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\QuestionnaireVersion;
use App\Models\RemoteAssessmentDraft;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Models\YearLevel;
use App\Services\RemoteAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithDomainData;
use Tests\Concerns\InteractsWithStudentDevice;
use Tests\TestCase;

/**
 * The Psychometrician's side when the student fills in Step 1 on the
 * student device: starting it from Step 1, the details shown live, the
 * duplicate-student panel and the held device, correcting a typo (answers
 * stay read-only), Submit re-running Step 1's checks, nothing in `students`
 * before Confirm & Save, no audit entries, and the draft — details
 * included — deleted on every exit.
 */
class RemoteIdentityWorkflowTest extends TestCase
{
    use InteractsWithDomainData;
    use InteractsWithStudentDevice;
    use RefreshDatabase;

    private User $owner;

    private QuestionnaireVersion $version;

    private Course $course;

    private YearLevel $yearLevel;

    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        config(['remote_assessment.student_consent' => true, 'remote_assessment.url' => 'http://192.168.1.10']);
        $this->seedOfficialThresholds();
        $this->version = $this->createActiveQuestionnaireVersion();
        $this->course = Course::factory()->create(['course_code' => 'BSPS', 'course_name' => 'Bachelor of Plain Studies']);
        $this->yearLevel = YearLevel::factory()->create(['label' => 'First Year']);
        $this->section = Section::factory()->create(['section_name' => 'Rizal']);
        $this->owner = $this->psychometrician();
        $this->actingAs($this->owner);
    }

    public function test_step_one_offers_the_student_device_and_starts_a_draft_that_collects_the_details(): void
    {
        $this->get(route('assessments.create'))
            ->assertOk()
            ->assertSee('action="'.route('assessments.create.remote.store-student').'"', false)
            ->assertSee('Let the student fill this in on their device');

        // Starting it ends whatever was staged before, like a Step 1 POST.
        $this->submitStepOne('Earlier', 'Person');
        ['draft' => $earlier] = $this->sendToStudentDevice();
        ['draft' => $draft] = $this->sendStepOneToStudentDevice();

        $this->assertModelMissing($earlier);
        $this->assertTrue($draft->collects_identity);
        $this->assertTrue($draft->requires_consent);
        $this->assertNull(session('assessment_wizard.student_data'));
        $this->assertNull(session('assessment_wizard.responses'));

        $this->get(route('assessments.create.remote'))
            ->assertOk()
            ->assertSee('waiting for the student’s details', false)
            ->assertSee('data-identity-waiting', false)
            ->assertSee('guest or private browser window')
            ->assertSee('HTTPS')
            ->assertSee('name="privacy_consent"', false);

        // Step 1 then points at the session in progress.
        $this->get(route('assessments.create'))->assertOk()->assertSee('data-remote-in-progress', false);
    }

    public function test_the_live_page_shows_the_details_when_they_arrive_and_the_poll_carries_only_flags(): void
    {
        ['short_code' => $shortCode] = $this->sendStepOneToStudentDevice();
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $this->actingAs($this->owner);

        $waiting = $this->getJson(route('assessments.create.remote.status'))->assertOk()->json();
        $this->assertSame('identity', $waiting['state']);
        $this->assertFalse($waiting['identity_received']);

        $this->sendDetailsOnDevice($device, $this->details('Rhea', 'D.', 'Baculio'));
        $this->actingAs($this->owner);

        $poll = $this->getJson(route('assessments.create.remote.status', ['rev' => $waiting['rev']]))->assertOk();
        $poll->assertJsonPath('state', 'answering')->assertJsonPath('identity_received', true)->assertJsonPath('held', false);
        foreach (['Rhea', 'Baculio', 'BSPS', 'first_name'] as $detail) {
            $this->assertStringNotContainsString($detail, (string) $poll->getContent());
        }

        $this->get(route('assessments.create.remote'))
            ->assertOk()
            ->assertSee('Rhea D. Baculio')
            ->assertSee('data-identity-form', false)
            ->assertSee('value="Rhea"', false)
            ->assertSee('<option value="'.$this->course->id.'" selected', false)
            ->assertSee('Entered by the student at')
            ->assertDontSee('data-identity-duplicate', false);
    }

    public function test_the_whole_flow_saves_nothing_to_students_before_confirm_and_save(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        ['draft' => $draft, 'short_code' => $shortCode] = $this->sendStepOneToStudentDevice();
        $responses = $this->buildResponses($this->version, 21, 10, 4);

        $this->travelTo(now()->setTime(10, 4));
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $this->assertSame(0, Student::query()->count());
        $this->sendDetailsOnDevice($device, $this->details('Lia', 's.', 'Santos'));
        $this->assertSame(0, Student::query()->count());
        $this->answerAllOnDevice($device, $this->version, responses: $responses);
        $this->studentRequest('POST', route('student-device.done'), [], $device, json: true)->assertOk();
        $this->assertSame(0, Student::query()->count());

        $this->travelTo(now()->setTime(10, 9));
        $this->actingAs($this->owner);
        $this->post(route('assessments.create.remote.submit'), ['privacy_consent' => '1'])->assertRedirect(route('assessments.create.result'));
        $this->assertModelMissing($draft);
        $this->assertSame(0, Student::query()->count());
        $this->assertSame('Lia', session('assessment_wizard.student_data.first_name'));
        $this->assertSame('S.', session('assessment_wizard.student_data.middle_name'));

        // Step 2 is read-only, as after any student-device Submit.
        $this->get(route('assessments.create.questionnaire'))->assertOk()->assertSee('Lia S. Santos')->assertDontSee('action="'.route('assessments.create.questionnaire.store').'"', false);
        $this->get(route('assessments.create.result'))->assertOk()->assertSee('Lia S. Santos');
        $this->assertSame(0, Student::query()->count());

        $this->post(route('assessments.create.submit'), ['is_confirmed' => '1'])->assertRedirect();

        $student = Student::query()->sole();
        $this->assertSame(['Lia', 'S.', 'Santos', 'Female'], [$student->first_name, $student->middle_name, $student->last_name, $student->gender]);
        $this->assertSame([$this->course->id, $this->yearLevel->id, $this->section->id], [$student->course_id, $student->year_level_id, $student->section_id]);
        // The student's own acknowledgment on the device, not Submit's time.
        $this->assertSame('10:04', $student->privacy_consent_at->format('H:i'));
        $assessment = Assessment::query()->with('result')->sole();
        $this->assertSame(Assessment::ADMINISTRATION_STUDENT_DEVICE, $assessment->administration_mode);
        $this->assertSame(21, $assessment->result->depression_raw_score);
        $this->assertSame(10, $assessment->result->anxiety_raw_score);
        $this->assertSame(4, $assessment->result->stress_raw_score);
        $this->assertSame(0, RemoteAssessmentDraft::query()->count());
    }

    public function test_the_psychometrician_can_correct_a_typo_but_not_the_answers(): void
    {
        ['draft' => $draft, 'short_code' => $shortCode] = $this->sendStepOneToStudentDevice();
        $responses = $this->buildResponses($this->version, 9, 9, 9);
        $this->completeWithDetailsOnStudentDevice($shortCode, $this->version, $this->details('Jonh', 'B.', 'Dela Cruz'), $responses);
        $this->actingAs($this->owner);
        $answersBefore = $draft->fresh()->responses;

        $this->from(route('assessments.create.remote'))->put(route('assessments.create.remote.identity'), [
            ...$this->details(' John ', 'b.', 'Dela  Cruz'),
            // Never taken from this form.
            'responses' => array_map(fn (): int => 0, $responses),
            'status' => 'answering',
        ])->assertRedirect(route('assessments.create.remote'))->assertSessionHas('status', 'The details were corrected.');

        $draft->refresh();
        $this->assertSame('John', $draft->identity['first_name']);
        $this->assertSame('B.', $draft->identity['middle_name']);
        $this->assertSame('Dela Cruz', $draft->identity['last_name']);
        $this->assertNotNull($draft->identity_corrected_at);
        $this->assertSame($answersBefore, $draft->responses);
        $this->assertSame(RemoteAssessmentDraft::STATUS_LOCKED, $draft->status);

        // Step 1's validation applies to the correction too.
        $this->from(route('assessments.create.remote'))->put(route('assessments.create.remote.identity'), $this->details('John', 'Bee', 'Dela Cruz'))
            ->assertRedirect(route('assessments.create.remote'))
            ->assertSessionHasErrorsIn('identity', ['middle_name']);
        $this->assertSame('B.', $draft->fresh()->identity['middle_name']);

        $this->get(route('assessments.create.remote'))->assertOk()->assertSee('corrected by you at');

        $this->post(route('assessments.create.remote.submit'), ['privacy_consent' => '1'])->assertRedirect(route('assessments.create.result'));
        $this->reviewAndSaveAssessment()->assertRedirect();

        $student = Student::query()->sole();
        $this->assertSame('John B. Dela Cruz', $student->full_name);
        $saved = Assessment::query()->sole()->responses()->pluck('answer_value', 'dass_question_id')->all();
        ksort($saved);
        ksort($responses);
        $this->assertSame($responses, $saved);
    }

    public function test_a_correction_needs_details_from_the_student_first(): void
    {
        ['draft' => $draft, 'short_code' => $shortCode] = $this->sendStepOneToStudentDevice();
        $this->consentOnDevice($this->claimWithCode($shortCode));
        $this->actingAs($this->owner);

        $this->put(route('assessments.create.remote.identity'), $this->details())
            ->assertRedirect(route('assessments.create.remote'))
            ->assertSessionHasErrors(['remote' => RemoteAssessmentController::NO_DETAILS_MESSAGE]);
        $this->assertNull($draft->fresh()->identity);

        // A draft where the Psychometrician typed Step 1 has none to correct.
        $this->submitStepOne();
        ['draft' => $plain] = $this->sendToStudentDevice();
        $this->put(route('assessments.create.remote.identity'), $this->details())->assertSessionHasErrors('remote');
        $this->assertNull($plain->fresh()->identity);
    }

    public function test_an_active_match_holds_the_device_and_shows_the_record_with_take_again(): void
    {
        $existing = $this->existingStudent('Cara', 'D.', 'Evangelista');
        ['draft' => $draft, 'short_code' => $shortCode] = $this->sendStepOneToStudentDevice();
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $this->sendDetailsOnDevice($device, $this->details('cara', 'd.', 'EVANGELISTA'));
        $this->actingAs($this->owner);

        $this->getJson(route('assessments.create.remote.status'))->assertJsonPath('state', 'held')->assertJsonPath('held', true);

        $this->get(route('assessments.create.remote'))
            ->assertOk()
            ->assertSee('data-held-reason', false)
            ->assertSee('matches an existing active student')
            ->assertSee('data-identity-duplicate="active"', false)
            ->assertSee($existing->student_number)
            ->assertSee('1 assessment')
            ->assertSee(route('assessments.create.retake', $existing), false);

        // Submit can't go ahead either (the device can't reach Done anyway).
        $this->post(route('assessments.create.remote.submit'), ['privacy_consent' => '1'])->assertSessionHasErrors('remote');

        // Take Again through the panel ends this draft; the retake needs a new code.
        $this->get(route('assessments.create.retake', $existing))->assertRedirect(route('assessments.create.questionnaire'));
        $this->assertModelMissing($draft);
        $this->studentRequest('GET', route('student-device.show'), device: $device)->assertNotFound();
        $this->actingAs($this->owner);
        ['draft' => $retake] = $this->sendToStudentDevice();
        $this->assertFalse($retake->collects_identity);
        $this->assertSame(1, Student::query()->count());
    }

    public function test_correcting_a_held_name_releases_the_device(): void
    {
        $this->existingStudent('Cara', 'D.', 'Evangelista');
        ['draft' => $draft, 'short_code' => $shortCode] = $this->sendStepOneToStudentDevice();
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $this->sendDetailsOnDevice($device, $this->details('Cara', 'D.', 'Evangelista'));
        $this->actingAs($this->owner);

        // Still the same name: still held.
        $this->put(route('assessments.create.remote.identity'), $this->details('Cara', 'D.', 'Evangelista'))->assertSessionHas('status', 'The details were corrected.');
        $this->assertNotNull($draft->fresh()->held_at);

        $this->put(route('assessments.create.remote.identity'), $this->details('Carla', 'D.', 'Evangelista'))
            ->assertSessionHas('status', 'The details were corrected. No active student has this name now, so the student device continues.');
        $this->assertNull($draft->fresh()->held_at);

        $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)->assertExactJson(['state' => 'answering']);
        $this->answerAllOnDevice($device, $this->version);
        $this->studentRequest('POST', route('student-device.done'), [], $device, json: true)->assertOk();
        $this->actingAs($this->owner);
        $this->post(route('assessments.create.remote.submit'), ['privacy_consent' => '1'])->assertRedirect(route('assessments.create.result'));
        $this->reviewAndSaveAssessment()->assertRedirect();
        $this->assertSame(1, Student::query()->where('first_name', 'Carla')->count());
    }

    public function test_submit_reruns_the_duplicate_check_on_corrected_details(): void
    {
        $existing = $this->existingStudent('Cara', 'D.', 'Evangelista');
        ['short_code' => $shortCode] = $this->sendStepOneToStudentDevice();
        $this->completeWithDetailsOnStudentDevice($shortCode, $this->version, $this->details('Carla', 'D.', 'Evangelista'));
        $this->actingAs($this->owner);

        // A "correction" that turns it into an existing active student.
        $this->put(route('assessments.create.remote.identity'), $this->details('Cara', 'D.', 'Evangelista'));
        $this->get(route('assessments.create.remote'))->assertSee($existing->student_number);

        $this->post(route('assessments.create.remote.submit'), ['privacy_consent' => '1'])
            ->assertRedirect(route('assessments.create.remote'))
            ->assertSessionHasErrors(['remote' => RemoteAssessmentController::ACTIVE_MATCH_MESSAGE]);
        $this->assertNull(session('assessment_wizard.responses'));
        $this->assertNull(session('assessment_wizard.student_data'));
        $this->assertSame(1, RemoteAssessmentDraft::query()->count());
    }

    public function test_an_archived_match_continues_on_the_device_and_needs_the_confirm_box_at_submit(): void
    {
        $archived = $this->existingStudent('Bento', 'C.', 'Dimaculangan');
        $archived->delete();
        ['short_code' => $shortCode] = $this->sendStepOneToStudentDevice();
        $this->completeWithDetailsOnStudentDevice($shortCode, $this->version, $this->details('Bento', 'C.', 'Dimaculangan'));
        $this->actingAs($this->owner);

        $this->get(route('assessments.create.remote'))
            ->assertOk()
            ->assertSee('data-identity-duplicate="archived"', false)
            ->assertSee($archived->student_number)
            ->assertSee('name="confirm_archived_match"', false)
            ->assertDontSee('data-held-reason', false);

        $this->post(route('assessments.create.remote.submit'), ['privacy_consent' => '1'])
            ->assertSessionHasErrors(['confirm_archived_match' => AssessmentWizardController::CONFIRM_ARCHIVED_MATCH_MESSAGE]);
        $this->assertNull(session('assessment_wizard.responses'));

        $this->post(route('assessments.create.remote.submit'), ['privacy_consent' => '1', 'confirm_archived_match' => '1'])
            ->assertRedirect(route('assessments.create.result'));
        $this->assertSame([$archived->id], session('assessment_wizard.acknowledged_archived_ids'));
        $this->reviewAndSaveAssessment()->assertRedirect();

        $student = Student::query()->sole();
        $this->assertNotSame($archived->id, $student->id);
        $this->assertSame(1, AuditLog::query()->where('action', 'Archived Match Confirmed')->where('record_id', $student->id)->count());
    }

    public function test_submit_needs_the_staff_attestation_and_valid_details(): void
    {
        ['draft' => $draft, 'short_code' => $shortCode] = $this->sendStepOneToStudentDevice();
        $this->completeWithDetailsOnStudentDevice($shortCode, $this->version, $this->details());
        $this->actingAs($this->owner);

        $this->get(route('assessments.create.remote'))->assertSee('name="privacy_consent"', false);
        $this->from(route('assessments.create.remote'))->post(route('assessments.create.remote.submit'))
            ->assertRedirect(route('assessments.create.remote'))
            ->assertSessionHasErrors('privacy_consent');
        $this->assertNull(session('assessment_wizard.responses'));

        // Details that no longer pass Step 1's rules (e.g. edited in the
        // database) are refused at Submit, never staged.
        $draft->refresh()->forceFill(['identity' => [...$draft->identity, 'middle_name' => 'Xx']])->save();
        $this->post(route('assessments.create.remote.submit'), ['privacy_consent' => '1'])
            ->assertRedirect(route('assessments.create.remote'))
            ->assertSessionHasErrors(['remote' => RemoteAssessmentController::INVALID_DETAILS_MESSAGE])
            ->assertSessionHasErrorsIn('identity', ['middle_name']);
        $this->assertNull(session('assessment_wizard.student_data'));
    }

    public function test_the_draft_and_the_details_are_never_audited(): void
    {
        ['short_code' => $shortCode] = $this->sendStepOneToStudentDevice();
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $this->sendDetailsOnDevice($device, $this->details('Xiomara', 'Q.', 'Ylagan'));
        $this->actingAs($this->owner);
        $this->put(route('assessments.create.remote.identity'), $this->details('Xiomarra', 'Q.', 'Ylaganx'));
        $this->get(route('assessments.create.remote'));
        $this->delete(route('assessments.create.remote.cancel'))->assertRedirect(route('assessments.create'));

        $audit = json_encode(DB::table('audit_logs')->get(), JSON_THROW_ON_ERROR);
        foreach (['Xiomara', 'Ylagan', 'emote', 'draft', 'identity'] as $term) {
            $this->assertStringNotContainsStringIgnoringCase($term, $audit);
        }
        $this->assertSame(0, AuditLog::query()->where('module', 'Students')->count());

        // Only Confirm & Save writes the student record, and its audit entry.
        ['short_code' => $shortCode] = $this->sendStepOneToStudentDevice();
        $this->completeWithDetailsOnStudentDevice($shortCode, $this->version, $this->details('Xiomara', 'Q.', 'Ylagan'));
        $this->actingAs($this->owner);
        $this->post(route('assessments.create.remote.submit'), ['privacy_consent' => '1']);
        $this->assertStringNotContainsString('Ylagan', json_encode(DB::table('audit_logs')->get(), JSON_THROW_ON_ERROR));
        $this->reviewAndSaveAssessment()->assertRedirect();

        $this->assertStringContainsString('Ylagan', json_encode(DB::table('audit_logs')->where('record_id', Student::query()->sole()->id)->get(), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsStringIgnoringCase('emote', json_encode(DB::table('audit_logs')->get(), JSON_THROW_ON_ERROR));
    }

    /**
     * Every way the wizard can end while a draft holds the student's
     * details deletes the whole row, details included.
     */
    public function test_a_draft_with_details_is_deleted_on_every_exit(): void
    {
        $exits = [
            'submit' => function (): void {
                $this->post(route('assessments.create.remote.submit'), ['privacy_consent' => '1'])->assertRedirect(route('assessments.create.result'));
            },
            'cancel' => fn () => $this->delete(route('assessments.create.remote.cancel')),
            'step one' => fn () => $this->submitStepOne('Someone', 'Else'),
            'a new student-device run' => function (): void {
                $this->post(route('assessments.create.remote.store-student'));
            },
            'take again' => fn () => $this->get(route('assessments.create.retake', Student::factory()->create())),
            'logout' => fn () => $this->post(route('logout')),
            'force logout' => function (): void {
                $this->actingAs($this->psychometrician())->patch(route('users.force-logout', $this->owner))->assertRedirect();
            },
            'deactivation' => function (): void {
                $this->actingAs($this->psychometrician())->patch(route('users.deactivate', $this->owner))->assertRedirect();
                $this->owner->forceFill(['is_active' => true])->save();
            },
            'expiry (poll)' => function (): void {
                $this->travel(76)->minutes();
                $this->getJson(route('assessments.create.remote.status'));
                $this->travelBack();
            },
            'expiry (model:prune)' => function (): void {
                $this->travel(76)->minutes();
                Artisan::call('model:prune', ['--model' => [RemoteAssessmentDraft::class]]);
                $this->travelBack();
            },
        ];

        foreach ($exits as $exit => $run) {
            $this->flushSession();
            $this->actingAs($this->owner);
            ['draft' => $draft, 'short_code' => $shortCode] = $this->sendStepOneToStudentDevice();
            $this->completeWithDetailsOnStudentDevice($shortCode, $this->version, $this->details('Exitname', 'E.', 'Exitcase'));
            $this->actingAs($this->owner);
            $this->assertNotNull(DB::table('remote_assessment_drafts')->where('id', $draft->id)->value('identity'), $exit);

            $run();

            $this->assertSame(0, DB::table('remote_assessment_drafts')->where('id', $draft->id)->count(), "{$exit} left the draft.");
            $this->assertSame(0, Student::query()->where('last_name', 'Exitcase')->count(), "{$exit} saved the student.");
            RemoteAssessmentDraft::query()->delete();
        }

        // Declining keeps only a stripped row (no details) until expiry.
        $this->flushSession();
        $this->actingAs($this->owner);
        ['draft' => $draft, 'short_code' => $shortCode] = $this->sendStepOneToStudentDevice();
        $this->studentRequest('POST', route('student-device.decline'), device: $this->claimWithCode($shortCode))->assertOk();
        $row = DB::table('remote_assessment_drafts')->where('id', $draft->id)->first();
        $this->assertNull($row->identity);
        $this->assertNull($row->responses);
        $this->assertNull($row->token_hash);
    }

    public function test_the_duplicate_conflict_at_save_starts_over_with_nothing_saved(): void
    {
        ['short_code' => $shortCode] = $this->sendStepOneToStudentDevice();
        $this->completeWithDetailsOnStudentDevice($shortCode, $this->version, $this->details('Ana', 'B.', 'Villareal'));
        $this->actingAs($this->owner);
        $this->post(route('assessments.create.remote.submit'), ['privacy_consent' => '1']);
        $this->get(route('assessments.create.result'));

        Student::factory()->create(['first_name' => 'Ana', 'middle_name' => 'B.', 'last_name' => 'Villareal']);
        ['draft' => $stray] = app(RemoteAssessmentService::class)->create($this->owner, $this->version, collectsIdentity: true);

        $this->post(route('assessments.create.submit'), ['is_confirmed' => '1'])->assertRedirect(route('assessments.create'));

        $this->assertModelMissing($stray);
        $this->assertSame(0, Assessment::query()->count());
        $this->assertSame(1, Student::query()->count());
    }

    public function test_the_new_routes_are_for_the_owning_psychometrician_only(): void
    {
        ['draft' => $draft, 'short_code' => $shortCode] = $this->sendStepOneToStudentDevice();
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $this->sendDetailsOnDevice($device, $this->details('Rhea', 'D.', 'Baculio'));
        $before = $draft->fresh()->identity;

        $this->flushSession();
        $this->actingAs($this->guidanceCounselor());
        $this->post(route('assessments.create.remote.store-student'))->assertForbidden();
        $this->put(route('assessments.create.remote.identity'), $this->details('Other'))->assertForbidden();
        $this->get(route('assessments.create.remote'))->assertForbidden();

        $this->actingAs($this->psychometrician());
        $this->withSession(['assessment_wizard.remote_draft_id' => $draft->id]);
        $this->put(route('assessments.create.remote.identity'), $this->details('Other'))->assertNotFound();
        $this->get(route('assessments.create.remote'))->assertNotFound()->assertDontSee('Baculio');

        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->put(route('assessments.create.remote.identity'), $this->details('Other'))->assertRedirect(route('login'));

        $this->assertSame($before, $draft->fresh()->identity);
        $this->assertSame(1, RemoteAssessmentDraft::query()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function details(string $first = 'Rhea', string $middle = 'D.', string $last = 'Baculio', array $overrides = []): array
    {
        return $this->studentDetails($first, $middle, $last, [
            'course_id' => $this->course->id,
            'year_level_id' => $this->yearLevel->id,
            'section_id' => $this->section->id,
            ...$overrides,
        ]);
    }

    private function existingStudent(string $first, string $middle, string $last): Student
    {
        $student = Student::factory()->create([
            'first_name' => $first, 'middle_name' => $middle, 'last_name' => $last,
            'course_id' => $this->course->id, 'year_level_id' => $this->yearLevel->id, 'section_id' => $this->section->id,
        ]);
        Assessment::factory()->create(['student_id' => $student->id, 'submitted_at' => now()->subWeek()]);

        return $student;
    }
}
