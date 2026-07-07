<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Assessment;
use App\Models\PredictionFeedback;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

/**
 * Records a Psychometrician's confirmation or correction of an
 * assessment's AI classification (the Feedback Loop). Creates the
 * prediction_feedback row the first time, updates it in place on
 * subsequent edits — never duplicates it. Never retroactively alters the
 * original dass_results row or any flagged_cases/notifications already
 * issued for the assessment.
 */
class PredictionFeedbackService
{
    public function __construct(private readonly DatabaseManager $database)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function submit(Assessment $assessment, User $psychometrician, array $data): PredictionFeedback
    {
        return $this->database->transaction(function () use ($assessment, $psychometrician, $data): PredictionFeedback {
            return PredictionFeedback::query()->updateOrCreate(
                ['assessment_id' => $assessment->id],
                [
                    'psychometrician_id' => $psychometrician->id,
                    'is_confirmed' => $data['is_confirmed'],
                    'corrected_depression_level' => $data['corrected_depression_level'] ?? null,
                    'corrected_anxiety_level' => $data['corrected_anxiety_level'] ?? null,
                    'corrected_stress_level' => $data['corrected_stress_level'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]
            );
        });
    }
}
