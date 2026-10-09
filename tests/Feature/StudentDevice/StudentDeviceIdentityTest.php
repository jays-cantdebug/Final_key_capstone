<?php

declare(strict_types=1);

namespace Tests\Feature\StudentDevice;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\QuestionnaireVersion;
use App\Models\RemoteAssessmentDraft;
use App\Models\Section;
use App\Models\Student;
use App\Models\YearLevel;
use App\Services\RemoteAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithDomainData;
use Tests\Concerns\InteractsWithStudentDevice;
use Tests\TestCase;

/**
 * The student's own Step 1 details on the student device (a draft that
 * `collects_identity`): the privacy notice always first, Step 1's
 * validation, Active lookups only, one submission, encrypted at rest, and
 * — above all — that a name matching an existing student changes nothing
 * the device can see except the generic message.
 */
class StudentDeviceIdentityTest extends TestCase
{
    use InteractsWithDomainData;
    use InteractsWithStudentDevice;
    use RefreshDatabase;

    private QuestionnaireVersion $version;

    private Course $course;

    private YearLevel $yearLevel;

    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        config(['remote_assessment.student_consent' => true]);
        $this->version = $this->createActiveQuestionnaireVersion();
        $this->course = Course::factory()->create(['course_code' => 'BSPS', 'course_name' => 'Bachelor of Plain Studies']);
        $this->yearLevel = YearLevel::factory()->create(['label' => 'First Year']);
        $this->section = Section::factory()->create(['section_name' => 'Rizal']);
    }

    public function test_the_privacy_notice_always_comes_first_even_with_the_consent_flag_off(): void
    {
        config(['remote_assessment.student_consent' => false]);
        ['draft' => $draft, 'short_code' => $shortCode] = $this->createRemoteDraft(collectsIdentity: true);
        $this->assertTrue($draft->requires_consent);
        $device = $this->claimWithCode($shortCode);

        $this->studentRequest('GET', route('student-device.show'), device: $device)
            ->assertOk()
            ->assertSee(__('student_device.consent_heading'))
            ->assertDontSee('data-student-device-details', false)
            ->assertDontSee('data-question=', false);

        // Details sent before the notice is acknowledged are not taken.
        $this->sendDetailsOnDevice($device, $this->details());
        $this->assertNull($draft->fresh()->identity);
        $this->assertNull($draft->fresh()->identity_submitted_at);

        $this->studentRequest('POST', route('student-device.answer'), ['question_id' => $this->version->questions->first()->id, 'value' => 1], $device, json: true)
            ->assertStatus(409)->assertExactJson(['state' => 'consent']);
    }

    public function test_after_the_notice_one_page_has_the_details_form_above_locked_questions(): void
    {
        $device = $this->deviceAtDetailsForm();

        $page = $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk();
        $body = (string) $page->getContent();
        $form = $this->openDetailsForm($body);
        $this->assertNotNull($form, 'No open details form.');
        $this->assertStringContainsString('action="'.route('student-device.identity').'"', $form);
        foreach (['first_name', 'middle_name', 'last_name', 'gender', 'course_id', 'year_level_id', 'section_id'] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $form);
        }
        $page->assertSee(__('student_device.identity_submit'));

        // The form comes first, then the questions — all of them, locked.
        $this->assertLessThan(strpos($body, 'data-question='), strpos($body, 'data-student-device-details'));
        foreach ($this->version->questions as $question) {
            $page->assertSee($question->item_number.'. '.$question->question_text);
        }
        $this->assertSame(1, preg_match('#<fieldset\b([^>]*)\bdata-questions\b#', $body, $outer));
        $this->assertMatchesRegularExpression('#\bdisabled\b#', $outer[1]);
        $this->assertStringContainsString('aria-describedby="questions-locked"', $outer[1]);
        $page->assertSee('id="questions-locked"', false)->assertSee(__('student_device.questions_locked'));
        $this->assertStringContainsString('data-locked="1"', $body);
        $this->assertSame(84, preg_match_all('#<input\s+type="radio"#', $body));
        $this->assertSame(0, preg_match('#<input\b[^>]*\schecked\b#', $body), 'An answer is checked.');
        $this->assertMatchesRegularExpression('#<button\b[^>]*\bdata-done\b[^>]*\bdisabled\b#s', $body);
        $page->assertSee(__('student_device.counter', ['answered' => 0, 'total' => 21]))
            ->assertSee(__('student_device.identity_not_saved'))
            ->assertDontSee('data-details-saved', false);
    }

    public function test_the_server_refuses_answers_and_done_until_the_details_are_saved(): void
    {
        $device = $this->deviceAtDetailsForm();
        $draft = RemoteAssessmentDraft::query()->sole();
        $question = $this->version->questions->first();

        // A valid answer posted straight to the endpoint, as a script that
        // ignores the disabled page would.
        foreach ([0, 3] as $value) {
            $this->studentRequest('POST', route('student-device.answer'), ['question_id' => $question->id, 'value' => $value, 'version' => $this->version->id], $device, json: true)
                ->assertStatus(409)->assertExactJson(['state' => 'identity']);
        }
        $this->studentRequest('POST', route('student-device.done'), ['version' => $this->version->id], $device, json: true)
            ->assertStatus(409)->assertExactJson(['state' => 'identity']);
        $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)->assertOk()->assertExactJson(['state' => 'identity']);
        $this->assertNull($draft->fresh()->responses);
        $this->assertSame(RemoteAssessmentDraft::STATUS_ANSWERING, $draft->fresh()->status);

        $this->sendDetailsOnDevice($device, $this->details());
        $this->studentRequest('POST', route('student-device.answer'), ['question_id' => $question->id, 'value' => 3, 'version' => $this->version->id], $device, json: true)
            ->assertOk()->assertExactJson(['state' => 'answering', 'answered' => 1]);
    }

    public function test_after_saving_the_page_shows_only_details_saved_and_unlocks_the_questions(): void
    {
        $device = $this->deviceAtDetailsForm();
        $this->sendDetailsOnDevice($device, $this->details('Zacarias', 'Q.', 'Pangilinan'));

        $page = $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk();
        $body = (string) $page->getContent();

        $this->assertNull($this->openDetailsForm($body));
        $page->assertSee('data-details-saved', false)
            ->assertSee(__('student_device.identity_saved'))
            ->assertSee('id="questions"', false);
        $this->assertSame(0, preg_match('#<(form|select|textarea)\b#', $body), 'The saved page has a form control for the details.');
        $this->assertSame(0, preg_match('#<input\b(?![^>]*type="radio")#', $body), 'The saved page has a non-answer input.');
        foreach (['Zacarias', 'Pangilinan', 'Female', 'BSPS', 'Plain Studies', 'First Year', 'Rizal', 'first_name', 'course_id'] as $value) {
            $this->assertStringNotContainsString($value, $body, "The saved page shows \"{$value}\".");
        }
        // The middle initial, in the page's visible text ('Q.' on its own
        // can also turn up inside built asset names).
        $this->assertStringNotContainsString('Q.', strip_tags($body), 'The saved page shows the middle initial.');
        $this->assertStringContainsString('data-locked="0"', $body);
        $this->assertSame(1, preg_match('#<fieldset\b([^>]*)\bdata-questions\b#', $body, $outer));
        $this->assertDoesNotMatchRegularExpression('#\bdisabled\b#', $outer[1]);
        $page->assertDontSee('id="questions-locked"', false);
    }

    public function test_the_details_can_never_be_edited_or_reopened_after_saving(): void
    {
        $device = $this->deviceAtDetailsForm();
        $this->sendDetailsOnDevice($device, $this->details('Zacarias', 'Q.', 'Pangilinan'));
        $draft = RemoteAssessmentDraft::query()->sole();
        $service = app(RemoteAssessmentService::class);

        $assertClosed = function (string $device, string $when): void {
            $body = (string) $this->studentRequest('GET', route('student-device.show'), device: $device)->getContent();
            $this->assertNull($this->openDetailsForm($body), "The details form reopened {$when}.");
            $this->assertStringNotContainsString('Pangilinan', $body, "The details were shown {$when}.");
        };

        // A reload, and a second submission (which changes nothing).
        $assertClosed($device, 'on a reload');
        $this->sendDetailsOnDevice($device, $this->details('Someone', 'E.', 'Else'));
        $this->assertSame('Pangilinan', $draft->fresh()->identity['last_name']);
        $assertClosed($device, 'after a second submission');

        // Return to student, after Done.
        $this->answerAllOnDevice($device, $this->version);
        $this->studentRequest('POST', route('student-device.done'), [], $device, json: true)->assertOk();
        $this->assertTrue($service->returnToStudent($draft->fresh()));
        $assertClosed($device, 'after Return to student');

        // New code, on another device.
        ['short_code' => $newCode] = $service->reissue($draft->fresh());
        $assertClosed($this->claimWithCode($newCode), 'after New code');

        // A held draft released by a correction.
        $this->existingStudent('Cara', 'D.', 'Evangelista');
        $held = $this->deviceAtDetailsForm();
        $this->sendDetailsOnDevice($held, $this->details('Cara', 'D.', 'Evangelista'));
        $heldDraft = RemoteAssessmentDraft::query()->latest('id')->firstOrFail();
        $service->correctIdentity($heldDraft, [...$heldDraft->identity, 'last_name' => 'Pangilinan'], matchesActiveStudent: false);
        $assertClosed($held, 'after a released hold');
    }

    public function test_the_form_lists_exactly_the_active_lookups_and_turns_autocomplete_off(): void
    {
        Course::factory()->create(['course_code' => 'OLDC', 'course_name' => 'Inactive Course', 'status' => Course::STATUS_INACTIVE]);
        Course::factory()->create(['course_code' => 'ARCC', 'course_name' => 'Archived Course'])->delete();
        YearLevel::factory()->create(['label' => 'Inactive Year', 'status' => YearLevel::STATUS_INACTIVE]);
        YearLevel::factory()->create(['label' => 'Archived Year'])->delete();
        Section::factory()->create(['section_name' => 'Inactive Section', 'status' => Section::STATUS_INACTIVE]);
        Section::factory()->create(['section_name' => 'Archived Section'])->delete();
        $secondCourse = Course::factory()->create(['course_code' => 'BSAB', 'course_name' => 'Bachelor of Another Bit']);

        $device = $this->deviceAtDetailsForm();
        // The details form only: the questions below it have radios of their own.
        $body = (string) $this->openDetailsForm((string) $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk()->getContent());

        $this->assertSame(
            ['' => 'Select a course', $secondCourse->id => 'BSAB - Bachelor of Another Bit', $this->course->id => 'BSPS - Bachelor of Plain Studies'],
            $this->selectOptions($body, 'course_id'),
        );
        $this->assertSame(['' => 'Select a year level', $this->yearLevel->id => 'First Year'], $this->selectOptions($body, 'year_level_id'));
        $this->assertSame(['' => 'Select a section', $this->section->id => 'Rizal'], $this->selectOptions($body, 'section_id'));
        $this->assertSame(['' => 'Select gender', 'Male' => 'Male', 'Female' => 'Female', 'Prefer not to say' => 'Prefer not to say'], $this->selectOptions($body, 'gender'));

        $this->assertMatchesRegularExpression('#<form[^>]*autocomplete="off"#', $body);
        preg_match_all('#<(input|select)\b[^>]*>#', $body, $controls);
        $this->assertCount(7, $controls[0]);
        foreach ($controls[0] as $control) {
            $this->assertStringContainsString('autocomplete="off"', $control);
        }
        // Nothing pre-filled (Step 1's inputs have no value attribute until
        // there is old input), nothing selected.
        $this->assertSame(0, preg_match_all('#<input\b[^>]*\bvalue="[^"]+"#', $body));
        $this->assertStringNotContainsString('selected', $body);
    }

    public function test_the_details_get_step_ones_validation_messages(): void
    {
        $device = $this->deviceAtDetailsForm();

        $response = $this->studentRequest('POST', route('student-device.identity'), [], $device)->assertStatus(422);
        foreach ([
            'Please fill out the First Name field.', 'Please fill out the Middle Name field.', 'Please fill out the Last Name field.',
            'Please select a Gender.', 'Please select a Course.', 'Please select a Year Level.', 'Please select a Section.',
        ] as $message) {
            $response->assertSee($message);
        }

        foreach (['Pe.', 'P', 'PP.', '1.'] as $bad) {
            $this->studentRequest('POST', route('student-device.identity'), $this->details(middle: $bad), $device)
                ->assertStatus(422)
                ->assertSee('Middle Name must be a single letter followed by a period, e.g., &#039;P.&#039;', false);
        }

        $this->studentRequest('POST', route('student-device.identity'), $this->details(overrides: ['gender' => 'Other', 'first_name' => str_repeat('a', 101)]), $device)
            ->assertStatus(422)
            ->assertSee('Please select a Gender.')
            ->assertSee('Please check the First Name field.')
            ->assertDontSee('validation.', false);

        $this->assertNull(RemoteAssessmentDraft::query()->sole()->identity);
    }

    public function test_the_details_are_normalized_like_step_one(): void
    {
        foreach ([
            ['  Ma.  Luisa ', 'p.', ' dela   Cruz ', 'Ma. Luisa', 'P.', 'dela Cruz'],
            ['Juan', 'ñ.', 'Peña', 'Juan', 'Ñ.', 'Peña'],
            // A decomposed Ñ (N + combining tilde).
            ['Jose', "N\u{0303}.", 'Reyes', 'Jose', 'Ñ.', 'Reyes'],
        ] as [$first, $middle, $last, $expectedFirst, $expectedMiddle, $expectedLast]) {
            $device = $this->deviceAtDetailsForm();
            $this->sendDetailsOnDevice($device, $this->details($first, $middle, $last));

            $identity = RemoteAssessmentDraft::query()->latest('id')->firstOrFail()->identity;
            $this->assertSame($expectedFirst, $identity['first_name']);
            $this->assertSame($expectedMiddle, $identity['middle_name']);
            $this->assertSame($expectedLast, $identity['last_name']);
            $this->assertSame($this->course->id, $identity['course_id']);
            $this->assertSame(['course_id', 'first_name', 'gender', 'last_name', 'middle_name', 'section_id', 'year_level_id'], collect($identity)->keys()->sort()->values()->all());
        }
    }

    public function test_an_inactive_an_archived_and_a_missing_lookup_id_give_the_same_422(): void
    {
        $device = $this->deviceAtDetailsForm();
        $lookups = [
            'course_id' => [
                Course::factory()->create(['status' => Course::STATUS_INACTIVE])->id,
                tap(Course::factory()->create())->delete()->id,
            ],
            'year_level_id' => [
                YearLevel::factory()->create(['status' => YearLevel::STATUS_INACTIVE])->id,
                tap(YearLevel::factory()->create())->delete()->id,
            ],
            'section_id' => [
                Section::factory()->create(['status' => Section::STATUS_INACTIVE])->id,
                tap(Section::factory()->create())->delete()->id,
            ],
        ];

        foreach ($lookups as $field => [$inactiveId, $archivedId]) {
            $replies = [];

            foreach ([$inactiveId, $archivedId, 999999] as $id) {
                $replies[] = $this->studentRequest('POST', route('student-device.identity'), $this->details(overrides: [$field => $id]), $device)
                    ->assertStatus(422);
            }

            $this->assertSame($this->comparable($replies[0], sameDevice: true), $this->comparable($replies[1], sameDevice: true), "{$field}: inactive vs archived");
            $this->assertSame($this->comparable($replies[0], sameDevice: true), $this->comparable($replies[2], sameDevice: true), "{$field}: inactive vs missing");
        }

        $this->assertNull(RemoteAssessmentDraft::query()->sole()->identity);
    }

    public function test_a_failed_form_shows_back_only_what_this_device_typed(): void
    {
        $this->existingStudent('Gregorio', 'H.', 'Villafuerte');
        $device = $this->deviceAtDetailsForm();

        $body = (string) $this->studentRequest('POST', route('student-device.identity'), $this->details('Typedfirst', 'bad', 'Villafuerte'), $device)
            ->assertStatus(422)->getContent();

        $this->assertStringContainsString('value="Typedfirst"', $body);
        $this->assertStringContainsString('value="Villafuerte"', $body);
        $this->assertStringContainsString('value="BAD"', $body);
        // The existing student's middle initial, as a field value ('H.' on
        // its own also turns up inside built asset names).
        foreach (['Gregorio', 'value="H."', 'OLDX', 'Old Programme'] as $existing) {
            $this->assertStringNotContainsString($existing, $body);
        }
    }

    public function test_a_failed_save_focuses_the_first_invalid_field_and_keeps_the_questions_locked(): void
    {
        $device = $this->deviceAtDetailsForm();

        // Middle name and course invalid; first and last name fine.
        $response = $this->studentRequest('POST', route('student-device.identity'), $this->details('Typedfirst', 'bad', 'Typedlast', ['course_id' => 999999]), $device)
            ->assertStatus(422);
        $body = (string) $response->getContent();
        $form = (string) $this->openDetailsForm($body);

        // Step 1's form has no error summary: each message sits under its
        // own field, which is marked invalid (data-field-invalid) and points
        // at the message (aria-describedby).
        $this->assertSame(1, preg_match_all('#\sautofocus=#', $body), 'Exactly one field gets autofocus.');
        $this->assertMatchesRegularExpression('#<input\b[^>]*\bid="middle_name"[^>]*\sautofocus=#s', $form);
        foreach (['middle_name', 'course_id'] as $invalid) {
            $this->assertMatchesRegularExpression('#<(input|select)\b[^>]*\bdata-field-invalid\b[^>]*\bid="'.$invalid.'"[^>]*\baria-describedby="'.$invalid.'-error"#s', $form, $invalid);
            $this->assertMatchesRegularExpression('#<div\s+id="'.$invalid.'-error"#', $form, "{$invalid}: no message element with that id.");
        }
        $response->assertSee('Middle Name must be a single letter followed by a period, e.g., &#039;P.&#039;', false)
            ->assertSee('Please select a Course.');
        foreach (['first_name', 'last_name'] as $valid) {
            $this->assertDoesNotMatchRegularExpression('#\bid="'.$valid.'"[^>]*aria-describedby#', $form, $valid);
            $this->assertStringNotContainsString('id="'.$valid.'-error"', $form);
        }
        // The student's own input stays; the questions stay locked.
        $this->assertStringContainsString('value="Typedfirst"', $form);
        $this->assertStringContainsString('value="Typedlast"', $form);
        $this->assertStringContainsString('<option value="'.$this->yearLevel->id.'" selected', $form);
        $this->assertStringContainsString('data-locked="1"', $body);
        $this->assertMatchesRegularExpression('#<fieldset\b[^>]*\bdisabled\b[^>]*\bdata-questions\b#', $body);
        $this->assertMatchesRegularExpression('#<button\b[^>]*\bdata-done\b[^>]*\bdisabled\b#s', $body);
    }

    public function test_a_matching_name_changes_nothing_on_the_device_but_the_generic_message(): void
    {
        $active = $this->existingStudent('Cara', 'D.', 'Evangelista');
        $archived = $this->existingStudent('Bento', 'C.', 'Dimaculangan');
        $archived->delete();

        $cases = [
            'none' => ['Aurora', 'B.', 'Salvador'],
            'archived' => ['Bento', 'C.', 'Dimaculangan'],
            'active' => ['Cara', 'D.', 'Evangelista'],
        ];
        $posts = [];
        $queries = [];
        $devices = [];

        foreach ($cases as $case => [$first, $middle, $last]) {
            $devices[$case] = $this->deviceAtDetailsForm();

            DB::flushQueryLog();
            DB::enableQueryLog();
            $posts[$case] = $this->sendDetailsOnDevice($devices[$case], $this->details($first, $middle, $last));
            $queries[$case] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }

        // The reply to the details is the same whatever the name matched.
        $this->assertSame($this->comparable($posts['none']), $this->comparable($posts['archived']));
        $this->assertSame($this->comparable($posts['none']), $this->comparable($posts['active']));
        $this->assertSame('', (string) $posts['active']->getContent());
        $this->assertSame([], $posts['active']->headers->getCookies());
        $this->assertSame($queries['none'], $queries['archived']);
        $this->assertSame($queries['none'], $queries['active']);

        // No match and an archived match: straight on to the questionnaire.
        foreach (['none', 'archived'] as $case) {
            $this->studentRequest('GET', route('student-device.show'), device: $devices[$case])->assertOk()
                ->assertSee('data-student-device="questionnaire"', false)
                ->assertSee('data-locked="0"', false)
                ->assertSee('data-details-saved', false);
            $this->studentRequest('GET', route('student-device.state'), device: $devices[$case], json: true)->assertOk()->assertExactJson(['state' => 'answering']);
        }

        // An active match: only the generic message, word for word.
        $held = $this->studentRequest('GET', route('student-device.show'), device: $devices['active'])->assertOk();
        $generic = $this->studentRequest('POST', route('student-device.code'), ['code' => 'ZZZZ-ZZZZ'])->assertNotFound();
        $this->assertSame('Not available This page is not available. Please ask the psychometrician for help.', $this->visibleText($generic));
        $this->assertSame('Please wait Thank you. Please let the psychometrician know, and wait for them to continue with you.', $this->visibleText($held));

        $body = (string) $held->getContent();
        foreach ([
            $active->student_number, $archived->student_number, 'Cara', 'Evangelista', 'Bento', 'Dimaculangan', 'Aurora',
            'OLDX', 'Old Programme', 'Old Year', 'Oldsection', 'assessment', 'Take Again', 'already', 'exists', 'registered', 'match',
        ] as $term) {
            $this->assertStringNotContainsStringIgnoringCase($term, $body, "The held page contains \"{$term}\".");
        }
        $this->assertSame(0, preg_match_all('#<(form|input|select|button|a|fieldset|legend|template|noscript)\b#', $body), 'The held page has a control, a link or part of the questionnaire.');
        foreach (['data-question', 'data-questions', 'data-details', 'data-student-device-details', 'id="questions"', 'question 1', 'Response Scale', __('student_device.identity_saved'), __('student_device.questions_locked'), __('student_device.identity_heading')] as $term) {
            $this->assertStringNotContainsString($term, $body, "The held page contains \"{$term}\".");
        }
        // The whole page is the generic one: the same elements, plus only
        // the wrapper that polls for a release.
        $expectedTags = [...$this->mainTags($generic), 'div'];
        sort($expectedTags);
        $this->assertSame($expectedTags, $this->mainTags($held));

        $this->studentRequest('GET', route('student-device.state'), device: $devices['active'], json: true)->assertOk()->assertExactJson(['state' => 'help']);
        $question = $this->version->questions->first();
        $this->studentRequest('POST', route('student-device.answer'), ['question_id' => $question->id, 'value' => 1], $devices['active'], json: true)
            ->assertStatus(409)->assertExactJson(['state' => 'help']);
        $this->studentRequest('POST', route('student-device.done'), [], $devices['active'], json: true)
            ->assertStatus(409)->assertExactJson(['state' => 'help']);
        // A second try with another name changes nothing, and replies the same.
        $retry = $this->sendDetailsOnDevice($devices['active'], $this->details('Aurora', 'B.', 'Salvador'));
        $this->assertSame($this->comparable($posts['none'], sameDevice: true), $this->comparable($retry, sameDevice: true));

        $drafts = RemoteAssessmentDraft::query()->orderBy('id')->get()->values();
        $this->assertNull($drafts[0]->held_at);
        $this->assertNull($drafts[1]->held_at);
        $this->assertNotNull($drafts[2]->held_at);
        $this->assertSame('Cara', $drafts[2]->identity['first_name']);
        $this->assertNull($drafts[2]->responses);
    }

    public function test_a_held_device_continues_once_the_psychometrician_clears_the_match(): void
    {
        $this->existingStudent('Cara', 'D.', 'Evangelista');
        $device = $this->deviceAtDetailsForm();
        $this->sendDetailsOnDevice($device, $this->details('Cara', 'D.', 'Evangelista'));
        $draft = RemoteAssessmentDraft::query()->sole();

        $released = app(RemoteAssessmentService::class)->correctIdentity($draft, [...$draft->identity, 'last_name' => 'Evangelio'], matchesActiveStudent: false);

        $this->assertTrue($released);
        $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)->assertOk()->assertExactJson(['state' => 'answering']);
        $page = $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk();
        $page->assertSee('data-student-device="questionnaire"', false)->assertDontSee('Evangeli');
    }

    public function test_the_details_are_accepted_once_per_draft(): void
    {
        $device = $this->deviceAtDetailsForm();
        $this->sendDetailsOnDevice($device, $this->details('Aurora', 'B.', 'Salvador'));
        $draft = RemoteAssessmentDraft::query()->sole();
        $first = [$draft->identity, $draft->identity_submitted_at->toIso8601String(), $draft->revision];

        $this->travel(1)->minutes();
        $this->sendDetailsOnDevice($device, $this->details('Someone', 'E.', 'Else'));

        $draft->refresh();
        $this->assertSame($first, [$draft->identity, $draft->identity_submitted_at->toIso8601String(), $draft->revision]);
    }

    public function test_the_details_are_encrypted_at_rest_and_hidden_from_serialization(): void
    {
        $device = $this->deviceAtDetailsForm();
        $this->sendDetailsOnDevice($device, $this->details('Zacarias', 'Q.', 'Pangilinan'));

        $raw = (string) DB::table('remote_assessment_drafts')->value('identity');
        foreach (['Zacarias', 'Pangilinan', 'Q.', 'Female', 'first_name'] as $plain) {
            $this->assertStringNotContainsString($plain, $raw);
        }
        $decrypted = json_decode(Crypt::decryptString($raw), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Pangilinan', $decrypted['last_name']);

        $draft = RemoteAssessmentDraft::query()->sole();
        $this->assertArrayNotHasKey('identity', $draft->toArray());
        $this->assertStringNotContainsString('Pangilinan', $draft->toJson());
    }

    public function test_a_draft_that_does_not_collect_details_never_shows_the_form(): void
    {
        ['draft' => $draft, 'short_code' => $shortCode] = $this->createRemoteDraft();
        $this->assertFalse($draft->collects_identity);
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);

        $this->studentRequest('GET', route('student-device.show'), device: $device)
            ->assertOk()->assertSee('data-student-device="questionnaire"', false)->assertDontSee('name="first_name"', false);
        $this->sendDetailsOnDevice($device, $this->details());
        $this->assertNull($draft->fresh()->identity);
        $this->assertNull($draft->fresh()->identity_submitted_at);

        $this->studentRequest('POST', route('student-device.answer'), ['question_id' => $this->version->questions->first()->id, 'value' => 2], $device, json: true)->assertOk();
    }

    public function test_after_new_code_the_details_are_never_shown_again(): void
    {
        $device = $this->deviceAtDetailsForm();
        $this->sendDetailsOnDevice($device, $this->details('Zacarias', 'Q.', 'Pangilinan'));
        $draft = RemoteAssessmentDraft::query()->sole();

        ['short_code' => $newCode] = app(RemoteAssessmentService::class)->reissue($draft);
        $newDevice = $this->claimWithCode($newCode);

        $page = $this->studentRequest('GET', route('student-device.show'), device: $newDevice)->assertOk();
        $page->assertSee('data-student-device="questionnaire"', false)->assertDontSee('Zacarias')->assertDontSee('Pangilinan');
        $this->assertSame('Pangilinan', $draft->fresh()->identity['last_name']);

        // A held draft stays held for the next device.
        $this->existingStudent('Cara', 'D.', 'Evangelista');
        $heldDevice = $this->deviceAtDetailsForm();
        $this->sendDetailsOnDevice($heldDevice, $this->details('Cara', 'D.', 'Evangelista'));
        ['short_code' => $heldCode] = app(RemoteAssessmentService::class)->reissue(RemoteAssessmentDraft::query()->latest('id')->firstOrFail());
        $this->studentRequest('GET', route('student-device.state'), device: $this->claimWithCode($heldCode), json: true)->assertExactJson(['state' => 'help']);
    }

    public function test_declining_strips_the_draft_of_any_details(): void
    {
        ['draft' => $draft, 'short_code' => $shortCode] = $this->createRemoteDraft(collectsIdentity: true);
        $device = $this->claimWithCode($shortCode);
        // Defensive: none can be there before consent, but planted anyway.
        $draft->forceFill(['identity' => ['first_name' => 'Planted'], 'identity_submitted_at' => now(), 'held_at' => now()])->save();

        $this->studentRequest('POST', route('student-device.decline'), device: $device)->assertOk();

        $draft->refresh();
        $this->assertSame(RemoteAssessmentDraft::STATUS_DECLINED, $draft->status);
        $this->assertNull($draft->identity);
        $this->assertNull($draft->identity_submitted_at);
        $this->assertNull($draft->held_at);
        $this->assertNull(DB::table('remote_assessment_drafts')->value('identity'));
    }

    /**
     * Claim a draft that collects the details and acknowledge the notice;
     * the device is then on the details form.
     */
    private function deviceAtDetailsForm(): string
    {
        ['short_code' => $shortCode] = $this->createRemoteDraft($this->psychometrician(), $this->version, collectsIdentity: true);
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);

        return $device;
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

    /**
     * An existing student with an assessment, on lookups that are not
     * Active (so the form never lists them).
     */
    private function existingStudent(string $first, string $middle, string $last): Student
    {
        $student = Student::factory()->create([
            'first_name' => $first, 'middle_name' => $middle, 'last_name' => $last,
            'course_id' => Course::query()->firstOrCreate(['course_code' => 'OLDX'], ['course_name' => 'Old Programme', 'status' => Course::STATUS_INACTIVE])->id,
            'year_level_id' => YearLevel::query()->where('label', 'Old Year')->first()?->id
                ?? YearLevel::factory()->create(['label' => 'Old Year', 'status' => YearLevel::STATUS_INACTIVE])->id,
            'section_id' => Section::query()->where('section_name', 'Oldsection')->first()?->id
                ?? Section::factory()->create(['section_name' => 'Oldsection', 'status' => Section::STATUS_INACTIVE])->id,
        ]);
        Assessment::factory()->create(['student_id' => $student->id]);

        return $student;
    }

    /**
     * A select's options as value => label.
     *
     * @return array<string, string>
     */
    private function selectOptions(string $body, string $name): array
    {
        $this->assertSame(1, preg_match('#<select[^>]*name="'.$name.'"[^>]*>(.*?)</select>#s', $body, $select), "No {$name} select.");
        preg_match_all('#<option value="([^"]*)"[^>]*>(.*?)</option>#s', $select[1], $options, PREG_SET_ORDER);

        return collect($options)->mapWithKeys(fn (array $option): array => [$option[1] => html_entity_decode(trim($option[2]), ENT_QUOTES)])->all();
    }

    /**
     * Status, headers apart from Date, and body. `$sameDevice`: replies to
     * one device compared with each other also differ in
     * X-RateLimit-Remaining, which only counts that device's own requests.
     *
     * @return array<string, mixed>
     */
    private function comparable(TestResponse $response, bool $sameDevice = false): array
    {
        $headers = $response->headers->all();
        unset($headers['date']);
        if ($sameDevice) {
            unset($headers['x-ratelimit-remaining']);
        }
        ksort($headers);

        return ['status' => $response->getStatusCode(), 'headers' => $headers, 'body' => (string) $response->getContent()];
    }

    /**
     * The element names inside <main>, sorted.
     *
     * @return array<int, string>
     */
    private function mainTags(TestResponse $response): array
    {
        $this->assertSame(1, preg_match('#<main[^>]*>(.*)</main>#s', (string) $response->getContent(), $main));
        preg_match_all('#<([a-z][a-z0-9]*)\b#', $main[1], $tags);
        $names = $tags[1];
        sort($names);

        return $names;
    }

    private function visibleText(TestResponse $response): string
    {
        $this->assertSame(1, preg_match('#<main[^>]*>(.*)</main>#s', (string) $response->getContent(), $main));

        return Str::squish(html_entity_decode(strip_tags($main[1]), ENT_QUOTES));
    }
}
