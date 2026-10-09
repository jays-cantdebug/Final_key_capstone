<?php

declare(strict_types=1);

namespace Tests\Feature\StudentDevice;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithDomainData;
use Tests\Concerns\InteractsWithStudentDevice;
use Tests\TestCase;

/**
 * When the student's typed details match an active student, the device
 * shows a neutral "One moment, please" that never says why: no record, existing
 * student, duplicate or match. The generic "not available" page (wrong or
 * expired codes and links) is unchanged.
 */
class StudentDeviceHeldPageTest extends TestCase
{
    use InteractsWithDomainData;
    use InteractsWithStudentDevice;
    use RefreshDatabase;

    private const HELD_TEXT = 'One moment, please Thank you for your details. The psychometrician will assist you now. Please wait.';

    public function test_a_matching_name_gets_the_neutral_please_wait_page(): void
    {
        $this->createActiveQuestionnaireVersion();
        Student::factory()->create(['first_name' => 'Rhea', 'middle_name' => 'Dalisay', 'last_name' => 'Baculio']);

        ['short_code' => $shortCode] = $this->createRemoteDraft(collectsIdentity: true);
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $this->sendDetailsOnDevice($device, $this->studentDetails('Rhea', 'D.', 'Baculio'));

        $held = $this->studentRequest('GET', route('student-device.show'), device: $device)
            ->assertOk()
            ->assertSee('data-student-device="held"', false);
        $text = $this->visibleText((string) $held->getContent());

        $this->assertSame(self::HELD_TEXT, $text);
        foreach (['record', 'existing', 'duplicate', 'match'] as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, (string) $held->getContent(), "The held page must not say \"{$word}\".");
        }
    }

    public function test_the_generic_not_available_page_is_unchanged(): void
    {
        $this->createActiveQuestionnaireVersion();
        $this->createRemoteDraft();

        $generic = $this->studentRequest('POST', route('student-device.code'), ['code' => 'ZZZZ-ZZZZ'])->assertNotFound();

        $this->assertSame(
            'Not available This page is not available. Please ask the psychometrician for help.',
            $this->visibleText((string) $generic->getContent()),
        );
        $this->assertStringNotContainsString(__('student_device.held_heading'), (string) $generic->getContent());
    }

    private function visibleText(string $html): string
    {
        $this->assertSame(1, preg_match('#<main[^>]*>(.*)</main>#s', $html, $main));

        return Str::squish(html_entity_decode(strip_tags($main[1]), ENT_QUOTES));
    }
}
