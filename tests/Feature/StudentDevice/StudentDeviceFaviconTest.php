<?php

declare(strict_types=1);

namespace Tests\Feature\StudentDevice;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithDomainData;
use Tests\Concerns\InteractsWithStudentDevice;
use Tests\TestCase;

/**
 * Every student-device page carries the staff layouts' favicon (the same
 * static public PNG), and the strict CSP is unchanged: img-src 'self'
 * already allows a same-origin icon.
 */
class StudentDeviceFaviconTest extends TestCase
{
    use InteractsWithDomainData;
    use InteractsWithStudentDevice;
    use RefreshDatabase;

    private const CSP = "default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'";

    public function test_every_student_device_page_has_the_favicon_and_the_unchanged_csp(): void
    {
        $version = $this->createActiveQuestionnaireVersion();
        $pages = [];

        $pages['code form'] = $this->studentRequest('GET', route('student-device.entry'))->assertOk();
        $pages['unavailable (wrong code)'] = $this->studentRequest('POST', route('student-device.code'), ['code' => 'ZZZZ-ZZZZ'])->assertNotFound();
        $pages['unavailable (unknown path)'] = $this->studentRequest('GET', '/s/nonexistent')->assertNotFound();
        $pages['staff browser'] = $this->studentRequest('GET', route('student-device.entry'), cookies: [Auth::guard('web')->getRecallerName() => '1|token|hash'])->assertForbidden();

        ['token' => $token] = $this->createRemoteDraft();
        $pages['begin (link)'] = $this->studentRequest('GET', route('student-device.begin', $token))->assertOk();

        ['short_code' => $shortCode] = $this->createRemoteDraft();
        $device = $this->claimWithCode($shortCode);
        $pages['consent'] = $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk();
        $this->consentOnDevice($device);
        $pages['questionnaire'] = $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk();
        $this->answerAllOnDevice($device, $version);
        $this->studentRequest('POST', route('student-device.done'), device: $device, json: true)->assertOk();
        $pages['thank-you'] = $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk()->assertSee(__('student_device.thanks_body'));

        ['short_code' => $shortCode] = $this->createRemoteDraft();
        $pages['declined'] = $this->studentRequest('POST', route('student-device.decline'), device: $this->claimWithCode($shortCode))->assertOk();

        Student::factory()->create(['first_name' => 'Rhea', 'middle_name' => 'Dalisay', 'last_name' => 'Baculio']);
        ['short_code' => $shortCode] = $this->createRemoteDraft(collectsIdentity: true);
        $device = $this->claimWithCode($shortCode);
        $this->consentOnDevice($device);
        $pages['details and questionnaire'] = $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk()->assertSee('data-student-device-details="open"', false);
        $this->sendDetailsOnDevice($device, $this->studentDetails('Rhea', 'D.', 'Baculio'));
        $pages['held'] = $this->studentRequest('GET', route('student-device.show'), device: $device)->assertOk()->assertSee('data-student-device="held"', false);

        $favicon = '<link rel="icon" type="image/png" href="'.asset('images/normi-logo-favicon.png').'?v='.filemtime(public_path('images/normi-logo-favicon.png')).'">';

        /** @var TestResponse $response */
        foreach ($pages as $label => $response) {
            $this->assertSame(1, substr_count((string) $response->getContent(), $favicon), "{$label}: favicon link");
            $this->assertSame(self::CSP, $response->headers->get('Content-Security-Policy'), "{$label}: CSP");
        }
    }

    public function test_it_is_the_same_favicon_file_as_the_staff_pages(): void
    {
        $path = public_path('images/normi-logo-favicon.png');

        $this->assertFileExists($path);
        $this->assertSame("\x89PNG", substr((string) file_get_contents($path), 0, 4));

        foreach (['layouts/app.blade.php', 'layouts/guest.blade.php', 'components/login-layout.blade.php', 'layouts/student-device.blade.php'] as $view) {
            $this->assertStringContainsString(
                '<link rel="icon" type="image/png" href="{{ asset(\'images/normi-logo-favicon.png\') }}',
                (string) file_get_contents(resource_path("views/{$view}")),
                $view,
            );
        }
    }
}
