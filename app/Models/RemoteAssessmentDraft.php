<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A questionnaire being answered on a separate student device.
 * Deliberately not audited (not registered with AuditableObserver), so an
 * abandoned draft leaves no trace once it is deleted or pruned.
 *
 * Student data: none when the Psychometrician typed Step 1 (the details
 * stay in their wizard session). With `collects_identity` the student
 * types their own Step 1 details on the device, after the privacy notice
 * (always required then); they are kept only in `identity`, encrypted,
 * until the Psychometrician submits.
 *
 * Status: `pending` (code not used yet) → `answering` (claimed by one
 * device; waiting for consent first when `requires_consent` and no
 * `consented_at`, then for the details when `collects_identity` and no
 * `identity_submitted_at`) → `locked` (the student pressed Done).
 * `held_at` is set when the typed name matches an active student; the
 * device then shows only the generic message until a correction clears
 * it. `declined` is a stripped row (no credentials, no answers, no
 * details) kept only so the Psychometrician can be told; it is pruned like
 * any other.
 *
 * @property array<int, int>|null $responses
 * @property array<string, mixed>|null $identity
 */
class RemoteAssessmentDraft extends Model
{
    use MassPrunable;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ANSWERING = 'answering';

    public const STATUS_LOCKED = 'locked';

    public const STATUS_DECLINED = 'declined';

    protected $fillable = [
        'psychometrician_id',
        'questionnaire_version_id',
        'token_hash',
        'short_code_hash',
        'device_hash',
        'status',
        'requires_consent',
        'collects_identity',
        'consented_at',
        'identity',
        'identity_submitted_at',
        'identity_corrected_at',
        'held_at',
        'responses',
        'revision',
        'refused_device_attempts',
        'claimed_at',
        'locked_at',
        'last_seen_at',
        'expires_at',
    ];

    protected $hidden = [
        'token_hash',
        'short_code_hash',
        'device_hash',
        'identity',
        'responses',
    ];

    protected function casts(): array
    {
        return [
            'requires_consent' => 'boolean',
            'collects_identity' => 'boolean',
            'consented_at' => 'datetime',
            'identity' => 'encrypted:array',
            'identity_submitted_at' => 'datetime',
            'identity_corrected_at' => 'datetime',
            'held_at' => 'datetime',
            // Same primitive as the other encrypted columns (APP_KEY,
            // AES-256-CBC + HMAC).
            'responses' => 'encrypted:array',
            'revision' => 'integer',
            'refused_device_attempts' => 'integer',
            'claimed_at' => 'datetime',
            'locked_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function psychometrician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'psychometrician_id');
    }

    public function questionnaireVersion(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireVersion::class);
    }

    /**
     * Expired drafts, removed by `model:prune` (scheduled in
     * routes/console.php) and lazily by RemoteAssessmentService.
     */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<=', now());
    }

    public function awaitingConsent(): bool
    {
        return $this->status === self::STATUS_ANSWERING && $this->requires_consent && $this->consented_at === null;
    }

    /**
     * Past the privacy notice, waiting for the student's own details.
     */
    public function awaitingIdentity(): bool
    {
        return $this->status === self::STATUS_ANSWERING && $this->collects_identity
            && ! $this->awaitingConsent() && $this->identity_submitted_at === null;
    }

    public function isHeld(): bool
    {
        return $this->held_at !== null;
    }
}
