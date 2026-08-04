<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedInteger;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DassResult extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'assessment_id',
        'depression_raw_score',
        'anxiety_raw_score',
        'stress_raw_score',
        'depression_final_score',
        'anxiety_final_score',
        'stress_final_score',
        'depression_level',
        'anxiety_level',
        'stress_level',
        'ai_provider',
        'used_non_official_thresholds',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * The six raw/final subscale scores are encrypted at rest (AES-256,
     * via `EncryptedInteger`, a thin wrapper around Laravel's `Crypt`
     * facade that decrypts back to an `int` rather than the string
     * Laravel's built-in `encrypted` cast would produce — this codebase's
     * `declare(strict_types=1)` convention, and tests that `assertSame()`
     * an exact int score, need the original type preserved) — they're
     * DASS-21 clinical output, not filtered/sorted/aggregated at the SQL
     * level anywhere in the app (severity *labels*, not these scores,
     * drive Dashboard SQL filters, so those stay unencrypted — see
     * `depression_level` etc. below, intentionally absent from this cast
     * list).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'depression_raw_score' => EncryptedInteger::class,
            'anxiety_raw_score' => EncryptedInteger::class,
            'stress_raw_score' => EncryptedInteger::class,
            'depression_final_score' => EncryptedInteger::class,
            'anxiety_final_score' => EncryptedInteger::class,
            'stress_final_score' => EncryptedInteger::class,
            'used_non_official_thresholds' => 'boolean',
        ];
    }

    /**
     * Get the assessment this result belongs to.
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * The highest severity level across the three subscales, computed on
     * the fly rather than stored — per the capstone's Assessment Result
     * design, this is a display convenience only, never a separate AI
     * output or database column.
     */
    public function highestSeverityLevel(): string
    {
        $rank = [
            ClassificationThreshold::SEVERITY_NORMAL => 0,
            ClassificationThreshold::SEVERITY_MILD => 1,
            ClassificationThreshold::SEVERITY_MODERATE => 2,
            ClassificationThreshold::SEVERITY_SEVERE => 3,
            ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE => 4,
        ];

        return collect([$this->depression_level, $this->anxiety_level, $this->stress_level])
            ->sortByDesc(fn (string $level): int => $rank[$level] ?? 0)
            ->first();
    }
}
