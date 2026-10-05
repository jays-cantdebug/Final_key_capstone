<?php

declare(strict_types=1);

namespace Tests\Feature\Assessments;

use App\Models\Assessment;
use App\Models\CounselingSession;
use App\Models\DassResult;
use App\Models\Student;
use App\Models\SystemNotification;
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

    public function test_back_to_dashboard_link_keeps_every_real_dashboard_filter_and_drops_unknown_keys(): void
    {
        $assessment = $this->assessmentWithResult();

        // Psychometrician Dashboard: all four DashboardFilterRequest
        // filters plus the All Assessments table's page survive the round
        // trip; anything else is dropped.
        $filters = [
            'period' => 'today',
            'course_id' => '4',
            'year_level_id' => '2',
            'severity_subscale' => 'depression',
            'page' => '3',
        ];

        $this->actingAs($this->psychometrician())
            ->withHeader('referer', route('psychometrician.dashboard', [...$filters, 'evil' => '"><script>', 'tab' => 'normal']))
            ->get(route('assessments.show', $assessment))
            ->assertOk()
            ->assertViewHas('backToDashboardUrl', route('psychometrician.dashboard', [...$filters, 'page' => 3]))
            ->assertDontSee('evil=', false);

        // Guidance Counselor Dashboard takes no filters and isn't
        // paginated, so nothing at all is carried back.
        $this->actingAs($this->guidanceCounselor())
            ->withHeader('referer', route('guidance-counselor.dashboard', ['evil' => '"><script>', 'period' => 'week', 'page' => '2']))
            ->get(route('assessments.show', $assessment))
            ->assertOk()
            ->assertViewHas('backToDashboardUrl', route('guidance-counselor.dashboard'));
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

    private function assessmentWithResult(): Assessment
    {
        $assessment = Assessment::factory()->create();
        DassResult::factory()->create(['assessment_id' => $assessment->id]);

        return $assessment;
    }

    public function test_back_to_student_profile_link_appears_when_referred_from_the_owning_students_profile(): void
    {
        $assessment = $this->assessmentWithResult();
        $profileUrl = route('students.show', $assessment->student);

        $response = $this->actingAs($this->psychometrician())
            ->withHeader('referer', $profileUrl)
            ->get(route('assessments.show', $assessment));

        $response->assertOk();
        $response->assertViewHas('backToStudentProfileUrl', $profileUrl);
        $response->assertSee('Back to Student Profile');
        $response->assertSee('href="'.$profileUrl.'"', false);
    }

    public function test_back_to_student_profile_link_keeps_only_a_valid_history_page_number(): void
    {
        $assessment = $this->assessmentWithResult();
        $student = $assessment->student;
        $psychometrician = $this->psychometrician();

        $this->actingAs($psychometrician)
            ->withHeader('referer', route('students.show', [$student, 'page' => 2, 'evil' => '"><script>']))
            ->get(route('assessments.show', $assessment))
            ->assertViewHas('backToStudentProfileUrl', route('students.show', [$student, 'page' => 2]));

        foreach (['abc', '0', '1', '-3', '2abc'] as $page) {
            $this->actingAs($psychometrician)
                ->withHeader('referer', route('students.show', [$student, 'page' => $page]))
                ->get(route('assessments.show', $assessment))
                ->assertViewHas('backToStudentProfileUrl', route('students.show', $student));
        }
    }

    public function test_back_to_student_profile_link_does_not_appear_from_another_students_profile(): void
    {
        $assessment = $this->assessmentWithResult();
        $otherStudent = Student::factory()->create();

        $this->actingAs($this->psychometrician())
            ->withHeader('referer', route('students.show', $otherStudent))
            ->get(route('assessments.show', $assessment))
            ->assertOk()
            ->assertViewHas('backToStudentProfileUrl', null)
            ->assertDontSee('Back to Student Profile');
    }

    public function test_back_to_student_profile_link_does_not_appear_with_no_referer(): void
    {
        $assessment = $this->assessmentWithResult();

        $this->actingAs($this->psychometrician())
            ->get(route('assessments.show', $assessment))
            ->assertOk()
            ->assertViewHas('backToStudentProfileUrl', null)
            ->assertDontSee('Back to Student Profile');
    }

    public function test_back_to_student_profile_link_does_not_appear_from_an_external_site(): void
    {
        $assessment = $this->assessmentWithResult();

        $this->actingAs($this->psychometrician())
            ->withHeader('referer', 'https://evil.example.com/students/'.$assessment->student_id)
            ->get(route('assessments.show', $assessment))
            ->assertOk()
            ->assertViewHas('backToStudentProfileUrl', null)
            ->assertDontSee('Back to Student Profile');
    }

    public function test_back_to_student_profile_link_does_not_appear_for_the_guidance_counselor(): void
    {
        $assessment = $this->assessmentWithResult();

        $this->actingAs($this->guidanceCounselor())
            ->withHeader('referer', route('students.show', $assessment->student))
            ->get(route('assessments.show', $assessment))
            ->assertOk()
            ->assertViewHas('backToStudentProfileUrl', null)
            ->assertDontSee('Back to Student Profile');
    }

    public function test_back_to_student_profile_link_does_not_appear_from_other_pages_ending_in_the_students_id(): void
    {
        $assessment = $this->assessmentWithResult();
        $psychometrician = $this->psychometrician();

        foreach ([
            route('counseling-sessions.students.show', $assessment->student),
            route('students.edit', $assessment->student),
        ] as $referer) {
            $this->actingAs($psychometrician)
                ->withHeader('referer', $referer)
                ->get(route('assessments.show', $assessment))
                ->assertOk()
                ->assertViewHas('backToStudentProfileUrl', null);
        }
    }

    public function test_back_to_assessment_history_link_appears_for_both_roles_and_keeps_its_whitelisted_filters(): void
    {
        $assessment = $this->assessmentWithResult();
        $referer = route('assessments.index', ['search' => 'Juan Cruz', 'student_number' => '2026-00004', 'page' => 3, 'evil' => '"><script>', 'tab' => 'normal']);
        $expected = route('assessments.index', ['search' => 'Juan Cruz', 'student_number' => '2026-00004', 'page' => 3]);

        foreach ([$this->psychometrician(), $this->guidanceCounselor()] as $user) {
            $this->actingAs($user)
                ->withHeader('referer', $referer)
                ->get(route('assessments.show', $assessment))
                ->assertOk()
                ->assertViewHas('backToAssessmentHistoryUrl', $expected)
                ->assertSee('Back to Assessment History')
                ->assertDontSee('evil=', false);
        }

        $this->actingAs($this->psychometrician())
            ->withHeader('referer', route('assessments.index'))
            ->get(route('assessments.show', $assessment))
            ->assertViewHas('backToAssessmentHistoryUrl', route('assessments.index'));
    }

    public function test_back_to_assessment_history_link_does_not_appear_from_other_pages_no_referer_or_external_sites(): void
    {
        $assessment = $this->assessmentWithResult();
        $other = $this->assessmentWithResult();

        foreach ([
            null,
            'https://evil.example.com/assessments?search=x',
            route('assessments.show', $other),
            route('flagged-cases.index'),
        ] as $referer) {
            $this->flushHeaders();
            $request = $this->actingAs($this->psychometrician());

            if ($referer !== null) {
                $request = $request->withHeader('referer', $referer);
            }

            $request->get(route('assessments.show', $assessment))
                ->assertOk()
                ->assertViewHas('backToAssessmentHistoryUrl', null)
                ->assertDontSee('Back to Assessment History');
        }
    }

    public function test_back_to_flagged_cases_link_appears_for_the_guidance_counselor_and_keeps_its_whitelisted_filters(): void
    {
        $assessment = $this->assessmentWithResult();
        $filters = [
            'tab' => 'endorsement',
            'search' => 'Juan',
            'course_id' => '3',
            'year_level_id' => '2',
            'section_id' => '1',
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
            'page' => '2',
        ];

        $this->actingAs($this->guidanceCounselor())
            ->withHeader('referer', route('flagged-cases.index', [...$filters, 'archived' => '1', 'search_extra' => 'x']))
            ->get(route('assessments.show', $assessment))
            ->assertOk()
            ->assertViewHas('backToFlaggedCasesUrl', route('flagged-cases.index', [...$filters, 'page' => 2]))
            ->assertSee('Back to Flagged Cases');
    }

    public function test_back_to_flagged_cases_link_does_not_appear_for_the_psychometrician_other_pages_no_referer_or_external_sites(): void
    {
        $assessment = $this->assessmentWithResult();

        $this->actingAs($this->psychometrician())
            ->withHeader('referer', route('flagged-cases.index', ['tab' => 'endorsement']))
            ->get(route('assessments.show', $assessment))
            ->assertOk()
            ->assertViewHas('backToFlaggedCasesUrl', null)
            ->assertDontSee('Back to Flagged Cases');

        foreach ([null, 'https://evil.example.com/flagged-cases', route('reports.flagged-students.print'), route('notifications.index')] as $referer) {
            $this->flushHeaders();
            $request = $this->actingAs($this->guidanceCounselor());

            if ($referer !== null) {
                $request = $request->withHeader('referer', $referer);
            }

            $request->get(route('assessments.show', $assessment))
                ->assertOk()
                ->assertViewHas('backToFlaggedCasesUrl', null)
                ->assertDontSee('Back to Flagged Cases');
        }
    }

    public function test_back_to_notifications_link_appears_after_opening_a_notification_and_keeps_the_archived_view(): void
    {
        $counselor = $this->guidanceCounselor();
        $assessment = $this->assessmentWithResult();
        $notification = SystemNotification::factory()->create([
            'user_id' => $counselor->id,
            'assessment_id' => $assessment->id,
        ]);

        // The real path: open the notification (marks it read, redirects
        // to the assessment). The test client re-sends the same Referer
        // on the redirected request, as browsers do (checked in headless
        // Chrome and Edge).
        $this->actingAs($counselor)
            ->withHeader('referer', route('notifications.index', ['archived' => '1', 'page' => '2', 'evil' => 'x']))
            ->followingRedirects()
            ->get(route('notifications.view', $notification))
            ->assertOk()
            ->assertViewHas('assessment', fn ($shown) => $shown->is($assessment))
            ->assertViewHas('backToNotificationsUrl', route('notifications.index', ['archived' => 1, 'page' => 2]))
            ->assertSee('Back to Notifications');

        $this->assertTrue($notification->fresh()->is_read);
    }

    public function test_back_to_notifications_link_only_keeps_a_truthy_archived_flag(): void
    {
        $counselor = $this->guidanceCounselor();
        $assessment = $this->assessmentWithResult();

        foreach ([
            route('notifications.index') => route('notifications.index'),
            route('notifications.index', ['archived' => '0']) => route('notifications.index'),
            route('notifications.index', ['archived' => 'true']) => route('notifications.index', ['archived' => 1]),
        ] as $referer => $expected) {
            $this->actingAs($counselor)
                ->withHeader('referer', $referer)
                ->get(route('assessments.show', $assessment))
                ->assertViewHas('backToNotificationsUrl', $expected);
        }
    }

    public function test_back_to_notifications_link_does_not_appear_for_the_psychometrician_other_pages_no_referer_or_external_sites(): void
    {
        $assessment = $this->assessmentWithResult();

        $this->actingAs($this->psychometrician())
            ->withHeader('referer', route('notifications.index', ['archived' => '1']))
            ->get(route('assessments.show', $assessment))
            ->assertOk()
            ->assertViewHas('backToNotificationsUrl', null)
            ->assertDontSee('Back to Notifications');

        foreach ([null, 'https://evil.example.com/notifications?archived=1', route('flagged-cases.index'), url('/notifications/1/view')] as $referer) {
            $this->flushHeaders();
            $request = $this->actingAs($this->guidanceCounselor());

            if ($referer !== null) {
                $request = $request->withHeader('referer', $referer);
            }

            $request->get(route('assessments.show', $assessment))
                ->assertOk()
                ->assertViewHas('backToNotificationsUrl', null)
                ->assertDontSee('Back to Notifications');
        }
    }

    public function test_at_most_one_back_link_applies_to_any_referer(): void
    {
        $counselor = $this->guidanceCounselor();
        $psychometrician = $this->psychometrician();
        $assessment = $this->assessmentWithResult();
        $session = CounselingSession::factory()->create([
            'assessment_id' => $assessment->id,
            'student_id' => $assessment->student_id,
            'counselor_id' => $counselor->id,
        ]);

        $backLinkKeys = [
            'backToCounselingSession', 'backToDashboardUrl', 'backToCounselingHistoryUrl', 'backToStudentProfileUrl',
            'backToAssessmentHistoryUrl', 'backToFlaggedCasesUrl', 'backToNotificationsUrl',
        ];

        $cases = [
            [$psychometrician, route('psychometrician.dashboard'), 'backToDashboardUrl'],
            [$psychometrician, route('students.show', $assessment->student), 'backToStudentProfileUrl'],
            [$psychometrician, route('assessments.index'), 'backToAssessmentHistoryUrl'],
            [$counselor, route('guidance-counselor.dashboard'), 'backToDashboardUrl'],
            [$counselor, route('counseling-sessions.show', $session), 'backToCounselingSession'],
            [$counselor, route('counseling-sessions.students.show', $assessment->student), 'backToCounselingHistoryUrl'],
            [$counselor, route('assessments.index'), 'backToAssessmentHistoryUrl'],
            [$counselor, route('flagged-cases.index'), 'backToFlaggedCasesUrl'],
            [$counselor, route('notifications.index'), 'backToNotificationsUrl'],
        ];

        foreach ($cases as [$user, $referer, $expectedKey]) {
            $view = $this->actingAs($user)
                ->withHeader('referer', $referer)
                ->get(route('assessments.show', $assessment))
                ->assertOk()
                ->viewData(...);

            $shown = array_values(array_filter($backLinkKeys, fn (string $key): bool => $view($key) !== null));

            $this->assertSame([$expectedKey], $shown, "Referer {$referer}");
        }
    }

    public function test_back_to_student_profile_link_does_not_appear_for_an_archived_student(): void
    {
        $assessment = $this->assessmentWithResult();
        $profileUrl = route('students.show', $assessment->student);
        $assessment->student->delete();

        $this->actingAs($this->psychometrician())
            ->withHeader('referer', $profileUrl)
            ->get(route('assessments.show', $assessment->fresh()))
            ->assertOk()
            ->assertViewHas('backToStudentProfileUrl', null);
    }
}
