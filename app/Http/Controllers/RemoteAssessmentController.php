<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\RemoteDraftStateException;
use App\Http\Middleware\RefuseWhenStudentEntryOnly;
use App\Http\Middleware\RestrictStudentDeviceNetwork;
use App\Http\Requests\AssessmentResponseFormRequest;
use App\Http\Requests\AssessmentStudentRequest;
use App\Models\Assessment;
use App\Models\QuestionnaireVersion;
use App\Models\RemoteAssessmentDraft;
use App\Models\Student;
use App\Services\AssessmentService;
use App\Services\AssessmentWizardStaging;
use App\Services\RemoteAssessmentService;
use App\Services\StudentDuplicateService;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

/**
 * The Psychometrician's side of a student-device assessment (New
 * Assessment Step 2, "Send to student device", or Step 1, "Let the student
 * fill this in on their device"): creating the draft, the live page and its
 * polling, correcting the details the student typed, New code / Return to
 * student / Restart on the new version / Cancel, and Submit, which re-runs
 * Step 1's checks on typed details, stages the answers exactly like Step
 * 2's own submit (AssessmentWizardStaging) and continues into the
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

    public const NO_DETAILS_MESSAGE = 'The student hasn’t sent their details yet.';

    public const ACTIVE_MATCH_MESSAGE = 'An active student with this name already exists, so a second record can’t be created. Use Take Again for that student, or correct the details if they were mistyped.';

    public const INVALID_DETAILS_MESSAGE = 'Please correct the student’s details before submitting.';

    public const STUDENT_ENTRY_ONLY_MESSAGE = RefuseWhenStudentEntryOnly::STEP_ONE_MESSAGE;

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

        // Student entry only: details the Psychometrician typed (staged
        // before the flag was turned on) are never sent; Take Again is fine.
        if (RemoteAssessmentService::studentEntryOnly() && ! $request->session()->has(self::SESSION_KEY.'.existing_student_id')) {
            return redirect()->route('assessments.create')
                ->withErrors(['student' => self::STUDENT_ENTRY_ONLY_MESSAGE]);
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
     * POST (from Step 1): the student fills in Step 1 themselves on the
     * device, then answers the questionnaire. Starts the wizard over like a
     * Step 1 POST (clears it and discards any earlier draft) and starts a
     * draft that collects the details. Nothing about the student is held
     * in this session until Submit; until then the details exist only in
     * the encrypted draft.
     */
    public function storeForStudent(Request $request): RedirectResponse
    {
        // Student entry only: this is "Send to student device again" on the
        // live page, and works like New Assessment — never replacing a live
        // draft.
        if (RemoteAssessmentService::studentEntryOnly()) {
            return $this->start($request);
        }

        $version = $this->assessmentService->activeQuestionnaireVersion();

        if ($version === null) {
            return redirect()->route('assessments.create')
                ->withErrors(['student' => 'No active questionnaire version is currently configured. Please contact an administrator.']);
        }

        $this->remoteAssessments->discardFor($request->user());
        $request->session()->forget(self::SESSION_KEY);

        return $this->startIdentityDraft($request, $version);
    }

    /**
     * POST "New Assessment" (the sidebar button; Psychometrician only, CSRF
     * protected). Resume-or-create, in this order:
     * 1. a live draft of this Psychometrician (a student-device run or a
     *    Take Again on the student device) — its live page; never discarded;
     * 2. answers staged and waiting for Step 3 — Step 3, with "Discard and
     *    start a new assessment"; never wiped silently;
     * 3. otherwise the wizard starts over (as a Step 1 POST always did) with
     *    a new draft where the student enters Step 1, and its live page.
     * Always a 303. Nothing is created when an error stops it (no active
     * questionnaire version). GET /assessments/create never creates.
     */
    public function start(Request $request): RedirectResponse
    {
        if ($this->resumeLiveDraft($request)) {
            return redirect()->route('assessments.create.remote', status: 303);
        }

        if ($request->session()->get(self::SESSION_KEY.'.responses') !== null
            && $request->session()->get(self::SESSION_KEY.'.student_data') !== null) {
            return redirect()->route('assessments.create.result', status: 303)->with('wizard_resumed', true);
        }

        $version = $this->assessmentService->activeQuestionnaireVersion();

        if ($version === null) {
            return redirect()->route('assessments.create', status: 303)
                ->withErrors(['student' => 'No active questionnaire version is currently configured. Please contact an administrator.']);
        }

        $request->session()->forget(self::SESSION_KEY);

        return $this->startIdentityDraft($request, $version);
    }

    /**
     * POST "Discard and start a new assessment" (Step 3, when New Assessment
     * resumed staged answers): clears the wizard — nothing of it was saved —
     * and starts a new student-device run.
     */
    public function discardAndStart(Request $request): RedirectResponse
    {
        $version = $this->assessmentService->activeQuestionnaireVersion();

        if ($version === null) {
            return redirect()->route('assessments.create.result', status: 303)
                ->withErrors(['student' => 'No active questionnaire version is currently configured. Please contact an administrator.']);
        }

        $this->remoteAssessments->discardFor($request->user());
        $request->session()->forget(self::SESSION_KEY);

        return $this->startIdentityDraft($request, $version);
    }

    /**
     * A new draft where the student enters Step 1, held in this session.
     */
    private function startIdentityDraft(Request $request, QuestionnaireVersion $version): RedirectResponse
    {
        ['draft' => $draft, 'token' => $token, 'short_code' => $shortCode] = $this->remoteAssessments->create($request->user(), $version, collectsIdentity: true);

        $request->session()->put([
            self::SESSION_KEY.'.remote_draft_id' => $draft->id,
            self::SESSION_KEY.'.remote_token' => $token,
            self::SESSION_KEY.'.remote_short_code' => $shortCode,
        ]);

        return redirect()->route('assessments.create.remote', status: 303);
    }

    /**
     * Whether this Psychometrician has a live draft the live page can show,
     * making sure this session holds it. The draft this session started is
     * always resumed. A live draft from an earlier session (e.g. one that
     * expired before the draft did) is taken over when it's a student-entry
     * draft: if no device has it yet, its code and link are renewed (their
     * plain values were only in the old session; the draft and its expiry
     * are kept). An earlier session's Take Again draft can't be shown (its
     * student's details were in that session), so it isn't resumed.
     */
    private function resumeLiveDraft(Request $request): bool
    {
        $owned = $this->ownedDraft($request);

        if ($this->remoteAssessments->isLive($owned)) {
            return $owned->collects_identity || $request->session()->has(self::SESSION_KEY.'.student_data');
        }

        $orphan = RemoteAssessmentDraft::query()->where('psychometrician_id', $request->user()->getKey())->first();

        if (! $this->remoteAssessments->isLive($orphan) || ! $orphan->collects_identity) {
            return false;
        }

        $request->session()->forget(self::SESSION_KEY);
        $request->session()->put(self::SESSION_KEY.'.remote_draft_id', $orphan->id);

        if ($orphan->status === RemoteAssessmentDraft::STATUS_PENDING) {
            ['token' => $token, 'short_code' => $shortCode] = $this->remoteAssessments->reissue($orphan);
            $request->session()->put([
                self::SESSION_KEY.'.remote_token' => $token,
                self::SESSION_KEY.'.remote_short_code' => $shortCode,
            ]);
        }

        return true;
    }

    /**
     * GET: the live page. For a draft that collects the details, they are
     * shown (with the correction form) once the student sends them, along
     * with the duplicate-student panel when the name matches a student —
     * on this page only, never on the device.
     */
    public function show(Request $request): View
    {
        $draft = $this->ownedDraftOr404($request);
        $token = $request->session()->get(self::SESSION_KEY.'.remote_token');
        $identity = $draft->collects_identity ? $draft->identity : null;

        if ($draft->collects_identity) {
            $student = $identity === null ? null : new Student($identity);
        } else {
            $student = new Student($request->session()->get(self::SESSION_KEY.'.student_data') ?? abort(404));
        }

        return view('assessments.create.remote', [
            'student' => $student,
            'identity' => $identity,
            'duplicate' => $identity === null ? null : $this->duplicatePanel($identity),
            'courses' => $this->assessmentService->activeCourses(),
            'yearLevels' => $this->assessmentService->activeYearLevels(),
            'sections' => $this->assessmentService->activeSections(),
            'draft' => $draft,
            'questions' => $draft->questionnaireVersion->questions,
            'shortCode' => $request->session()->get(self::SESSION_KEY.'.remote_short_code'),
            'studentEntryUrl' => $this->remoteAssessments->studentEntryUrl(),
            // For the Copy link button only (a data attribute on this staff
            // page; never text, never an <a>, never in the poll JSON), and
            // only while no device has the draft yet.
            'studentLink' => is_string($token) && $draft->status === RemoteAssessmentDraft::STATUS_PENDING
                ? $this->remoteAssessments->studentTokenUrl($token)
                : null,
            'loopback' => $this->remoteAssessments->baseUrlIsLoopback(),
            // Staff only: which student PCs may open the address (never shown on the device).
            'allowedIps' => RestrictStudentDeviceNetwork::entries(),
            'invalidAllowedIps' => RestrictStudentDeviceNetwork::invalidEntries(),
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
     * POST: a new code and link, the device unbound, answers kept.
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

        return redirect()->route('assessments.create.remote')->with('status', 'A new code and link were created. The previous code, link and device no longer work.');
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
     * PUT: correct the details the student typed (a typo), with Step 1's
     * rules and normalization. Only the details: the answers stay as the
     * student gave them. Re-runs the duplicate check; a held device
     * continues once no active student has the corrected name.
     */
    public function correctIdentity(Request $request): RedirectResponse
    {
        $draft = $this->ownedDraftOr404($request);

        if (! $draft->collects_identity || $draft->identity_submitted_at === null) {
            return $this->backToLivePage(['remote' => self::NO_DETAILS_MESSAGE]);
        }

        $input = $request->only(AssessmentStudentRequest::IDENTITY_FIELDS);
        $validator = $this->identityValidator([...$input, ...AssessmentStudentRequest::normalizedNameParts($input)]);

        if ($validator->fails()) {
            return redirect()->route('assessments.create.remote')->withErrors($validator, 'identity')->withInput($input);
        }

        $identity = $this->castIdentity($validator->validated());
        $matches = $this->duplicateService->findMatches($identity['first_name'], $identity['middle_name'], $identity['last_name']);

        try {
            $released = $this->remoteAssessments->correctIdentity($draft, $identity, $matches['active']->isNotEmpty());
        } catch (RemoteDraftStateException) {
            return $this->backToLivePage(['remote' => 'This session is no longer available. Send to the student device again.']);
        }

        return redirect()->route('assessments.create.remote')->with('status', $released
            ? 'The details were corrected. No active student has this name now, so the student device continues.'
            : 'The details were corrected.');
    }

    /**
     * DELETE: discard the draft, its answers and any details the student
     * typed; back to Step 2's choice (or to Step 1, when the student was
     * filling it in).
     */
    public function cancel(Request $request): RedirectResponse
    {
        $draft = $this->ownedDraftOr404($request);

        $draft->delete();
        $this->staging->forgetRemote($request->session());

        return redirect()->route($draft->collects_identity ? 'assessments.create' : 'assessments.create.questionnaire')
            ->with('status', 'The student device session was cancelled. Nothing was saved.');
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
     * also requires the staff checkbox here, as Step 2 does, and so does a
     * draft where the student filled in Step 1 (the checkbox is then the
     * staff attestation Step 1 would have had).
     *
     * Details typed on the device go through Step 1's checks again here:
     * its validation, then the duplicate check exactly as confirmStudent()
     * runs it (an active match blocks; an archived one needs the confirm
     * box, and is recorded as acknowledged for the final save). They are
     * then staged as Step 1 stages them, and nothing reaches `students`
     * until Step 3's save.
     */
    public function submit(Request $request): RedirectResponse
    {
        $draft = $this->ownedDraftOr404($request);
        $isRetake = $request->session()->has(self::SESSION_KEY.'.existing_student_id');

        if ($isRetake || $draft->collects_identity) {
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

        $archivedIds = [];

        if ($draft->collects_identity) {
            $identityValidator = $this->identityValidator($draft->identity ?? []);

            if ($draft->identity === null || $identityValidator->fails()) {
                return redirect()->route('assessments.create.remote')
                    ->withErrors($identityValidator, 'identity')
                    ->withErrors(['remote' => self::INVALID_DETAILS_MESSAGE]);
            }

            $identity = $this->castIdentity($identityValidator->validated());
            $matches = $this->duplicateService->findMatches($identity['first_name'], $identity['middle_name'], $identity['last_name']);

            if ($matches['active']->isNotEmpty()) {
                return $this->backToLivePage(['remote' => self::ACTIVE_MATCH_MESSAGE]);
            }

            if ($matches['archived']->isNotEmpty() && ! $request->boolean('confirm_archived_match')) {
                return $this->backToLivePage(['confirm_archived_match' => AssessmentWizardController::CONFIRM_ARCHIVED_MATCH_MESSAGE]);
            }

            $archivedIds = $matches['archived']->modelKeys();
            $request->session()->put(self::SESSION_KEY.'.student_data', [...$identity, 'privacy_consent_at' => $draft->consented_at]);
        }

        $consentAt = $draft->consented_at;

        $this->staging->stageResponses($request->session(), $version, $validator->validated()['responses'], $consentAt);

        if ($consentAt !== null && ! $isRetake) {
            $request->session()->put(self::SESSION_KEY.'.student_data.privacy_consent_at', $consentAt);
        }

        if ($archivedIds !== []) {
            $request->session()->put(self::SESSION_KEY.'.acknowledged_archived_ids', $archivedIds);
        }

        $request->session()->put(self::SESSION_KEY.'.'.AssessmentWizardStaging::ADMINISTRATION_MODE_KEY, Assessment::ADMINISTRATION_STUDENT_DEVICE);

        $draft->delete();
        $this->staging->forgetRemote($request->session());

        return redirect()->route('assessments.create.result');
    }

    /**
     * Step 1's own rules and messages for the details (the device's are the
     * same, but Active lookups only).
     *
     * @param  array<string, mixed>  $data
     */
    private function identityValidator(array $data): ValidatorContract
    {
        return Validator::make(
            $data,
            AssessmentStudentRequest::identityRules(),
            AssessmentStudentRequest::identityMessages(),
        );
    }

    /**
     * @param  array<string, mixed>  $identity
     * @return array<string, mixed>
     */
    private function castIdentity(array $identity): array
    {
        foreach (['course_id', 'year_level_id', 'section_id'] as $field) {
            $identity[$field] = (int) $identity[$field];
        }

        return $identity;
    }

    /**
     * The duplicate-student panel for the details on the live page, built
     * like Step 1's (assessments/create/_duplicate-student): an active match
     * first, otherwise an archived one. Null when nobody matches.
     *
     * @param  array<string, mixed>  $identity
     * @return array<string, mixed>|null
     */
    private function duplicatePanel(array $identity): ?array
    {
        if (! is_string($identity['first_name'] ?? null) || ! is_string($identity['last_name'] ?? null)) {
            return null;
        }

        $matches = $this->duplicateService->findMatches($identity['first_name'], $identity['middle_name'] ?? null, $identity['last_name']);
        [$kind, $students] = $matches['active']->isNotEmpty() ? ['active', $matches['active']] : ['archived', $matches['archived']];

        if ($students->isEmpty()) {
            return null;
        }

        $nameParts = Arr::only($identity, ['first_name', 'middle_name', 'last_name']);

        return [
            'kind' => $kind,
            'ids' => $students->modelKeys(),
            'name' => implode(' ', array_filter($nameParts, fn ($part): bool => filled($part))),
            'search' => trim($identity['first_name'].' '.$identity['last_name']),
            'students' => $this->duplicateService->describe($students->modelKeys()),
        ];
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
