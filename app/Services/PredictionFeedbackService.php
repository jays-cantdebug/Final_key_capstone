<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Assessment;
use App\Models\PredictionFeedback;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

/**
 * Records a Psychometrician's confirmation or correction of an
 * assessment's AI classification (the Feedback Loop — in the Audit Log,
 * "Feedback Loop Submission"). Called only from AssessmentService::save(),
 * inside the same transaction that saves the assessment, so it records the
 * mandatory Step 3 review decision exactly once; there is no post-save
 * edit. `updateOrCreate` still keys the row on the assessment, so a repeat
 * call could never create a second row. It never alters the original
 * dass_results row — flagging off the reviewed (effective) levels is done
 * by AssessmentService::save() afterwards.
 */
class PredictionFeedbackService
{
    public function __construct(private readonly DatabaseManager $database) {}

    /**
     * @param  array<string, mixed>  $data
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
