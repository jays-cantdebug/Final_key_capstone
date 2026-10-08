<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\RemoteDraftStateException;
use App\Http\Requests\AssessmentResponseFormRequest;
use App\Models\Assessment;
use App\Models\RemoteAssessmentDraft;
use App\Models\Student;
use App\Services\AssessmentService;
use App\Services\AssessmentWizardStaging;
use App\Services\RemoteAssessmentService;
use App\Services\StudentDuplicateService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * The Psychometrician's side of a student-device assessment (New
 * Assessment Step 2, "Send to student device"): creating the draft, the
 * live page and its polling, New code / Return to student / Restart on the
 * new version / Cancel, and Submit, which stages the answers exactly like
 * Step 2's own submit (AssessmentWizardStaging) and continues into the
 * unchanged Step 3 review and final save.
 *
 * Every action works only on the draft id held in this Psychometrician's
 * own wizard session AND owned by them (RemoteAssessmentService::
 * ownedDraft()); anything else is a 404, the same as a draft that doesn't
 * exist.
 */
class RemoteAssessmentController extends Controller
{
    private const SESSION_KEY = AssessmentWizardStaging::SESSION_KEY;

    public const NOT_LOCKED_MESSAGE = 'The student hasn’t pressed Done yet.';

    public const VERSION_CHANGED_MESSAGE = 'The active questionnaire changed while the student was answering. Restart on the new version to have it answered again.';

    public function __construct(
        private readonly RemoteAssessmentService $remoteAssessments,
        private readonly AssessmentService $assessmentService,
        private readonly StudentDuplicateService $duplicateService,
        private readonly AssessmentWizardStaging $staging,
    ) {}

    /**
     * POST: start a draft on the Active version (replacing any earlier
     * one), with one extra, non-blocking duplicate re-check for a new
     * student — the save still refuses an active namesake, as always.
     */
    public function store(Request $request): RedirectResponse
    {
        $studentData = $request->session()->get(self::SESSION_KEY.'.student_data');

        if ($studentData === null) {
            return redirect()->route('assessments.create')
                ->withErrors(['student' => 'Please select or register a student before continuing.']);
        }

        $version = $this->assessmentService->activeQuestionnaireVersion();

        if ($version === null) {
            return redirect()->route('assessments.create')
                ->withErrors(['student' => 'No active questionnaire version is currently configured. Please contact an administrator.']);
        }

        $duplicateWarning = false;

        if (! $request->session()->has(self::SESSION_KEY.'.existing_student_id')) {
            $matches = $this->duplicateService->findMatches(
                (string) $studentData['first_name'],
                $studentData['middle_name'] ?? null,
                (string) $studentData['last_name'],
            );
            $duplicateWarning = $matches['active']->isNotEmpty();
        }

        ['draft' => $draft, 'token' => $token, 'short_code' => $shortCode] = $this->remoteAssessments->create($request->user(), $version);

        // The new answers will come from the device: drop anything staged.
        $request->session()->forget([
            self::SESSION_KEY.'.responses',
            self::SESSION_KEY.'.review',
            self::SESSION_KEY.'.questionnaire_version_id',
            self::SESSION_KEY.'.'.AssessmentWizardStaging::ADMINISTRATION_MODE_KEY,
        ]);
        $request->session()->put([
            self::SESSION_KEY.'.remote_draft_id' => $draft->id,
            self::SESSION_KEY.'.remote_token' => $token,
            self::SESSION_KEY.'.remote_short_code' => $shortCode,
        ]);

        return redirect()->route('assessments.create.remote')->with('remote_duplicate_warning', $duplicateWarning);
    }

    /**
     * GET: the live page.
     */
    public function show(Request $request): View
    {
        $draft = $this->ownedDraftOr404($request);
        $studentData = $request->session()->get(self::SESSION_KEY.'.student_data') ?? abort(404);
        $token = $request->session()->get(self::SESSION_KEY.'.remote_token');

        return view('assessments.create.remote', [
            'student' => new Student($studentData),
            'draft' => $draft,
            'questions' => $draft->questionnaireVersion->questions,
            'shortCode' => $request->session()->get(self::SESSION_KEY.'.remote_short_code'),
            'studentEntryUrl' => $this->remoteAssessments->studentEntryUrl(),
            'qrSvg' => is_string($token) ? $this->remoteAssessments->qrSvg($this->remoteAssessments->studentTokenUrl($token)) : null,
            'loopback' => $this->remoteAssessments->baseUrlIsLoopback(),
            'duplicateWarning' => (bool) $request->session()->get('remote_duplicate_warning'),
            'monitor' => $this->remoteAssessments->monitorData($draft, $this->assessmentService->activeQuestionnaireVersion()),
            'pollIntervalMs' => (int) config('remote_assessment.poll_interval_ms'),
            'existingStudentId' => $request->session()->get(self::SESSION_KEY.'.existing_student_id'),
        ]);
    }

    /**
     * GET (JSON, polled): the draft's state and answers. With `?rev=` equal
     * to the current revision, only the fields that change without one.
     * Expired drafts — anyone's — are pruned on every poll.
     */
    public function status(Request $request): JsonResponse
    {
        $this->remoteAssessments->pruneExpired();

        $draft = $this->ownedDraft($request);

        if ($draft === null) {
            return response()->json(['state' => 'gone'], 404);
        }

        $rev = $request->query('rev');

        return response()->json($this->remoteAssessments->monitorData(
            $draft,
            $this->assessmentService->activeQuestionnaireVersion(),
            is_string($rev) && ctype_digit($rev) ? (int) $rev : null,
        ))->header('Cache-Control', 'no-store, max-age=0');
    }

