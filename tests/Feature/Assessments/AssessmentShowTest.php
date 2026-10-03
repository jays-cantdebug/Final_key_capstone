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

    public function test_back_to_dashboard_link_appears_when_referred_from_the_guidance_counselor_dashboard(): void
    {
        $counselor = $this->guidanceCounselor();
        $assessment = Assessment::factory()->create();
        DassResult::factory()->create(['assessment_id' => $assessment->id]);

        $response = $this->actingAs($counselor)
            ->withHeader('referer', route('guidance-counselor.dashboard'))
            ->get(route('assessments.show', $assessment));

        $response->assertOk();
        $response->assertSee('Back to Dashboard');
        $response->assertSee(route('guidance-counselor.dashboard'));
        $response->assertDontSee('Back to Counseling Session');
    }

    public function test_back_to_dashboard_link_does_not_appear_from_other_pages_or_external_referers(): void
    {
        $counselor = $this->guidanceCounselor();
        $assessment = Assessment::factory()->create();
        DassResult::factory()->create(['assessment_id' => $assessment->id]);

        foreach ([null, route('flagged-cases.index'), 'https://evil.example.com/guidance-counselor/dashboard'] as $referer) {
            $request = $this->actingAs($counselor);

            if ($referer !== null) {
                $request = $request->withHeader('referer', $referer);
            }

            $request->get(route('assessments.show', $assessment))
                ->assertOk()
                ->assertDontSee('Back to Dashboard');
        }
    }

    public function test_back_to_dashboard_link_appears_on_the_psychometrician_dashboard_and_keeps_its_filters(): void
    {
        $psychometrician = $this->psychometrician();
        $assessment = Assessment::factory()->create();
        DassResult::factory()->create(['assessment_id' => $assessment->id]);
        $filters = ['period' => 'week', 'severity_subscale' => 'stress', 'page' => 2];

        $response = $this->actingAs($psychometrician)
            ->withHeader('referer', route('psychometrician.dashboard', $filters))
            ->get(route('assessments.show', $assessment));

        $response->assertOk();
        $response->assertSee('Back to Dashboard');
        $response->assertViewHas('backToDashboardUrl', route('psychometrician.dashboard', $filters));
    }

    public function test_back_to_dashboard_link_only_points_at_the_viewers_own_dashboard(): void
    {
        $counselor = $this->guidanceCounselor();
        $assessment = Assessment::factory()->create();
        DassResult::factory()->create(['assessment_id' => $assessment->id]);

        $this->actingAs($counselor)
            ->withHeader('referer', route('psychometrician.dashboard'))
            ->get(route('assessments.show', $assessment))
            ->assertOk()
            ->assertDontSee('Back to Dashboard');
    }

    public function test_back_to_counseling_history_link_appears_when_referred_from_the_students_history_page(): void
    {
        $counselor = $this->guidanceCounselor();
        $assessment = Assessment::factory()->create();
        DassResult::factory()->create(['assessment_id' => $assessment->id]);
        CounselingSession::factory()->create([
            'student_id' => $assessment->student_id,
            'assessment_id' => $assessment->id,
            'counselor_id' => $counselor->id,
        ]);
        $historyUrl = route('counseling-sessions.students.show', $assessment->student_id);

        $response = $this->actingAs($counselor)
            ->withHeader('referer', $historyUrl)
            ->get(route('assessments.show', $assessment));

        $response->assertOk();
        $response->assertSee('Back to Counseling History');
        $response->assertViewHas('backToCounselingHistoryUrl', $historyUrl);
        $response->assertDontSee('Back to Counseling Session');
    }

    public function test_back_to_counseling_history_link_does_not_appear_when_the_student_has_no_session_for_this_assessment(): void
    {
        $counselor = $this->guidanceCounselor();
        $assessment = Assessment::factory()->create();
        DassResult::factory()->create(['assessment_id' => $assessment->id]);

        $otherAssessment = Assessment::factory()->create();
        CounselingSession::factory()->create([
            'student_id' => $otherAssessment->student_id,
            'assessment_id' => $otherAssessment->id,
            'counselor_id' => $counselor->id,
        ]);

        $this->actingAs($counselor)
            ->withHeader('referer', route('counseling-sessions.students.show', $otherAssessment->student_id))
            ->get(route('assessments.show', $assessment))
            ->assertOk()
            ->assertDontSee('Back to Counseling History');
    }

    public function test_back_to_counseling_history_link_does_not_appear_for_other_referers_or_roles(): void
    {
        $counselor = $this->guidanceCounselor();
        $assessment = Assessment::factory()->create();
        DassResult::factory()->create(['assessment_id' => $assessment->id]);
        CounselingSession::factory()->create([
            'student_id' => $assessment->student_id,
            'assessment_id' => $assessment->id,
            'counselor_id' => $counselor->id,
        ]);
        $historyPath = parse_url(route('counseling-sessions.students.show', $assessment->student_id), PHP_URL_PATH);

        foreach ([null, route('counseling-sessions.students.index'), 'https://evil.example.com'.$historyPath] as $referer) {
            $request = $this->actingAs($counselor);

            if ($referer !== null) {
                $request = $request->withHeader('referer', $referer);
            }

            $request->get(route('assessments.show', $assessment))
                ->assertOk()
                ->assertDontSee('Back to Counseling History');
        }

        // Counseling History is Guidance Counselor-only, so a Psychometrician never gets a link that would 403.
        $this->actingAs($this->psychometrician())
            ->withHeader('referer', route('counseling-sessions.students.show', $assessment->student_id))
            ->get(route('assessments.show', $assessment))
            ->assertOk()
            ->assertDontSee('Back to Counseling History');
    }
}
