<?php

declare(strict_types=1);

namespace Tests\Feature\Assessments;

use App\Models\Assessment;
use App\Models\CounselingSession;
use App\Models\DassResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

/**
 * The Assessment Result page's "Back to Counseling Session" link is
 * resolved from the Referer header (see
 * AssessmentController::resolveBackToCounselingSession()) rather than a
 * query parameter, so it only appears when genuinely reached from that
 * specific session's "Related Assessment" link — never from the
 * Dashboard, Reports, Flagged Students, Assessment History, or a
 * student's profile, which all also link to this same page.
 */
class AssessmentShowTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    public function test_back_link_appears_when_referred_from_its_matching_counseling_session(): void
    {
        $counselor = $this->guidanceCounselor();
        $assessment = Assessment::factory()->create();
        DassResult::factory()->create(['assessment_id' => $assessment->id]);
        $session = CounselingSession::factory()->create([
            'assessment_id' => $assessment->id,
            'counselor_id' => $counselor->id,
        ]);

        $response = $this->actingAs($counselor)
            ->withHeader('referer', route('counseling-sessions.show', $session))
            ->get(route('assessments.show', $assessment));

        $response->assertOk();
        $response->assertViewHas('backToCounselingSession', fn ($backTo) => $backTo?->is($session));
        $response->assertSee('Back to Counseling Session');
    }

    public function test_back_link_does_not_appear_with_no_referer(): void
    {
        $psychometrician = $this->psychometrician();
        $assessment = Assessment::factory()->create();
        DassResult::factory()->create(['assessment_id' => $assessment->id]);

        $response = $this->actingAs($psychometrician)->get(route('assessments.show', $assessment));

        $response->assertOk();
        $response->assertViewHas('backToCounselingSession', fn ($backTo) => $backTo === null);
        $response->assertDontSee('Back to Counseling Session');
    }

    public function test_back_link_does_not_appear_when_referred_from_the_dashboard(): void
    {
        $psychometrician = $this->psychometrician();
        $assessment = Assessment::factory()->create();
        DassResult::factory()->create(['assessment_id' => $assessment->id]);

        $response = $this->actingAs($psychometrician)
            ->withHeader('referer', route('psychometrician.dashboard'))
            ->get(route('assessments.show', $assessment));

        $response->assertDontSee('Back to Counseling Session');
    }

    public function test_back_link_does_not_appear_when_the_referring_session_belongs_to_a_different_assessment(): void
    {
        $counselor = $this->guidanceCounselor();
        $assessment = Assessment::factory()->create();
        DassResult::factory()->create(['assessment_id' => $assessment->id]);

        $otherAssessment = Assessment::factory()->create();
        DassResult::factory()->create(['assessment_id' => $otherAssessment->id]);
        $unrelatedSession = CounselingSession::factory()->create([
            'assessment_id' => $otherAssessment->id,
            'counselor_id' => $counselor->id,
        ]);

        $response = $this->actingAs($counselor)
            ->withHeader('referer', route('counseling-sessions.show', $unrelatedSession))
            ->get(route('assessments.show', $assessment));

        $response->assertDontSee('Back to Counseling Session');
    }

    public function test_back_link_does_not_appear_for_an_external_referer(): void
    {
        $psychometrician = $this->psychometrician();
        $assessment = Assessment::factory()->create();
        DassResult::factory()->create(['assessment_id' => $assessment->id]);

        $response = $this->actingAs($psychometrician)
            ->withHeader('referer', 'https://evil.example.com/counseling-sessions/1')
            ->get(route('assessments.show', $assessment));

        $response->assertDontSee('Back to Counseling Session');
    }
}
