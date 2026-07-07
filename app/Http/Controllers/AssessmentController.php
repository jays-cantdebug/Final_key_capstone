<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Assessment;
use Illuminate\Contracts\View\View;

/**
 * Displays the final, read-only result of a completed assessment.
 *
 * The AI classification was already computed and persisted at submission
 * time (see AssessmentService::submit()) — an assessment is read-only
 * after submission, so this never re-classifies on view.
 */
class AssessmentController extends Controller
{
    public function show(Assessment $assessment): View
    {
        $assessment->load([
            'student.course',
            'student.yearLevel',
            'student.section',
            'questionnaireVersion.questionnaire',
            'result',
            'responses.question',
            'psychometrician',
            'flaggedCases',
            'predictionFeedback',
        ]);

        return view('assessments.show', [
            'assessment' => $assessment,
        ]);
    }
}
