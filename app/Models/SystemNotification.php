<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemNotification extends Model
{
    use HasFactory;

    public const TYPE_COUNSELING_ENDORSEMENT = 'counseling_endorsement';

    public const TYPE_AWARENESS_NOTIFICATION = 'awareness_notification';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'assessment_id',
        'flagged_case_id',
        'notification_type',
        'title',
        'message',
        'is_read',
        'read_at',
        'archived_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * Scope a query to notifications that have not been archived.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /**
     * Scope a query to notifications that have been archived.
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    /**
     * Get the user this notification was sent to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the assessment this notification concerns.
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * Get the flagged case this notification was generated for.
     */
    public function flaggedCase(): BelongsTo
    {
        return $this->belongsTo(FlaggedCase::class);
    }
}
