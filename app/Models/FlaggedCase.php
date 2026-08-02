<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlaggedCase extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'Open';

    public const STATUS_RESOLVED = 'Resolved';

    public const FLAG_TYPE_COUNSELING_ENDORSEMENT = 'counseling_endorsement';

    public const FLAG_TYPE_AWARENESS_NOTIFICATION = 'awareness_notification';

    public const SUBSCALE_DEPRESSION = 'depression';

    public const SUBSCALE_ANXIETY = 'anxiety';

    public const SUBSCALE_STRESS = 'stress';

    /**
     * Severity levels that meet or exceed the differentiated-flagging
     * threshold — the same list `FlaggedCaseService::evaluateAndFlag()`
     * checks a subscale against before creating a row here.
     */
    public const SEVERE_LEVELS = [
        ClassificationThreshold::SEVERITY_SEVERE,
        ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'assessment_id',
        'assigned_to_user_id',
        'flag_type',
        'triggering_subscale',
        'status',
        'flagged_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'flagged_at' => 'datetime',
        ];
    }

    /**
     * Get the assessment this flagged case belongs to.
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * Get the user (Guidance Counselor) this case is assigned to, if any.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}
