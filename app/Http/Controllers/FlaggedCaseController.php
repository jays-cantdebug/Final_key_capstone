<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\FlaggedCaseFilterRequest;
use App\Services\FlaggedCaseService;
use Illuminate\Contracts\View\View;

/**
 * Displays the Flagged Students listing for Guidance Counselors: assessments
 * that met or exceeded the Notification Severity Threshold.
 */
class FlaggedCaseController extends Controller
{
    public function __construct(private readonly FlaggedCaseService $flaggedCaseService)
    {
    }

    public function index(FlaggedCaseFilterRequest $request): View
    {
        $filters = $request->validated();

        return view('flagged-cases.index', [
            'assessments' => $this->flaggedCaseService->paginate($filters),
            'filters' => $filters,
            'activeTab' => $filters['tab'] ?? 'all',
            'courses' => $this->flaggedCaseService->allCourses(),
            'yearLevels' => $this->flaggedCaseService->allYearLevels(),
            'sections' => $this->flaggedCaseService->allSections(),
        ]);
    }
}
