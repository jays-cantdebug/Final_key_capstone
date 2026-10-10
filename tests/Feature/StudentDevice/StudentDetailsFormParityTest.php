<?php

declare(strict_types=1);

namespace Tests\Feature\StudentDevice;

use App\Models\Course;
use App\Models\Section;
use App\Models\User;
use App\Models\YearLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDomainData;
use Tests\Concerns\InteractsWithStudentDevice;
use Tests\TestCase;

/**
 * The student's details are asked for in three places — Step 1, the
 * student device and the live page's correction form — through one shared
 * partial (assessments/create/_student-fields). These fail if any of the
 * three drifts: other labels, another field order, other options, other
 * styling. And the staff attestation never reaches the student device.
 */
class StudentDetailsFormParityTest extends TestCase
{
    use InteractsWithDomainData;
    use InteractsWithStudentDevice;
    use RefreshDatabase;

    private const ATTESTATION = 'The student has acknowledged the data privacy consent notice for this assessment.';

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config(['remote_assessment.student_consent' => true]);
        $this->createActiveQuestionnaireVersion();
        Course::factory()->create(['course_code' => 'BSPS', 'course_name' => 'Plain Studies']);
        Course::factory()->create(['course_code' => 'BSAB', 'course_name' => 'Another Bit']);
        Course::factory()->create(['course_code' => 'OLDC', 'course_name' => 'Inactive', 'status' => Course::STATUS_INACTIVE]);
        YearLevel::factory()->create(['label' => 'First Year']);
        YearLevel::factory()->create(['label' => 'Second Year']);
        Section::factory()->create(['section_name' => 'Rizal']);
        Section::factory()->create(['section_name' => 'Bonifacio']);
        $this->owner = $this->psychometrician();
        $this->actingAs($this->owner);
    }

    public function test_step_one_the_student_device_and_the_correction_form_ask_for_the_same_fields(): void
    {
        $stepOne = $this->formIn((string) $this->get(route('assessments.create'))->assertOk()->getContent(), 'action="'.route('assessments.create.student').'"');

        ['short_code' => $shortCode] = $this->sendStepOneToStudentDevice();
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $studentDevice = (string) $this->openDetailsForm((string) $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk()->getContent());

        $this->sendDetailsOnDevice($device, $this->studentDetails(overrides: [
            'course_id' => Course::query()->where('course_code', 'BSPS')->value('id'),
            'year_level_id' => YearLevel::query()->where('label', 'First Year')->value('id'),
            'section_id' => Section::query()->where('section_name', 'Rizal')->value('id'),
        ]));
        $this->actingAs($this->owner);
        $correction = $this->formIn((string) $this->get(route('assessments.create.remote'))->assertOk()->getContent(), 'data-identity-form');

        $expectedLabels = ['First Name', 'Middle Name', 'Last Name', 'Gender', 'Course', 'Year Level', 'Section'];
        $expectedFields = ['first_name', 'middle_name', 'last_name', 'gender', 'course_id', 'year_level_id', 'section_id'];

        foreach (['Step 1' => $stepOne, 'student device' => $studentDevice, 'correction form' => $correction] as $where => $form) {
            $this->assertSame($expectedLabels, $this->labels($form), "{$where}: labels or their order.");
            $this->assertSame($expectedFields, $this->fieldNames($form), "{$where}: fields or their order.");
        }

        // The same options, in the same order, and the same styling.
        foreach (['gender', 'course_id', 'year_level_id', 'section_id'] as $select) {
            $this->assertSame($this->selectOptions($stepOne, $select), $this->selectOptions($studentDevice, $select), "student device: {$select} options.");
            $this->assertSame($this->selectOptions($stepOne, $select), $this->selectOptions($correction, $select), "correction form: {$select} options.");
        }
        $this->assertSame(
            ['Select a course', 'BSAB - Another Bit', 'BSPS - Plain Studies'],
            array_values($this->selectOptions($stepOne, 'course_id')),
        );
        $this->assertSame($this->controlClasses($stepOne), $this->controlClasses($studentDevice), 'student device: styling.');
        $this->assertSame($this->controlClasses($stepOne), $this->controlClasses($correction), 'correction form: styling.');

        // The only differences: the button, and no attestation on the device.
        $this->assertStringContainsString(self::ATTESTATION, $stepOne);
        $this->assertStringContainsString('name="privacy_consent"', $stepOne);
        $this->assertStringNotContainsString('name="privacy_consent"', $studentDevice);
        $this->assertMatchesRegularExpression('#<button\b[^>]*>\s*'.preg_quote(__('student_device.identity_submit'), '#').'\s*</button>#', $studentDevice);
        $this->assertSame(1, preg_match_all('#<button\b#', $studentDevice));
    }

    public function test_the_staff_attestation_appears_nowhere_on_the_student_device(): void
    {
        ['short_code' => $shortCode] = $this->sendStepOneToStudentDevice();
        $device = $this->claimWithCode($shortCode);
        $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk();
        $this->consentOnDevice($device);
        $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk();
        $this->studentRequest('POST', route('student-device.identity'), ['first_name' => 'x'], $device)->assertStatus(422);
        $this->studentRequest('GET', route('student-device.state'), device: $device, json: true)->assertOk();
        $this->sendDetailsOnDevice($device, $this->studentDetails(overrides: [
            'course_id' => Course::query()->where('status', Course::STATUS_ACTIVE)->value('id'),
            'year_level_id' => YearLevel::query()->value('id'),
            'section_id' => Section::query()->value('id'),
        ]));
        $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk();
        $this->studentRequest('GET', route('student-device.entry'))->assertOk();
        $this->studentRequest('POST', route('student-device.code'), ['code' => 'ZZZZ-ZZZZ'])->assertNotFound();
        $this->studentRequest('GET', '/s/missing')->assertNotFound();

        $this->assertGreaterThanOrEqual(9, count($this->studentResponses));
        foreach ($this->studentResponses as ['label' => $label, 'response' => $response]) {
            $haystack = (string) $response->getContent()."\n".$response->headers;
            foreach ([self::ATTESTATION, 'acknowledged the data privacy consent notice', 'privacy_consent'] as $term) {
                $this->assertStringNotContainsStringIgnoringCase($term, $haystack, "{$label} contains \"{$term}\".");
            }
        }
    }

    /**
     * The one <form> whose opening tag contains `$marker`.
     */
    private function formIn(string $body, string $marker): string
    {
        preg_match_all('#<form\b[^>]*>.*?</form>#s', $body, $forms);
        $matching = array_values(array_filter($forms[0], fn (string $form): bool => str_contains(strtok($form, '>') ?: '', $marker)));
        $this->assertCount(1, $matching, "No single form with {$marker}.");

        return $matching[0];
    }

    /**
     * @return array<int, string>
     */
    private function labels(string $form): array
    {
        preg_match_all('#<label\b[^>]*\bfor="[^"]*"[^>]*>(.*?)</label>#s', $form, $labels);

        return array_map(fn (string $text): string => trim(html_entity_decode(strip_tags($text))), $labels[1]);
    }

    /**
     * @return array<int, string>
     */
    private function fieldNames(string $form): array
    {
        preg_match_all('#<(?:input|select)\b[^>]*\bname="([^"]+)"#', $form, $names);

        return array_values(array_diff($names[1], ['_token', '_method', 'privacy_consent', 'archived_warning_shown', 'confirm_archived_match']));
    }

    /**
     * @return array<string, string>
     */
    private function selectOptions(string $form, string $name): array
    {
        $this->assertSame(1, preg_match('#<select\b[^>]*\bname="'.$name.'"[^>]*>(.*?)</select>#s', $form, $select), "No {$name} select.");
        preg_match_all('#<option value="([^"]*)"[^>]*>(.*?)</option>#s', $select[1], $options, PREG_SET_ORDER);

        return collect($options)->mapWithKeys(fn (array $option): array => [$option[1] => trim(html_entity_decode($option[2], ENT_QUOTES))])->all();
    }

    /**
     * Each field label's and control's class attribute, in order (not
     * Step 1's own attestation checkbox).
     *
     * @return array<int, string>
     */
    private function controlClasses(string $form): array
    {
        preg_match_all('#<(label\b[^>]*\bfor="[^"]*"|input\b(?![^>]*type="(?:hidden|checkbox)")|select\b)[^>]*?\bclass="([^"]*)"#s', $form, $classes);

        return array_values(array_filter($classes[2], fn (string $class): bool => ! str_contains($class, 'h-4 w-4')));
    }
}
