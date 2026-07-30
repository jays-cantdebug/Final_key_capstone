<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\CounselingSession;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Displays the final, read-only result of a completed assessment.
 *
 * The AI classification was already computed and persisted at submission
 * time (see AssessmentService::submit()) — an assessment is read-only
 * after submission, so this never re-classifies on view.
 */
class AssessmentController extends Controller
{
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
        ]);
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
        $referer = $request->headers->get('referer');

        if ($referer === null || parse_url($referer, PHP_URL_HOST) !== $request->getHost()) {
            return null;
        }

        if (! preg_match('#/counseling-sessions/(\d+)$#', (string) parse_url($referer, PHP_URL_PATH), $matches)) {
            return null;
        }

        $session = CounselingSession::find((int) $matches[1]);

        return $session?->assessment_id === $assessment->id ? $session : null;
    }
}
