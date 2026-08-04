<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedInteger;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DassResponse extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'assessment_id',
        'dass_question_id',
        'answer_value',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * `answer_value` is encrypted at rest (AES-256, via `EncryptedInteger`,
     * a thin wrapper around Laravel's `Crypt` facade that decrypts back to
     * an `int` rather than the string Laravel's built-in `encrypted` cast
     * would produce — this codebase's `declare(strict_types=1)`
     * convention needs the original type preserved) — it's the raw
     * DASS-21 answer to an individual question, the most granular piece
     * of clinical data in the system.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answer_value' => EncryptedInteger::class,
        ];
    }

    /**
     * Get the assessment this response belongs to.
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * Get the question this response answers.
     *
     * Includes soft-deleted (archived) questions — a submitted response
     * must remain fully readable (Assessment Result, Reports) even if the
     * question is later removed from the live questionnaire.
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(DassQuestion::class, 'dass_question_id')->withTrashed();
    }
}
