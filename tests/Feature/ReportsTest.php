<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\DassResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    public function test_reports_hub_renders_for_either_role(): void
    {
        $this->actingAs($this->psychometrician())->get(route('reports.index'))->assertOk();
        $this->actingAs($this->guidanceCounselor())->get(route('reports.index'))->assertOk();
    }

    /**
     * Regression test: submitting the on-screen year/month filter used to
     * 500 with a TypeError because `(int) ($validated ?? default)` cast the
     * whole nullable expression instead of the plain string value.
     */
    public function test_monthly_assessment_report_accepts_a_real_year_and_month_filter(): void
    {
        $psychometrician = $this->psychometrician();

        $response = $this->actingAs($psychometrician)->get(route('reports.monthly-assessments', [
            'year' => (string) now()->year,
            'month' => (string) now()->month,
        ]));

        $response->assertOk();
    }

    public function test_monthly_assessment_report_defaults_to_the_current_month_when_no_filter_given(): void
    {
        $psychometrician = $this->psychometrician();

        $this->actingAs($psychometrician)->get(route('reports.monthly-assessments'))->assertOk();
    }

    public function test_assessment_report_pdf_downloads_successfully(): void
    {
        $psychometrician = $this->psychometrician();
        $assessment = Assessment::factory()->create();
        DassResult::factory()->create(['assessment_id' => $assessment->id]);

        $response = $this->actingAs($psychometrician)->get(route('reports.assessment.pdf', $assessment));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_flagged_students_report_is_guidance_counselor_only(): void
    {
        $counselor = $this->guidanceCounselor();
        $psychometrician = $this->psychometrician();

        $this->actingAs($counselor)->get(route('reports.flagged-students.print'))->assertOk();
        $this->actingAs($psychometrician)->get(route('reports.flagged-students.print'))->assertForbidden();
    }

    public function test_counseling_report_is_guidance_counselor_only(): void
    {
        $counselor = $this->guidanceCounselor();
        $psychometrician = $this->psychometrician();

        $this->actingAs($counselor)->get(route('reports.counseling.print'))->assertOk();
        $this->actingAs($psychometrician)->get(route('reports.counseling.print'))->assertForbidden();
    }

    public function test_questionnaire_usage_report_is_psychometrician_only(): void
    {
        $psychometrician = $this->psychometrician();
        $counselor = $this->guidanceCounselor();

        $this->actingAs($psychometrician)->get(route('reports.questionnaire-usage'))->assertOk();
        $this->actingAs($counselor)->get(route('reports.questionnaire-usage'))->assertForbidden();
    }

    public function test_daily_assessment_report_renders(): void
    {
        $psychometrician = $this->psychometrician();

        $this->actingAs($psychometrician)->get(route('reports.daily-assessments'))->assertOk();
    }

    public function test_assessment_summary_report_renders(): void
    {
        $psychometrician = $this->psychometrician();

        $this->actingAs($psychometrician)->get(route('reports.assessment-summary'))->assertOk();
    }
}
