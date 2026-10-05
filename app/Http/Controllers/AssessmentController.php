<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\CounselingSession;
use App\Services\Auth\DashboardRouteService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Displays the final, read-only result of a completed assessment.
 *
 * The AI classification was already computed and persisted at submission
 * time (see AssessmentService::submit()) — an assessment is read-only
 * after submission, so this never re-classifies on view.
 */
class AssessmentController extends Controller
{
    /**
     * The query keys each role dashboard actually uses, carried back by
     * "Back to Dashboard". Psychometrician: DashboardFilterRequest's four
     * filters, plus `page` from the All Assessments table's paginator
     * (DashboardService::allAssessmentsTable()). Guidance Counselor: none —
     * its dashboard takes no filters and Recent Assessments isn't
     * paginated. Keep in sync if either dashboard gains a filter.
     *
     * @var array<string, array<int, string>>
     */
    private const DASHBOARD_QUERY_KEYS = [
        'psychometrician.dashboard' => ['period', 'course_id', 'year_level_id', 'severity_subscale', 'page'],
        'guidance-counselor.dashboard' => [],
    ];

    public function __construct(private readonly DashboardRouteService $dashboardRouteService) {}

    public function show(Assessment $assessment, Request $request): View
    {
        $assessment->load([
            'student.course',
            'student.yearLevel',
            'student.section',
            'questionnaireVersion.questionnaire',
            'result',
            'responses.question',
            'psychometrician',
            'flaggedCases',
            'predictionFeedback',
        ]);

        return view('assessments.show', [
            'assessment' => $assessment,
            'backToCounselingSession' => $this->resolveBackToCounselingSession($assessment, $request),
            'backToDashboardUrl' => $this->resolveBackToDashboardUrl($request),
            'backToCounselingHistoryUrl' => $this->resolveBackToCounselingHistoryUrl($assessment, $request),
            'backToStudentProfileUrl' => $this->resolveBackToStudentProfileUrl($assessment, $request),
            'backToAssessmentHistoryUrl' => $this->resolveBackToAssessmentHistoryUrl($request),
            'backToFlaggedCasesUrl' => $this->resolveBackToFlaggedCasesUrl($request),
            'backToNotificationsUrl' => $this->resolveBackToNotificationsUrl($request),
        ]);
    }

    /**
     * The Referer's path, or null when there is no Referer or it points at
     * another host — so a back link is only ever derived from a page on
     * this app. Shared by every Referer-resolved back link on this page.
     *
     * Every back link matches the Referer path *exactly* against the one
     * page it leads back to, and those pages all have different paths, so
     * at most one back link can ever apply to a single request — there is
     * no precedence between them to resolve.
     */
    private function refererPath(Request $request): ?string
    {
        $referer = $request->headers->get('referer');

        if ($referer === null || parse_url($referer, PHP_URL_HOST) !== $request->getHost()) {
            return null;
        }

        return (string) parse_url($referer, PHP_URL_PATH);
    }

    /**
     * "Back to Dashboard" appears when this page was reached from the
     * viewer's own role dashboard (the Recent Assessments "View" link on
     * the Psychometrician or Guidance Counselor Dashboard). Only the
     * viewer's own dashboard route counts, so the link can never point at
     * a dashboard their role can't open. Only that dashboard's own query
     * keys are carried back (see DASHBOARD_QUERY_KEYS), so returning
     * restores the exact view the user left — rebuilt through route(),
     * never echoing the raw Referer URL.
     */
    private function resolveBackToDashboardUrl(Request $request): ?string
    {
        $routeName = $this->dashboardRouteService->resolve($request->user());

        if (! array_key_exists($routeName, self::DASHBOARD_QUERY_KEYS)
            || $this->refererPath($request) !== parse_url(route($routeName), PHP_URL_PATH)) {
            return null;
        }

        return route($routeName, $this->refererQuery($request, self::DASHBOARD_QUERY_KEYS[$routeName]));
    }

    /**
     * "Back to Counseling Session" only makes sense when this page was
     * reached via the "Related Assessment" link on that specific session
     * — never from the Dashboard, Reports, Flagged Students, Assessment
     * History, or a student's profile, all of which also link here.
     * Rather than thread a query parameter through just that one link
     * (fragile to forget on future links, and pollutes the URL), this
     * inspects the Referer header: same host, path matching
     * `/counseling-sessions/{id}`, and that session must actually belong
     * to this assessment — a stale or unrelated referrer never produces
     * a misleading link. If the browser doesn't send a Referer (privacy
     * settings, extensions), the link just doesn't appear.
     */
    private function resolveBackToCounselingSession(Assessment $assessment, Request $request): ?CounselingSession
    {
        $path = $this->refererPath($request);

        if ($path === null || ! preg_match('#/counseling-sessions/(\d+)$#', $path, $matches)) {
            return null;
        }

        $session = CounselingSession::find((int) $matches[1]);

        return $session?->assessment_id === $assessment->id ? $session : null;
    }

