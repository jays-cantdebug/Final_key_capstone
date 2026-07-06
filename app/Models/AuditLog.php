<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit trail entry. Audit logs are append-only: there is no
 * `updated_at` column, matching the canonical schema.
 */
class AuditLog extends Model
{
    /**
     * Audit logs are append-only; disable the `updated_at` column.
     */
    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'module',
        'action',
        'record_id',
        'ip_address',
        'user_agent',
        'old_values',
        'new_values',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the user this log entry is attributed to, if any.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
