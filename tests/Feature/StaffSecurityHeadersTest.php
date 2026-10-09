<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\StaffSecurityHeaders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

/**
 * Login and staff pages (the `web` group) send X-Frame-Options: DENY,
 * nosniff and Referrer-Policy: same-origin, but no Content-Security-Policy;
 * the student device (`/s`) keeps only its own headers.
 */
class StaffSecurityHeadersTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    public function test_the_login_page_and_a_guest_redirect_have_the_headers(): void
    {
        $this->assertStaffHeaders($this->get('/login')->assertOk());
        $this->assertStaffHeaders($this->get('/flagged-cases')->assertRedirect(route('login')));
    }

    public function test_staff_pages_prints_and_pdfs_have_the_headers_and_still_work(): void
    {
        $this->seedOfficialThresholds();
        $version = $this->createActiveQuestionnaireVersion();
        $psych = $this->psychometrician();
        $this->actingAs($psych);
        $assessment = $this->saveAssessmentThroughWizard($version, 3, 3, 3);

        foreach ([
            route('psychometrician.dashboard'),
            route('students.index'),
            route('assessments.show', $assessment),
            route('reports.assessment.print', $assessment),
            route('reports.assessment-summary.print'),
        ] as $url) {
            $this->assertStaffHeaders($this->get($url)->assertOk(), $url);
        }

        $pdf = $this->get(route('reports.assessment.pdf', $assessment))->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());
        $this->assertStaffHeaders($pdf, 'assessment pdf');

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->guidanceCounselor());
        foreach ([route('guidance-counselor.dashboard'), route('flagged-cases.index'), route('reports.counseling.print')] as $url) {
            $this->assertStaffHeaders($this->get($url)->assertOk(), $url);
        }
    }

    public function test_the_student_device_keeps_only_its_own_headers(): void
    {
        foreach (Route::getRoutes() as $route) {
            if ($route->uri() === 's' || str_starts_with($route->uri(), 's/')) {
                $this->assertNotContains(StaffSecurityHeaders::class, $route->gatherMiddleware(), "/{$route->uri()}");
                $this->assertNotContains('web', $route->gatherMiddleware(), "/{$route->uri()}");
            }
        }

        $response = $this->get('/s')->assertOk();
        $this->assertStringContainsString("default-src 'none'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    private function assertStaffHeaders(TestResponse $response, string $label = ''): void
    {
        foreach (StaffSecurityHeaders::HEADERS as $name => $value) {
            $this->assertSame($value, $response->headers->get($name), trim("{$label} {$name}"));
        }
        $this->assertFalse($response->headers->has('Content-Security-Policy'), trim("{$label}: no CSP on staff pages yet"));
    }
}