    /**
     * POST: new token and code, the device unbound, answers kept.
     */
    public function newCode(Request $request): RedirectResponse
    {
        $draft = $this->ownedDraftOr404($request);

        try {
            ['token' => $token, 'short_code' => $shortCode] = $this->remoteAssessments->reissue($draft);
        } catch (RemoteDraftStateException) {
            return $this->backToLivePage(['remote' => 'This code can no longer be renewed. Send to the student device again.']);
        }

        $request->session()->put([
            self::SESSION_KEY.'.remote_token' => $token,
            self::SESSION_KEY.'.remote_short_code' => $shortCode,
        ]);

        return redirect()->route('assessments.create.remote')->with('status', 'A new code was created. The previous code and device no longer work.');
    }

    /**
     * POST: unlock a locked draft so the student can edit again.
     */
    public function returnToStudent(Request $request): RedirectResponse
    {
        $draft = $this->ownedDraftOr404($request);

        if (! $this->remoteAssessments->returnToStudent($draft)) {
            return $this->backToLivePage(['remote' => self::NOT_LOCKED_MESSAGE]);
        }

        return redirect()->route('assessments.create.remote')->with('status', 'The questionnaire was returned to the student device.');
    }

    /**
     * POST: re-pin the draft on the now-Active version and clear its answers.
     */
    public function restart(Request $request): RedirectResponse
    {
        $draft = $this->ownedDraftOr404($request);
        $active = $this->assessmentService->activeQuestionnaireVersion();

        if ($active === null || $active->id === $draft->questionnaire_version_id) {
            return $this->backToLivePage(['remote' => 'The questionnaire version has not changed.']);
        }

        $this->remoteAssessments->restartOn($draft, $active);

        return redirect()->route('assessments.create.remote')->with('status', 'Restarted on the new questionnaire version. The student device will reload.');
    }

    /**
     * DELETE: discard the draft and its answers; back to Step 2's choice.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $draft = $this->ownedDraftOr404($request);

        $draft->delete();
        $this->staging->forgetRemote($request->session());

        return redirect()->route('assessments.create.questionnaire')->with('status', 'The student device session was cancelled. Nothing was saved.');
    }

    /**
     * POST: only once the student pressed Done. Validates the answers with
     * the same rules as Step 2 (AssessmentResponseFormRequest::rulesFor()),
     * stages them exactly like Step 2's submit (pinning the version), marks
     * the run as answered on a student device, deletes the draft, and
     * continues into the unchanged Step 3.
     *
     * Consent: the student's on-device acknowledgment time (when that
     * screen is on) becomes the recorded `privacy_consent_at`. Take Again
     * also requires the staff checkbox here, as Step 2 does.
     */
    public function submit(Request $request): RedirectResponse
    {
        $draft = $this->ownedDraftOr404($request);
        $isRetake = $request->session()->has(self::SESSION_KEY.'.existing_student_id');

        if ($isRetake) {
            $request->validate(
                ['privacy_consent' => ['required', 'accepted']],
                ['privacy_consent.required' => 'Please check the privacy consent box to continue.', 'privacy_consent.accepted' => 'Please check the privacy consent box to continue.'],
            );
        }

        if ($this->remoteAssessments->monitorState($draft) !== RemoteAssessmentDraft::STATUS_LOCKED) {
            return $this->backToLivePage(['remote' => self::NOT_LOCKED_MESSAGE]);
        }

        $version = $draft->questionnaireVersion;

        if ($this->assessmentService->activeQuestionnaireVersion()?->id !== $version->id) {
            return $this->backToLivePage(['remote' => self::VERSION_CHANGED_MESSAGE]);
        }

        $validator = Validator::make(
            ['responses' => $draft->responses ?? []],
            AssessmentResponseFormRequest::rulesFor($version),
        );

        if ($validator->fails()) {
            return $this->backToLivePage(['remote' => 'The student’s answers are incomplete. Return the questionnaire to the student.']);
        }

        $consentAt = $draft->consented_at;

        $this->staging->stageResponses($request->session(), $version, $validator->validated()['responses'], $consentAt);

        if ($consentAt !== null && ! $isRetake) {
            $request->session()->put(self::SESSION_KEY.'.student_data.privacy_consent_at', $consentAt);
        }

        $request->session()->put(self::SESSION_KEY.'.'.AssessmentWizardStaging::ADMINISTRATION_MODE_KEY, Assessment::ADMINISTRATION_STUDENT_DEVICE);

        $draft->delete();
        $this->staging->forgetRemote($request->session());

        return redirect()->route('assessments.create.result');
    }

    private function ownedDraft(Request $request): ?RemoteAssessmentDraft
    {
        return $this->remoteAssessments->ownedDraft(
            $request->user(),
            $request->session()->get(self::SESSION_KEY.'.remote_draft_id'),
        );
    }

    private function ownedDraftOr404(Request $request): RemoteAssessmentDraft
    {
        return $this->ownedDraft($request) ?? abort(404);
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function backToLivePage(array $errors): RedirectResponse
    {
        return redirect()->route('assessments.create.remote')->withErrors($errors);
    }
}
