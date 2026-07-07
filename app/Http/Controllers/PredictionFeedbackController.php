<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PredictionFeedbackFormRequest;
use App\Models\Assessment;
use App\Services\PredictionFeedbackService;
use Illuminate\Http\RedirectResponse;

/**
 * Records a Psychometrician's confirmation or correction of an
 * assessment's AI classification (the Feedback Loop).
 */
class PredictionFeedbackController extends Controller
{
    public function __construct(private readonly PredictionFeedbackService $predictionFeedbackService)
    {
    }

    public function store(PredictionFeedbackFormRequest $request, Assessment $assessment): RedirectResponse
    {
        $this->predictionFeedbackService->submit($assessment, $request->user(), $request->validated());

        return redirect()->route('assessments.show', $assessment)
            ->with('status', 'Feedback recorded.');
    }
}
