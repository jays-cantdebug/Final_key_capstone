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
        ]);
    }

    /**
     * The Referer's path, or null when there is no Referer or it points at
     * another host — so a back link is only ever derived from a page on
     * this app. Shared by every Referer-resolved back link on this page
     * ("Back to Counseling Session", "Back to Counseling History", and
     * "Back to Dashboard").
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
     * a dashboard their role can't open. The Referer's query string
     * (Psychometrician Dashboard period/course/year-level/severity filters
     * and Recent Assessments page) is carried back so returning restores
     * the exact view the user left — rebuilt through route() rather than
     * echoing the raw Referer URL.
     */
    private function resolveBackToDashboardUrl(Request $request): ?string
    {
        $routeName = $this->dashboardRouteService->resolve($request->user());

        if ($routeName === 'dashboard' || $this->refererPath($request) !== parse_url(route($routeName), PHP_URL_PATH)) {
            return null;
        }

        parse_str((string) parse_url((string) $request->headers->get('referer'), PHP_URL_QUERY), $query);

        return route($routeName, $query);
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
}
