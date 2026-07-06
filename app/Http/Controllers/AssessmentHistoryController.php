<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AssessmentHistoryRequest;
use App\Services\AssessmentHistoryService;
use Illuminate\Contracts\View\View;

/**
 * Displays the Assessment History listing: a paginated, searchable list
 * of past assessments. Accessible to both Psychometrician and Guidance
 * Counselor, matching their respective "View Student Assessment History"
 * permissions.
 */
class AssessmentHistoryController extends Controller
{
    public function __construct(private readonly AssessmentHistoryService $historyService)
    {
    }

    public function index(AssessmentHistoryRequest $request): View
    {
        $studentNumber = $request->validated('student_number');

        return view('assessments.index', [
            'assessments' => $this->historyService->paginate($studentNumber),
            'studentNumber' => $studentNumber,
        ]);
    }
}
