<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\ClassificationThreshold;
use App\Models\Course;
use App\Models\DassResult;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    public function test_psychometrician_dashboard_renders(): void
    {
        $this->actingAs($this->psychometrician())
            ->get(route('psychometrician.dashboard'))
            ->assertOk();
    }

    public function test_guidance_counselor_dashboard_renders(): void
    {
        $this->actingAs($this->guidanceCounselor())
            ->get(route('guidance-counselor.dashboard'))
            ->assertOk();
    }

    public function test_severity_subscale_filter_survives_period_and_course_year_level_form_submissions(): void
    {
        $response = $this->actingAs($this->psychometrician())->get(route('psychometrician.dashboard', [
            'period' => 'week',
            'severity_subscale' => 'stress',
        ]));

        $response->assertOk();

        // One hidden field in the period-select form, one in the
        // Course/Year Level filter form below the stat cards — previously
        // both forms silently dropped this filter on their own submission,
        // wiping it out as soon as the user touched either control after
        // clicking a severity stat card.
        $hiddenFieldCount = substr_count($response->getContent(), 'name="severity_subscale" value="stress"');
        $this->assertSame(2, $hiddenFieldCount);
    }

    public function test_course_filter_scopes_stat_cards_and_severity_chart_not_just_the_assessments_table(): void
    {
        $courseA = Course::factory()->create();
        $courseB = Course::factory()->create();

        $studentA = Student::factory()->create(['course_id' => $courseA->id]);
        $studentB = Student::factory()->create(['course_id' => $courseB->id]);

        $assessmentA = Assessment::factory()->create(['student_id' => $studentA->id]);
        DassResult::factory()->withLevels(
            ClassificationThreshold::SEVERITY_NORMAL,
            ClassificationThreshold::SEVERITY_NORMAL,
            ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE,
        )->create(['assessment_id' => $assessmentA->id]);

        $assessmentB = Assessment::factory()->create(['student_id' => $studentB->id]);
        DassResult::factory()->create(['assessment_id' => $assessmentB->id]);

        $unfiltered = $this->actingAs($this->psychometrician())->get(route('psychometrician.dashboard', [
            'period' => 'all',
        ]));
        $unfiltered->assertOk();
        $unfiltered->assertViewHas('cards', fn (array $cards) => $cards['total']['count'] === 2);
        $unfiltered->assertViewHas('severityChart', fn (array $chart) => array_sum($chart) === 2);

        // Previously course_id/year_level_id only reached the bottom
        // table's query — the stat cards and Severity Distribution chart
        // stayed institution-wide regardless of this filter.
        $filtered = $this->actingAs($this->psychometrician())->get(route('psychometrician.dashboard', [
            'period' => 'all',
            'course_id' => $courseA->id,
        ]));
        $filtered->assertOk();
        $filtered->assertViewHas('cards', fn (array $cards) => $cards['total']['count'] === 1
            && $cards['stress']['count'] === 1
            && $cards['anxiety']['count'] === 0
            && $cards['depression']['count'] === 0);
        $filtered->assertViewHas('severityChart', fn (array $chart) => array_sum($chart) === 1
            && $chart[ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE] === 1);
    }

    public function test_generic_dashboard_route_redirects_to_the_role_specific_dashboard(): void
    {
        $this->actingAs($this->psychometrician())
            ->get(route('dashboard'))
            ->assertRedirect(route('psychometrician.dashboard'));

        $this->actingAs($this->guidanceCounselor())
            ->get(route('dashboard'))
            ->assertRedirect(route('guidance-counselor.dashboard'));
    }
}