    /**
     * "Back to Counseling History" appears when this page was reached via a
     * session row's "Related Assessment" link on a student's Counseling
     * History page (`/counseling-sessions/students/{id}`). Same Referer
     * approach as "Back to Counseling Session": the referring student must
     * actually have a counseling session linked to this assessment, and
     * the viewer must be allowed to open Counseling Sessions at all
     * (Guidance Counselor-only), so the link is never stale or a dead end.
     */
    private function resolveBackToCounselingHistoryUrl(Assessment $assessment, Request $request): ?string
    {
        $path = $this->refererPath($request);

        if ($path === null || ! preg_match('#/counseling-sessions/students/(\d+)$#', $path, $matches)) {
            return null;
        }

        if (! Gate::allows('viewAny', CounselingSession::class)) {
            return null;
        }

        $studentId = (int) $matches[1];

        $linked = CounselingSession::query()
            ->where('student_id', $studentId)
            ->where('assessment_id', $assessment->id)
            ->exists();

        return $linked ? route('counseling-sessions.students.show', $studentId) : null;
    }

    /**
     * "Back to Student Profile" appears when this page was reached via the
     * Assessment History "View" link on the profile of the student this
     * assessment belongs to (`/students/{id}`, Psychometrician-only). The
     * Referer path must equal that student's own profile path exactly —
     * another student's profile, or a different page that merely ends in
     * `/students/{id}` (e.g. Counseling History), never counts — and the
     * viewer must be allowed to open that profile, so the link is never a
     * dead end. An archived student's profile can't be opened (route model
     * binding excludes them), so no link is offered for one. The URL is
     * rebuilt through route() from the assessment's own student; only the
     * profile's Assessment History page number is carried over, and only
     * when it's a positive integer.
     */
    private function resolveBackToStudentProfileUrl(Assessment $assessment, Request $request): ?string
    {
        $student = $assessment->student;

        if ($student === null || $student->trashed() || ! Gate::allows('view', $student)) {
            return null;
        }

        if ($this->refererPath($request) !== parse_url(route('students.show', $student), PHP_URL_PATH)) {
            return null;
        }

        return route('students.show', [$student, ...$this->refererQuery($request, ['page'])]);
    }

    /**
     * "Back to Assessment History" appears when this page was reached from
     * the Assessment History list (`/assessments`, both roles). Its name
     * search, its `student_number` deep-link filter, and its page are
     * carried back.
     */
    private function resolveBackToAssessmentHistoryUrl(Request $request): ?string
    {
        if (! $request->user()->hasRole(['psychometrician', 'guidance_counselor'])
            || $this->refererPath($request) !== parse_url(route('assessments.index'), PHP_URL_PATH)) {
            return null;
        }

        return route('assessments.index', $this->refererQuery($request, ['search', 'student_number', 'page']));
    }

    /**
     * "Back to Flagged Cases" appears when this page was reached from the
     * Flagged Cases list (`/flagged-cases`, Guidance Counselor-only, the
     * same role its route middleware allows). Its tab, name search,
     * course/year level/section and date filters, and page are carried
     * back.
     */
    private function resolveBackToFlaggedCasesUrl(Request $request): ?string
    {
        if (! $request->user()->hasRole('guidance_counselor')
            || $this->refererPath($request) !== parse_url(route('flagged-cases.index'), PHP_URL_PATH)) {
            return null;
        }

        return route('flagged-cases.index', $this->refererQuery($request, [
            'tab', 'search', 'course_id', 'year_level_id', 'section_id', 'date_from', 'date_to', 'page',
        ]));
    }

    /**
     * "Back to Notifications" appears when this page was reached by
     * opening a notification (Guidance Counselor-only). That goes through
     * NotificationController::view(), which marks it read and redirects
     * here; browsers keep the original page as the Referer across that
     * redirect (checked in headless Chrome and Edge), so the Referer is
     * the Notifications list itself. Whether the archived view was on, and
     * the page, are carried back.
     */
    private function resolveBackToNotificationsUrl(Request $request): ?string
    {
        if (! $request->user()->hasRole('guidance_counselor')
            || $this->refererPath($request) !== parse_url(route('notifications.index'), PHP_URL_PATH)) {
            return null;
        }

        $query = $this->refererQuery($request, ['archived', 'page']);

        if (isset($query['archived'])) {
            if (filter_var($query['archived'], FILTER_VALIDATE_BOOLEAN)) {
                $query['archived'] = 1;
            } else {
                unset($query['archived']);
            }
        }

        return route('notifications.index', $query);
    }

    /**
     * The Referer's query string, reduced to the given whitelist of keys
     * so a back link never carries anything else from it: only plain,
     * non-empty string values are kept (arrays are dropped), and `page`
     * only when it's a whole number of 2 or more. The caller always
     * rebuilds the URL with route(), which encodes these values — the raw
     * Referer is never echoed.
     *
     * @param  array<int, string>  $allowedKeys
     * @return array<string, string|int>
     */
    private function refererQuery(Request $request, array $allowedKeys): array
    {
        parse_str((string) parse_url((string) $request->headers->get('referer'), PHP_URL_QUERY), $query);

        $kept = [];

        foreach ($allowedKeys as $key) {
            $value = $query[$key] ?? null;

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            if ($key === 'page') {
                $page = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2]]);

                if ($page !== false) {
                    $kept['page'] = $page;
                }

                continue;
            }

            $kept[$key] = $value;
        }

        return $kept;
    }
}
