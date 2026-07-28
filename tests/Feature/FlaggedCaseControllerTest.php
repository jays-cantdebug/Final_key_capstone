<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\FlaggedCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

class FlaggedCaseControllerTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    public function test_endorsement_tab_translates_to_the_matching_report_flag_type(): void
    {
        $response = $this->actingAs($this->guidanceCounselor())
            ->get(route('flagged-cases.index', ['tab' => 'endorsement']));

        $response->assertOk();
        $response->assertViewHas('canGenerateReport', true);
        $response->assertViewHas('reportFilters', fn (array $filters) => $filters['flag_type'] === FlaggedCase::FLAG_TYPE_COUNSELING_ENDORSEMENT
            && ! array_key_exists('tab', $filters));
    }

    public function test_notification_tab_translates_to_the_matching_report_flag_type(): void
    {
        $response = $this->actingAs($this->guidanceCounselor())
            ->get(route('flagged-cases.index', ['tab' => 'notification']));

        $response->assertOk();
        $response->assertViewHas('canGenerateReport', true);
        $response->assertViewHas('reportFilters', fn (array $filters) => $filters['flag_type'] === FlaggedCase::FLAG_TYPE_AWARENESS_NOTIFICATION
            && ! array_key_exists('tab', $filters));
    }

    public function test_all_tab_carries_no_flag_type_into_the_report(): void
    {
        $response = $this->actingAs($this->guidanceCounselor())
            ->get(route('flagged-cases.index', ['tab' => 'all']));

        $response->assertOk();
        $response->assertViewHas('canGenerateReport', true);
        $response->assertViewHas('reportFilters', fn (array $filters) => ! array_key_exists('flag_type', $filters)
            && ! array_key_exists('tab', $filters));
    }

    public function test_normal_tab_hides_the_report_buttons_since_the_report_has_no_equivalent(): void
    {
        $response = $this->actingAs($this->guidanceCounselor())
            ->get(route('flagged-cases.index', ['tab' => 'normal']));

        $response->assertOk();
        $response->assertViewHas('canGenerateReport', false);
        $response->assertDontSee('Print Report');
        $response->assertDontSee('Download PDF');
    }

    public function test_other_active_filters_are_preserved_alongside_the_translated_flag_type(): void
    {
        $course = Course::factory()->create();

        $response = $this->actingAs($this->guidanceCounselor())
            ->get(route('flagged-cases.index', ['tab' => 'endorsement', 'course_id' => $course->id]));

        $response->assertOk();
        $response->assertViewHas('reportFilters', fn (array $filters) => $filters['flag_type'] === FlaggedCase::FLAG_TYPE_COUNSELING_ENDORSEMENT
            && (string) $filters['course_id'] === (string) $course->id);
    }

    public function test_endorsement_tabs_report_link_actually_matches_what_the_listing_shows(): void
    {
        $counselor = $this->guidanceCounselor();

        $endorsement = Assessment::factory()->create();
        FlaggedCase::factory()->endorsement()->create(['assessment_id' => $endorsement->id]);

        $notification = Assessment::factory()->create();
        FlaggedCase::factory()->create(['assessment_id' => $notification->id]);

        $listing = $this->actingAs($counselor)->get(route('flagged-cases.index', ['tab' => 'endorsement']));
        $reportFilters = $listing->viewData('reportFilters');

        $report = $this->actingAs($counselor)->get(route('reports.flagged-students.print', $reportFilters));

        $report->assertOk();
        $report->assertSee('Counseling Endorsement');
        $report->assertDontSee('Awareness Notification');
    }
}
