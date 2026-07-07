<?php

declare(strict_types=1);

namespace App\Models;

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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'depression_raw_score' => 'integer',
            'anxiety_raw_score' => 'integer',
            'stress_raw_score' => 'integer',
            'depression_final_score' => 'integer',
            'anxiety_final_score' => 'integer',
            'stress_final_score' => 'integer',
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
