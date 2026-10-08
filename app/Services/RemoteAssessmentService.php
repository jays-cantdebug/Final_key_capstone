<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RemoteDraftStateException;
use App\Models\QuestionnaireVersion;
use App\Models\RemoteAssessmentDraft;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Drafts for a questionnaire answered on a separate student device: a PC
 * provided by the guidance office, where the student types a short code or
 * opens a link the Psychometrician copied from the live page.
 *
 * Credentials: an 8-character short code (Crockford base32, about 40 bits)
 * and a 256-bit link token, both handed out once and stored only as
 * HMAC-SHA256 digests keyed by APP_KEY, so a leaked table can't be brute-
 * forced offline. Using either one claims the draft for one device:
 * an atomic update binds a fresh random device secret (kept in an HttpOnly
 * cookie on that device, stored here as a digest), and from then on every
 * other device — by code or by link — is refused and counted in
 * `refused_device_attempts`. Opening the link only shows the Begin page;
 * only its POST claims, so a link preview or scanner can't use it up.
 * (The link token was removed and restored on 2026-10-08; see the
 * 130000/140000 token_hash migrations. There is no QR code.)
 *
 * Every lookup checks `expires_at` itself rather than relying on pruning.
 */
class RemoteAssessmentService
{
    public const DEVICE_COOKIE = 'normi_device';

    /** Crockford base32: no I, L, O or U. */
    private const CODE_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const CODE_LENGTH = 8;

    public function __construct(private readonly DatabaseManager $database) {}

