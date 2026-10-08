<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\RemoteDraftStateException;
use App\Http\Requests\AssessmentStudentRequest;
use App\Http\Responses\StudentDeviceResponse;
use App\Models\RemoteAssessmentDraft;
use App\Services\AssessmentService;
use App\Services\RemoteAssessmentService;
use App\Services\StudentDuplicateService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * The student device — a PC provided by the guidance office: a
 * session-less, login-less questionnaire reached by a typed short code or
 * a link (routes/student-device.php), optionally only from listed addresses
 * (RestrictStudentDeviceNetwork). It only
 * ever shows the privacy notice (when enabled, and always before the
 * details form), the student's own details form (when the Psychometrician
 * chose that the student fills in Step 1), the instructions and rating
 * scale, the questions and answer buttons, a progress counter, a save
 * status line, Done, and the thank-you message — or the generic "not
 * available" message. It never reads the wizard session, and never shows
 * a student record: the only student data it handles is what the student
 * types into the details form, which is echoed back only on that form,
 * only when it fails validation, and never after it is accepted.
 */
class StudentDeviceController extends Controller
{
    public function __construct(
        private readonly RemoteAssessmentService $remoteAssessments,
        private readonly AssessmentService $assessmentService,
        private readonly StudentDuplicateService $duplicateService,
    ) {}

    /**
     * GET /s — the short-code form, or straight back to the questionnaire
     * when this device already holds a live draft.
     */
    public function entry(Request $request): View|RedirectResponse
    {
        if ($this->draft($request) !== null) {
            return $this->toQuestionnaire();
        }

        return view('student-device.code');
    }

    /**
     * POST /s — claim a draft by short code.
     */
    public function enterCode(Request $request): Response
    {
        if ($lockedOut = $this->entryLockedOut($request)) {
            return $lockedOut;
        }

        $code = $request->input('code');
        $deviceSecret = is_string($code) ? $this->remoteAssessments->claimWithShortCode($code) : null;

        return $this->claimed($request, $deviceSecret);
    }

    /**
     * GET /s/t/{token} — the "Begin" page for a link from the live page's
     * Copy link. Opening the link claims nothing, so a link preview or a
     * safe-browsing scanner can't use up the token; only Begin (the POST)
     * claims. The page's form posts back to its own address (no action
     * attribute), so the token appears in none of the HTML.
     */
    public function begin(Request $request, string $token): Response
    {
        $draft = $this->draft($request);

        if ($draft !== null && $this->remoteAssessments->tokenBelongsTo($draft, $token)) {
            return $this->toQuestionnaire();
        }

        if ($lockedOut = $this->entryLockedOut($request)) {
            return $lockedOut;
        }

        if ($this->remoteAssessments->pendingDraftForToken($token) === null) {
            return $this->failedEntry($request);
        }

        return response()->view('student-device.begin');
    }

    /**
     * POST /s/t/{token} — Begin: claim the draft by link token.
     */
    public function claim(Request $request, string $token): Response
    {
        $draft = $this->draft($request);

        if ($draft !== null && $this->remoteAssessments->tokenBelongsTo($draft, $token)) {
            return $this->toQuestionnaire();
        }

        if ($lockedOut = $this->entryLockedOut($request)) {
            return $lockedOut;
        }

        return $this->claimed($request, $this->remoteAssessments->claimWithToken($token));
    }

    /**
     * GET /s/q — whichever screen the draft is on: the privacy notice, the
     * questionnaire, or the thank-you message once locked. When the student
     * fills in their own details, the questionnaire page carries them too:
     * the open details form above locked questions until the details are
     * saved, then only "Details saved" (never the values) above the
     * questions.
     */
    public function show(Request $request): Response
    {
        $draft = $this->draft($request);

        if ($draft === null) {
            return $this->unavailableAndForgetDevice($request);
        }

        return match ($this->remoteAssessments->stateOf($draft)) {
            'consent' => response()->view('student-device.consent'),
            'identity' => $this->detailsAndQuestionnaire($draft),
            'answering' => response()->view('student-device.questionnaire', $this->questionnaireData($draft)),
            // Held: the generic message, word for word, whatever the reason.
            'help' => response()->view('student-device.held'),
            'locked' => response()->view('student-device.thanks'),
            default => $this->unavailableAndForgetDevice($request),
        };
    }

