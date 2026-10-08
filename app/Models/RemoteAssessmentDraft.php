<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A questionnaire being answered on a separate student device. Holds no
 * student data: the student's details stay in the Psychometrician's wizard
 * session. Deliberately not audited (not registered with
 * AuditableObserver), so an abandoned draft leaves no trace once it is
 * deleted or pruned.
 *
 * Status: `pending` (code not used yet) → `answering` (claimed by one
 * device; waiting for consent first when `requires_consent` and no
 * `consented_at`) → `locked` (the student pressed Done). `declined` is a
 * stripped row (no credentials, no answers) kept only so the
 * Psychometrician can be told; it is pruned like any other.
 *
 * @property array<int, int>|null $responses
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
        'consented_at',
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
    ];

    protected function casts(): array
    {
        return [
            'requires_consent' => 'boolean',
            'consented_at' => 'datetime',
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
}
