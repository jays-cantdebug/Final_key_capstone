<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\FlaggedCaseFilterRequest;
use App\Models\FlaggedCase;
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
        $activeTab = $filters['tab'] ?? 'all';

        return view('flagged-cases.index', [
            'assessments' => $this->flaggedCaseService->paginate($filters),
            'filters' => $filters,
            'activeTab' => $activeTab,
            'reportFilters' => $this->reportFiltersForTab($filters, $activeTab),
            'canGenerateReport' => $activeTab !== 'normal',
            'courses' => $this->flaggedCaseService->allCourses(),
            'yearLevels' => $this->flaggedCaseService->allYearLevels(),
            'sections' => $this->flaggedCaseService->allSections(),
        ]);
    }

    /**
     * Translate the listing's `tab` filter into the `flag_type` filter the
     * Flagged Students Report actually understands, so the PDF/print output
     * matches what's on screen instead of silently dropping `tab` (which
     * `ReportFilterRequest` has no rule for). The "Normal" tab has no report
     * equivalent — the report only ever lists flagged_cases rows — so
     * callers must gate the report links on `canGenerateReport` for that tab.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function reportFiltersForTab(array $filters, string $activeTab): array
    {
        $reportFilters = array_diff_key($filters, ['tab' => null]);

        $flagType = match ($activeTab) {
            'endorsement' => FlaggedCase::FLAG_TYPE_COUNSELING_ENDORSEMENT,
            'notification' => FlaggedCase::FLAG_TYPE_AWARENESS_NOTIFICATION,
            default => null,
        };

        if ($flagType !== null) {
            $reportFilters['flag_type'] = $flagType;
        }

        return $reportFilters;
    }
}