    /**
     * POST /s/identity — the student's own Step 1 details (after the
     * privacy notice), with Step 1's validation and normalization.
     *
     * Whether the name matches an existing student changes nothing in this
     * reply: the same validation, the same duplicate query and the same
     * single update run either way, and the answer is always the same
     * empty 303 to /s/q#questions. Only that page differs — a held draft
     * shows the generic message (which has no #questions) — and the
     * Psychometrician's live page shows the match. A draft not waiting for
     * details gets the same 303 too.
     */
    public function identity(Request $request): Response
    {
        $draft = $this->draft($request);

        if ($draft === null) {
            return $this->unavailableAndForgetDevice($request);
        }

        if (! $draft->awaitingIdentity()) {
            return $this->toQuestions();
        }

        $input = $request->only(AssessmentStudentRequest::IDENTITY_FIELDS);
        $validator = Validator::make(
            [...$input, ...AssessmentStudentRequest::normalizedNameParts($input)],
            AssessmentStudentRequest::studentDeviceIdentityRules(),
            AssessmentStudentRequest::studentDeviceIdentityMessages(),
        );

        if ($validator->fails()) {
            // Only what was typed on this device, back into its own form.
            return $this->detailsAndQuestionnaire($draft, $validator->getData(), $validator->errors(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $identity = $validator->validated();
        foreach (['course_id', 'year_level_id', 'section_id'] as $field) {
            $identity[$field] = (int) $identity[$field];
        }

        $matches = $this->duplicateService->findMatches($identity['first_name'], $identity['middle_name'], $identity['last_name']);

        try {
            $this->remoteAssessments->saveIdentity($draft, $identity, held: $matches['active']->isNotEmpty());
        } catch (RemoteDraftStateException) {
            // Already sent (a double click): show where it is.
        }

        return $this->toQuestions();
    }

    /**
     * POST /s/consent — the student acknowledged the privacy notice.
     */
    public function consent(Request $request): Response
    {
        $draft = $this->draft($request);

        if ($draft === null) {
            return $this->unavailableAndForgetDevice($request);
        }

        try {
            $this->remoteAssessments->consent($draft);
        } catch (RemoteDraftStateException) {
            // Already past consent (e.g. a double click): show where it is.
        }

        return $this->toQuestionnaire();
    }

    /**
     * POST /s/decline — the student declined: nothing is kept, and the
     * device is asked to be handed back.
     */
    public function decline(Request $request): Response
    {
        $draft = $this->draft($request);

        if ($draft === null) {
            return $this->unavailableAndForgetDevice($request);
        }

        try {
            $this->remoteAssessments->decline($draft);
        } catch (RemoteDraftStateException) {
            return $this->toQuestionnaire();
        }

        return response()->view('student-device.declined')->withCookie($this->forgetDeviceCookie($request));
    }

    /**
     * POST /s/answer (JSON) — autosave one answer.
     */
    public function answer(Request $request): JsonResponse
    {
        $draft = $this->draft($request);

        if ($draft === null) {
            return response()->json(['state' => 'unavailable'], Response::HTTP_NOT_FOUND);
        }

        $validator = Validator::make($request->all(), [
            'question_id' => ['required', 'integer'],
            'value' => ['required', 'integer', 'between:0,3'],
        ]);

        if ($validator->fails()) {
            return response()->json(['state' => 'unavailable'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($this->builtForAnotherVersion($request, $draft)) {
            return response()->json(['state' => 'reload'], Response::HTTP_CONFLICT);
        }

        try {
            $answered = $this->remoteAssessments->saveAnswer($draft, (int) $request->input('question_id'), (int) $request->input('value'));
        } catch (RemoteDraftStateException $exception) {
            return $this->stateConflict($exception);
        } catch (\InvalidArgumentException) {
            return response()->json(['state' => 'unavailable'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['state' => 'answering', 'answered' => $answered]);
    }

    /**
     * POST /s/done (JSON) — lock the answers, or list the item numbers
     * still unanswered.
     */
    public function done(Request $request): JsonResponse
    {
        $draft = $this->draft($request);

        if ($draft === null) {
            return response()->json(['state' => 'unavailable'], Response::HTTP_NOT_FOUND);
        }

        if ($this->builtForAnotherVersion($request, $draft)) {
            return response()->json(['state' => 'reload'], Response::HTTP_CONFLICT);
        }

        try {
            $missing = $this->remoteAssessments->markDone($draft);
        } catch (RemoteDraftStateException $exception) {
            return $this->stateConflict($exception);
        }

        if ($missing !== []) {
            return response()->json(['state' => 'answering', 'missing' => $missing], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Locking can extend the draft's expiry; the cookie follows it so the
        // device still works if the Psychometrician returns it for editing.
        $response = response()->json(['state' => 'locked']);
        $locked = $draft->fresh();

        return $locked === null ? $response : $response->withCookie($this->deviceCookie($request, (string) $request->cookie(RemoteAssessmentService::DEVICE_COOKIE), $locked));
    }

    /**
     * GET /s/state (JSON) — the draft's state and nothing else. With
     * `?version=` (the questionnaire page sends the version it was built
     * for), `reload` when the draft has since been restarted on another.
     */
    public function state(Request $request): JsonResponse
    {
        $state = $this->remoteAssessments->stateFor($this->draft($request), $request->query('version'));

        return response()->json(['state' => $state], $state === 'unavailable' ? Response::HTTP_NOT_FOUND : Response::HTTP_OK);
    }

    /**
     * The questionnaire page sends the version it was built for; answers
     * for another version (after "Restart on the new version") are refused
     * with `reload` so the page reloads with the new questions.
     */
    private function builtForAnotherVersion(Request $request, RemoteAssessmentDraft $draft): bool
    {
        $version = $request->input('version');

        return $version !== null && (string) $version !== (string) $draft->questionnaire_version_id;
    }

    private function draft(Request $request): ?RemoteAssessmentDraft
    {
        $deviceSecret = $request->cookie(RemoteAssessmentService::DEVICE_COOKIE);

        return $this->remoteAssessments->deviceDraft(is_string($deviceSecret) ? $deviceSecret : null);
    }

    /**
     * After a claim attempt: the questionnaire with the new device cookie,
     * or the generic message.
     */
    private function claimed(Request $request, ?string $deviceSecret): Response
    {
        $draft = $deviceSecret === null ? null : $this->remoteAssessments->deviceDraft($deviceSecret);

        if ($draft === null) {
            return $this->failedEntry($request);
        }

        return $this->toQuestionnaire()->withCookie($this->deviceCookie($request, $deviceSecret, $draft));
    }

    /**
     * The per-IP limit on FAILED code/link attempts (invalid, expired or
     * already used). Successful claims never count, so a whole class
     * behind one school NAT can claim their devices.
     */
    private function entryLockedOut(Request $request): ?Response
    {
        $key = $this->failedEntryKey($request);

        if (! RateLimiter::tooManyAttempts($key, (int) config('remote_assessment.limits.failed_entry_per_ip'))) {
            return null;
        }

        return StudentDeviceResponse::unavailable($request, Response::HTTP_TOO_MANY_REQUESTS, [
            'Retry-After' => (string) RateLimiter::availableIn($key),
        ]);
    }

    private function failedEntry(Request $request): Response
    {
        RateLimiter::hit($this->failedEntryKey($request), 60);

        return StudentDeviceResponse::unavailable($request);
    }

    private function failedEntryKey(Request $request): string
    {
        return 'student-device-failed-entry|'.$request->ip();
    }

    /**
     * The one page while the details are awaited: the open details form,
     * then the questions, locked (`<fieldset disabled>`; the server refuses
     * answers until the details are saved anyway). The form's lists come
     * from the lookup tables only (Active courses, year levels and sections —
     * the same queries as Step 1), never from a student record. `$old` and
     * `$errors` are only ever the input this device just sent.
     *
     * @param  array<string, mixed>  $old
     */
    private function detailsAndQuestionnaire(RemoteAssessmentDraft $draft, array $old = [], ?MessageBag $errors = null, int $status = Response::HTTP_OK): Response
    {
        return response()->view('student-device.questionnaire', [
            ...$this->questionnaireData($draft),
            'details' => 'open',
            'courses' => $this->assessmentService->activeCourses(),
            'yearLevels' => $this->assessmentService->activeYearLevels(),
            'sections' => $this->assessmentService->activeSections(),
            'old' => array_map(fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $old),
            'fieldErrors' => $errors ?? new MessageBag,
        ], $status);
    }

    /**
     * `details`: null when the Psychometrician typed Step 1 (no details
     * section at all), `saved` once the student's details are in — the
     * page then says only "Details saved", never the values, so a reload,
     * New code, Return to student or a released hold never shows them.
     *
     * @return array<string, mixed>
     */
    private function questionnaireData(RemoteAssessmentDraft $draft): array
    {
        $questions = $draft->questionnaireVersion->questions;
        $responses = $draft->responses ?? [];

        return [
            'version' => $draft->questionnaire_version_id,
            'questions' => $questions,
            'responses' => $responses,
            'answered' => $questions->filter(fn ($question): bool => array_key_exists($question->id, $responses))->count(),
            'required' => $questions->where('is_required', true)->count(),
            'details' => $draft->collects_identity ? 'saved' : null,
        ];
    }

    private function stateConflict(RemoteDraftStateException $exception): JsonResponse
    {
        return response()->json(
            ['state' => $exception->state],
            $exception->state === 'unavailable' ? Response::HTTP_NOT_FOUND : Response::HTTP_CONFLICT,
        );
    }

    private function toQuestionnaire(): RedirectResponse
    {
        return redirect()->route('student-device.show', status: Response::HTTP_SEE_OTHER);
    }

    /**
     * After the details form: the same page, scrolled to (and focusing) the
     * questions. One Location for every outcome; a held page has no
     * #questions, so the fragment is simply ignored there.
     */
    private function toQuestions(): RedirectResponse
    {
        return redirect()->to(route('student-device.show').'#questions', Response::HTTP_SEE_OTHER);
    }

    private function unavailableAndForgetDevice(Request $request): Response
    {
        $response = StudentDeviceResponse::unavailable($request);

        if ($request->cookies->has(RemoteAssessmentService::DEVICE_COOKIE)) {
            $response->headers->setCookie($this->forgetDeviceCookie($request));
        }

        return $response;
    }

    /**
     * HttpOnly, SameSite=Strict, limited to /s, Secure over HTTPS, and
     * expiring with the draft.
     */
    private function deviceCookie(Request $request, string $deviceSecret, RemoteAssessmentDraft $draft): Cookie
    {
        return new Cookie(
            RemoteAssessmentService::DEVICE_COOKIE,
            $deviceSecret,
            $draft->expires_at,
            '/'.StudentDeviceResponse::PATH_PREFIX,
            null,
            $request->isSecure(),
            true,
            false,
            Cookie::SAMESITE_STRICT,
        );
    }

    private function forgetDeviceCookie(Request $request): Cookie
    {
        return new Cookie(
            RemoteAssessmentService::DEVICE_COOKIE,
            null,
            1,
            '/'.StudentDeviceResponse::PATH_PREFIX,
            null,
            $request->isSecure(),
            true,
            false,
            Cookie::SAMESITE_STRICT,
        );
    }
}
