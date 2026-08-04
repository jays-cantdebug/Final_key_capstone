<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CounselingSession extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_SCHEDULED = 'Scheduled';

    public const STATUS_COMPLETED = 'Completed';

    public const STATUS_CANCELLED = 'Cancelled';

    public const STATUS_NO_SHOW = 'No-Show';

    public const CONFIDENTIALITY_STANDARD = 'Standard';

    public const CONFIDENTIALITY_RESTRICTED = 'Restricted';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'student_id',
        'assessment_id',
        'counselor_id',
        'session_datetime',
        'session_notes',
        'session_status',
        'follow_up_required',
        'follow_up_date',
        'confidentiality_level',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * `session_notes` is encrypted at rest (AES-256, via Laravel's
     * `encrypted` cast) — free-text clinical notes, never searched or
     * filtered by content (only the related student's name is searched).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'session_datetime' => 'datetime',
            'session_notes' => 'encrypted',
            'follow_up_required' => 'boolean',
            'follow_up_date' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the student this session was conducted with.
     *
     * Includes archived (soft-deleted) students — a student being
     * archived after a counseling session was logged must not break that
     * session's historical record.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /**
     * Get the assessment this session stems from, if any.
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * Get the counselor who conducted this session.
     */
    public function counselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }

    /**
     * Determine whether this session's notes are hidden from the given
     * user: true when the session is Restricted and the user is not the
     * counselor who created it.
     */
    public function isRestrictedFor(User $user): bool
    {
        return $this->confidentiality_level === self::CONFIDENTIALITY_RESTRICTED
            && $this->counselor_id !== $user->id;
    }
}
