<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\RemoteDraftStateException;
use App\Http\Responses\StudentDeviceResponse;
use App\Models\RemoteAssessmentDraft;
use App\Services\RemoteAssessmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * The student device: a session-less, login-less questionnaire reached by
 * a typed short code or a QR link (routes/student-device.php). It only
 * ever shows the privacy notice (when enabled), the instructions and
 * rating scale, the questions and answer buttons, a progress counter, a
 * save status line, Done, and the thank-you message — or the generic
 * "not available" message. It never reads the wizard session or any
 * student record; the draft it works on holds none.
 */
class StudentDeviceController extends Controller
{
    public function __construct(private readonly RemoteAssessmentService $remoteAssessments) {}

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
     * GET /s/t/{token} — the "Begin" page. Opening the link claims
     * nothing, so a link previewer or scanner can't use up the token.
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

        return response()->view('student-device.begin', ['token' => $token]);
    }

    /**
     * POST /s/t/{token} — claim a draft by QR token.
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
     * questionnaire, or the thank-you message once locked.
     */
    public function show(Request $request): Response
    {
        $draft = $this->draft($request);

        if ($draft === null) {
            return $this->unavailableAndForgetDevice($request);
        }

        return match ($this->remoteAssessments->stateOf($draft)) {
            'consent' => response()->view('student-device.consent'),
            'answering' => response()->view('student-device.questionnaire', $this->questionnaireData($draft)),
            'locked' => response()->view('student-device.thanks'),
            default => $this->unavailableAndForgetDevice($request),
        };
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
     * The per-IP limit on FAILED code/token attempts (invalid, expired or
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