    /**
     * Start a draft for this Psychometrician on `$version`, replacing any
     * draft they already had. Returns the plain link token and short code;
     * only their digests are stored.
     *
     * `$collectsIdentity`: the student also types their own Step 1 details
     * on the device. The privacy notice is then always shown first,
     * whatever `remote_assessment.student_consent` says, so no detail is
     * accepted before the student acknowledges it.
     *
     * @return array{draft: RemoteAssessmentDraft, token: string, short_code: string}
     */
    public function create(User $psychometrician, QuestionnaireVersion $version, bool $collectsIdentity = false): array
    {
        $this->pruneExpired();

        for ($attempt = 1; ; $attempt++) {
            $token = $this->randomToken();
            $shortCode = $this->randomShortCode();

            try {
                $draft = $this->database->transaction(function () use ($psychometrician, $version, $token, $shortCode, $collectsIdentity): RemoteAssessmentDraft {
                    RemoteAssessmentDraft::query()->where('psychometrician_id', $psychometrician->id)->delete();

                    return RemoteAssessmentDraft::query()->create([
                        'psychometrician_id' => $psychometrician->id,
                        'questionnaire_version_id' => $version->id,
                        'token_hash' => $this->hash('token', $token),
                        'short_code_hash' => $this->hash('code', $shortCode),
                        'status' => RemoteAssessmentDraft::STATUS_PENDING,
                        'requires_consent' => $collectsIdentity || (bool) config('remote_assessment.student_consent'),
                        'collects_identity' => $collectsIdentity,
                        'expires_at' => now()->addMinutes((int) config('remote_assessment.ttl_minutes')),
                    ]);
                });

                return ['draft' => $draft, 'token' => $token, 'short_code' => $this->formatShortCode($shortCode)];
            } catch (UniqueConstraintViolationException $exception) {
                // A short-code collision with another live draft; try again.
                if ($attempt >= 5) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * The unclaimed, unexpired draft for a link token, for the "Begin" page.
     */
    public function pendingDraftForToken(string $token): ?RemoteAssessmentDraft
    {
        return RemoteAssessmentDraft::query()
            ->where('token_hash', $this->hash('token', $token))
            ->where('status', RemoteAssessmentDraft::STATUS_PENDING)
            ->whereNull('device_hash')
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Claim by link token (the Begin page's POST). Returns the device secret
     * for the cookie, or null when the token is invalid, expired, or already
     * claimed (by link or by code).
     */
    public function claimWithToken(string $token): ?string
    {
        return $this->claim('token_hash', $this->hash('token', $token));
    }

    /**
     * Claim by typed short code (normalized first; see normalizeShortCode()).
     * Returns the device secret for the cookie, or null when the code is
     * invalid, expired, or already claimed (by code or by link).
     */
    public function claimWithShortCode(string $input): ?string
    {
        $code = $this->normalizeShortCode($input);

        return $code === null ? null : $this->claim('short_code_hash', $this->hash('code', $code));
    }

    /**
     * The live draft this device secret is bound to (answering or locked,
     * not expired), with `last_seen_at` refreshed. Null for anything else.
     */
    public function deviceDraft(?string $deviceSecret): ?RemoteAssessmentDraft
    {
        if ($deviceSecret === null || $deviceSecret === '') {
            return null;
        }

        $draft = RemoteAssessmentDraft::query()
            ->where('device_hash', $this->hash('device', $deviceSecret))
            ->whereIn('status', [RemoteAssessmentDraft::STATUS_ANSWERING, RemoteAssessmentDraft::STATUS_LOCKED])
            ->where('expires_at', '>', now())
            ->first();

        if ($draft !== null) {
            // A heartbeat, not a change: no revision or updated_at bump.
            $draft->newQuery()->whereKey($draft->getKey())->toBase()->update(['last_seen_at' => now()]);
        }

        return $draft;
    }

    /**
     * Whether a link token belongs to the draft this device already holds
     * (reopening the link on the same device resumes instead of refusing).
     */
    public function tokenBelongsTo(RemoteAssessmentDraft $draft, string $token): bool
    {
        return $draft->token_hash !== null && hash_equals($draft->token_hash, $this->hash('token', $token));
    }

    /**
     * Record the student's own acknowledgment of the privacy notice.
     *
     * @throws RemoteDraftStateException when the draft isn't waiting for it.
     */
    public function consent(RemoteAssessmentDraft $draft): void
    {
        $this->database->transaction(function () use ($draft): void {
            $fresh = $this->lockedFresh($draft);

            if ($fresh === null || ! $fresh->awaitingConsent()) {
                throw new RemoteDraftStateException($this->stateOf($fresh));
            }

            $fresh->consented_at = now();
            $fresh->revision++;
            $fresh->save();
        });
    }

    /**
     * The student declined the privacy notice: strip the draft of its
     * credentials and any answers, keeping only the `declined` status so
     * the Psychometrician's page can say so. Pruned like any other draft.
     *
     * @throws RemoteDraftStateException when the draft isn't waiting for consent.
     */
    public function decline(RemoteAssessmentDraft $draft): void
    {
        $this->database->transaction(function () use ($draft): void {
            $fresh = $this->lockedFresh($draft);

            if ($fresh === null || ! $fresh->awaitingConsent()) {
                throw new RemoteDraftStateException($this->stateOf($fresh));
            }

            $fresh->forceFill([
                'status' => RemoteAssessmentDraft::STATUS_DECLINED,
                'token_hash' => null,
                'short_code_hash' => null,
                'device_hash' => null,
                'responses' => null,
                // None can exist before consent; cleared all the same.
                'identity' => null,
                'identity_submitted_at' => null,
                'identity_corrected_at' => null,
                'held_at' => null,
                'revision' => $fresh->revision + 1,
            ])->save();
        });
    }

    /**
     * The student's own Step 1 details, already validated, accepted once per
     * draft and only after the privacy notice. `$held` (the caller found an
     * active student with this name) stops the device at the generic
     * message. The same single update runs either way, so the reply can't
     * differ.
     *
     * @param  array<string, mixed>  $identity
     *
     * @throws RemoteDraftStateException when the draft isn't waiting for them.
     */
    public function saveIdentity(RemoteAssessmentDraft $draft, array $identity, bool $held): void
    {
        $this->database->transaction(function () use ($draft, $identity, $held): void {
            $fresh = $this->lockedFresh($draft);

            if ($fresh === null || ! $fresh->awaitingIdentity()) {
                throw new RemoteDraftStateException($this->stateOf($fresh));
            }

            $fresh->forceFill([
                'identity' => $identity,
                'identity_submitted_at' => now(),
                'held_at' => $held ? now() : null,
                'revision' => $fresh->revision + 1,
            ])->save();
        });
    }

    /**
     * The Psychometrician's correction of the details the student typed
     * (answers are never touched). A held device continues once
     * `$matchesActiveStudent` is false. Returns whether it was released.
     *
     * @param  array<string, mixed>  $identity
     *
     * @throws RemoteDraftStateException when there are no details to correct yet.
     */
    public function correctIdentity(RemoteAssessmentDraft $draft, array $identity, bool $matchesActiveStudent): bool
    {
        return $this->database->transaction(function () use ($draft, $identity, $matchesActiveStudent): bool {
            $fresh = $this->lockedFresh($draft);

            if ($fresh === null || ! $fresh->collects_identity || $fresh->identity_submitted_at === null
                || $fresh->status === RemoteAssessmentDraft::STATUS_DECLINED) {
                throw new RemoteDraftStateException('unavailable');
            }

            $released = $fresh->isHeld() && ! $matchesActiveStudent;

            $fresh->forceFill([
                'identity' => $identity,
                'identity_corrected_at' => now(),
                'held_at' => $released ? null : $fresh->held_at,
                'revision' => $fresh->revision + 1,
            ])->save();

            return $released;
        });
    }

    /**
     * Autosave one answer. `$questionId` must be a question of the draft's
     * pinned version and `$value` 0-3 (checked by the caller's validation
     * too). Returns how many questions are now answered.
     *
     * @throws RemoteDraftStateException when the draft can't take answers
     *                                   (consent pending, locked, gone).
     * @throws \InvalidArgumentException for a question outside the version.
     */
    public function saveAnswer(RemoteAssessmentDraft $draft, int $questionId, int $value): int
    {
        if ($value < 0 || $value > 3) {
            throw new \InvalidArgumentException('Answer out of range.');
        }

        return $this->database->transaction(function () use ($draft, $questionId, $value): int {
            $fresh = $this->lockedFresh($draft);

            if ($fresh === null || ! $this->takesAnswers($fresh)) {
                throw new RemoteDraftStateException($this->stateOf($fresh));
            }

            if (! $fresh->questionnaireVersion->questions->contains('id', $questionId)) {
                throw new \InvalidArgumentException('Unknown question.');
            }

            $responses = $fresh->responses ?? [];
            $responses[$questionId] = $value;

            $fresh->responses = $responses;
            $fresh->revision++;
            $fresh->save();

            return count($responses);
        });
    }

    /**
     * The student pressed Done. Locks the draft when every required
     * question is answered — extending `expires_at` to at least now +
     * `locked_grace_minutes` — otherwise returns the missing item numbers
     * and changes nothing.
     *
     * @return array<int, int> Missing item numbers; empty once locked.
     *
     * @throws RemoteDraftStateException when the draft isn't being answered.
     */
    public function markDone(RemoteAssessmentDraft $draft): array
    {
        return $this->database->transaction(function () use ($draft): array {
            $fresh = $this->lockedFresh($draft);

            if ($fresh === null || ! $this->takesAnswers($fresh)) {
                throw new RemoteDraftStateException($this->stateOf($fresh));
            }

            $missing = $this->missingItemNumbers($fresh);

            if ($missing !== []) {
                return $missing;
            }

            $fresh->status = RemoteAssessmentDraft::STATUS_LOCKED;
            $fresh->locked_at = now();
            // Time for the Psychometrician to review and submit; never
            // shortens a longer remaining expiry.
            $graceExpiry = now()->addMinutes((int) config('remote_assessment.locked_grace_minutes'));
            if ($graceExpiry->greaterThan($fresh->expires_at)) {
                $fresh->expires_at = $graceExpiry;
            }
            $fresh->revision++;
            $fresh->save();

            return [];
        });
    }

    /**
     * Item numbers of required questions with no answer yet.
     *
     * @return array<int, int>
     */
    public function missingItemNumbers(RemoteAssessmentDraft $draft): array
    {
        $responses = $draft->responses ?? [];

        return $draft->questionnaireVersion->questions
            ->filter(fn ($question): bool => $question->is_required && ! array_key_exists($question->id, $responses))
            ->pluck('item_number')
            ->map(fn ($itemNumber): int => (int) $itemNumber)
            ->values()
            ->all();
    }

    /**
     * The state a student device is told about: `consent`, `identity`
     * (its own details), `answering`, `help` (held: the generic message),
     * `locked`, or `unavailable` for anything else (missing, expired,
     * declined). Never more than that — in particular never why a draft is
     * held.
     */
    public function stateOf(?RemoteAssessmentDraft $draft): string
    {
        if ($draft === null || $draft->expires_at->isPast()) {
            return 'unavailable';
        }

        return match (true) {
            $draft->awaitingConsent() => 'consent',
            $draft->awaitingIdentity() => 'identity',
            $draft->status === RemoteAssessmentDraft::STATUS_ANSWERING && $draft->isHeld() => 'help',
            $draft->status === RemoteAssessmentDraft::STATUS_ANSWERING => 'answering',
            $draft->status === RemoteAssessmentDraft::STATUS_LOCKED => 'locked',
            default => 'unavailable',
        };
    }

    /**
     * The Psychometrician's own draft by id, with its version's questions;
     * null for someone else's draft or one that doesn't exist. Expired
     * drafts are returned (the live page says so) until pruned.
     */
    public function ownedDraft(User $psychometrician, mixed $draftId): ?RemoteAssessmentDraft
    {
        if (! is_int($draftId) && ! (is_string($draftId) && ctype_digit($draftId))) {
            return null;
        }

        return RemoteAssessmentDraft::query()
            ->whereKey((int) $draftId)
            ->where('psychometrician_id', $psychometrician->getKey())
            ->with('questionnaireVersion.questions')
            ->first();
    }

    /**
     * "New code": a fresh link token and short code, the device unbound (its
     * cookie stops working; the old link and code too), answers, consent and
     * the original expiry kept. The next device to use the new code or link
     * continues where the old one was.
     *
     * @return array{token: string, short_code: string}
     *
     * @throws RemoteDraftStateException when the draft was declined or has expired.
     */
    public function reissue(RemoteAssessmentDraft $draft): array
    {
        for ($attempt = 1; ; $attempt++) {
            $token = $this->randomToken();
            $shortCode = $this->randomShortCode();

            try {
                $this->database->transaction(function () use ($draft, $token, $shortCode): void {
                    $fresh = $this->lockedFresh($draft);

                    if ($fresh === null || $fresh->status === RemoteAssessmentDraft::STATUS_DECLINED) {
                        throw new RemoteDraftStateException('unavailable');
                    }

                    $fresh->forceFill([
                        'token_hash' => $this->hash('token', $token),
                        'short_code_hash' => $this->hash('code', $shortCode),
                        'device_hash' => null,
                        'status' => RemoteAssessmentDraft::STATUS_PENDING,
                        'claimed_at' => null,
                        'locked_at' => null,
                        'last_seen_at' => null,
                        'refused_device_attempts' => 0,
                        'revision' => $fresh->revision + 1,
                    ])->save();
                });

                return ['token' => $token, 'short_code' => $this->formatShortCode($shortCode)];
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= 5) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * "Return to student": unlock a locked draft so the student can edit
     * again. False when the draft isn't locked.
     */
    public function returnToStudent(RemoteAssessmentDraft $draft): bool
    {
        return $this->database->transaction(function () use ($draft): bool {
            $fresh = $this->lockedFresh($draft);

            if ($fresh === null || $fresh->status !== RemoteAssessmentDraft::STATUS_LOCKED) {
                return false;
            }

            $fresh->forceFill([
                'status' => RemoteAssessmentDraft::STATUS_ANSWERING,
                'locked_at' => null,
                'revision' => $fresh->revision + 1,
            ])->save();

            return true;
        });
    }

    /**
     * "Restart on the new version": re-pin the draft to `$version` and clear
     * the answers, which belong to the old version's questions. A bound
     * device keeps its binding and is told to reload (see stateFor()).
     */
    public function restartOn(RemoteAssessmentDraft $draft, QuestionnaireVersion $version): bool
    {
        return $this->database->transaction(function () use ($draft, $version): bool {
            $fresh = $this->lockedFresh($draft);

            if ($fresh === null || $fresh->status === RemoteAssessmentDraft::STATUS_DECLINED) {
                return false;
            }

            $fresh->forceFill([
                'questionnaire_version_id' => $version->id,
                'responses' => null,
                'status' => $fresh->status === RemoteAssessmentDraft::STATUS_LOCKED ? RemoteAssessmentDraft::STATUS_ANSWERING : $fresh->status,
                'locked_at' => null,
                'revision' => $fresh->revision + 1,
            ])->save();

            return true;
        });
    }

    /**
     * Delete every draft this Psychometrician has (at most one): wizard
     * exits, Force Logout and deactivation.
     */
    public function discardFor(User $psychometrician): void
    {
        RemoteAssessmentDraft::query()->where('psychometrician_id', $psychometrician->getKey())->delete();
    }

    /**
     * What the Psychometrician's live page shows: pending, consent,
     * identity (waiting for the student's details), held (the details
     * match an active student), answering, locked, declined or expired.
     */
    public function monitorState(RemoteAssessmentDraft $draft): string
    {
        if ($draft->expires_at->isPast()) {
            return 'expired';
        }

        return match (true) {
            $draft->status === RemoteAssessmentDraft::STATUS_PENDING => 'pending',
            $draft->status === RemoteAssessmentDraft::STATUS_DECLINED => 'declined',
            $draft->awaitingConsent() => 'consent',
            $draft->awaitingIdentity() => 'identity',
            $draft->status === RemoteAssessmentDraft::STATUS_ANSWERING && $draft->isHeld() => 'held',
            default => $draft->status,
        };
    }

    /**
     * The live page's polling data. When `$sinceRevision` equals the
     * draft's revision (and it hasn't expired), only the few fields that
     * change without a revision bump are sent.
     *
     * @return array<string, mixed>
     */
    public function monitorData(RemoteAssessmentDraft $draft, ?QuestionnaireVersion $activeVersion, ?int $sinceRevision = null): array
    {
        $state = $this->monitorState($draft);
        $volatile = [
            'rev' => $draft->revision,
            'last_seen_seconds' => $draft->last_seen_at === null ? null : (int) $draft->last_seen_at->diffInSeconds(now(), true),
            'seconds_left' => max(0, (int) now()->diffInSeconds($draft->expires_at, false)),
            'version_changed' => $activeVersion?->id !== $draft->questionnaire_version_id,
        ];

        if ($sinceRevision === $draft->revision && $state !== 'expired') {
            return [...$volatile, 'unchanged' => true];
        }

        $responses = $state === 'expired' ? [] : ($draft->responses ?? []);

        return [
            ...$volatile,
            'unchanged' => false,
            'state' => $state,
            'consent_required' => $draft->requires_consent,
            'consented' => $draft->consented_at !== null,
            'claimed' => $draft->device_hash !== null,
            'refused_device_attempts' => $draft->refused_device_attempts,
            // Flags only: the details themselves are rendered by the page
            // (it reloads when these change), never sent in the poll.
            'collects_identity' => $draft->collects_identity,
            'identity_received' => $draft->identity_submitted_at !== null,
            'held' => $draft->isHeld(),
            'answers' => (object) $responses,
            'answered' => count($responses),
            'total' => $draft->questionnaireVersion->questions->count(),
        ];
    }

    /**
     * The address the student device uses (REMOTE_ASSESSMENT_URL, falling
     * back to APP_URL), without a trailing slash.
     */
    public function baseUrl(): string
    {
        return rtrim((string) config('remote_assessment.url'), '/');
    }

    public function studentEntryUrl(): string
    {
        return $this->baseUrl().'/s';
    }

    /**
     * The link with the token, for the live page's Copy link button only:
     * never rendered as text or as a link, never sent to the student device
     * in any page, never logged by NORMI.
     */
    public function studentTokenUrl(string $token): string
    {
        return $this->baseUrl().'/s/t/'.$token;
    }

    /**
     * Whether the student address points at a loopback address (127.x,
     * localhost, ::1), which a separate student PC can't reach.
     */
    public function baseUrlIsLoopback(): bool
    {
        $host = strtolower(trim((string) parse_url($this->baseUrl(), PHP_URL_HOST), '[]'));

        return $host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')
            || $host === '::1' || $host === '0.0.0.0' || str_starts_with($host, '127.');
    }

    /**
     * The device-facing state, or `reload` when the device's page was built
     * for another questionnaire version than the draft now has (after
     * "Restart on the new version").
     */
    public function stateFor(?RemoteAssessmentDraft $draft, mixed $pageVersion): string
    {
        $state = $this->stateOf($draft);

        if ($draft !== null && in_array($state, ['answering', 'locked'], true)
            && $pageVersion !== null && (string) $pageVersion !== (string) $draft->questionnaire_version_id) {
            return 'reload';
        }

        return $state;
    }

    /**
     * Delete every expired draft (the same rows `model:prune` removes).
     */
    public function pruneExpired(): int
    {
        return RemoteAssessmentDraft::query()->where('expires_at', '<=', now())->delete();
    }

    /**
     * Uppercase, drop spaces and dashes, and read the letters people
     * mistype for digits (O → 0, I/L → 1). Null unless the result is a
     * well-formed code.
     */
    public function normalizeShortCode(string $input): ?string
    {
        $code = strtr(strtoupper(preg_replace('/[\s-]+/', '', $input) ?? ''), ['O' => '0', 'I' => '1', 'L' => '1']);

        return preg_match('/^['.self::CODE_ALPHABET.']{'.self::CODE_LENGTH.'}$/', $code) === 1 ? $code : null;
    }

    public function formatShortCode(string $code): string
    {
        return substr($code, 0, 4).'-'.substr($code, 4);
    }

    /**
     * HMAC-SHA256 keyed by APP_KEY, with a purpose prefix so a link token,
     * a code and a device secret never share a digest space.
     */
    public function hash(string $purpose, string $value): string
    {
        return hash_hmac('sha256', $purpose.'|'.$value, (string) config('app.key'));
    }

    private function claim(string $column, string $hash): ?string
    {
        $deviceSecret = $this->randomToken();
        $now = now();

        // Atomic: of two devices racing for one credential, exactly one
        // update matches the `device_hash IS NULL` condition.
        $claimed = RemoteAssessmentDraft::query()
            ->where($column, $hash)
            ->where('status', RemoteAssessmentDraft::STATUS_PENDING)
            ->whereNull('device_hash')
            ->where('expires_at', '>', $now)
            ->toBase()
            ->update([
                'device_hash' => $this->hash('device', $deviceSecret),
                'status' => RemoteAssessmentDraft::STATUS_ANSWERING,
                'claimed_at' => $now,
                'last_seen_at' => $now,
                'revision' => DB::raw('revision + 1'),
                'updated_at' => $now,
            ]);

        if ($claimed !== 1) {
            RemoteAssessmentDraft::query()
                ->where($column, $hash)
                ->whereNotNull('device_hash')
                ->where('expires_at', '>', $now)
                ->toBase()
                ->increment('refused_device_attempts', 1, ['revision' => DB::raw('revision + 1')]);

            return null;
        }

        return $deviceSecret;
    }

    /**
     * Answering, past the privacy notice and the details, and not held.
     */
    private function takesAnswers(RemoteAssessmentDraft $draft): bool
    {
        return $draft->status === RemoteAssessmentDraft::STATUS_ANSWERING
            && ! $draft->awaitingConsent() && ! $draft->awaitingIdentity() && ! $draft->isHeld();
    }

    private function lockedFresh(RemoteAssessmentDraft $draft): ?RemoteAssessmentDraft
    {
        $fresh = RemoteAssessmentDraft::query()->whereKey($draft->getKey())->lockForUpdate()->first();

        return $fresh !== null && $fresh->expires_at->isFuture() ? $fresh : null;
    }

    private function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function randomShortCode(): string
    {
        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        return $code;
    }
}
