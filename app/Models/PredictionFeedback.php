<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Psychometrician's confirmation or correction of the AI's
 * classification for one assessment (the Feedback Loop). Corrections here
 * never retroactively alter the original dass_results row or any
 * flagged_cases/notifications already issued for that assessment.
 */
class PredictionFeedback extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'assessment_id',
        'psychometrician_id',
        'is_confirmed',
        'corrected_depression_level',
        'corrected_anxiety_level',
        'corrected_stress_level',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_confirmed' => 'boolean',
        ];
    }

    /**
     * Get the assessment this feedback concerns.
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * Get the psychometrician who submitted this feedback.
     */
    public function psychometrician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'psychometrician_id');
    }
}
