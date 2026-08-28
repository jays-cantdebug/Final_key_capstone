<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportFilterRequest;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assessment Summary Report: an institution-wide overview - totals,
 * per-condition severity breakdowns, and a chart - optionally filtered by
 * course, year level, gender, and/or date range.
 */
class AssessmentSummaryReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    public function index(ReportFilterRequest $request): View
    {
        return view('reports.assessment-summary', $this->reportService->assessmentSummaryData($this->filters($request)) + [
            'courses' => $this->reportService->courseOptions(),
            'yearLevels' => $this->reportService->yearLevelOptions(),
        ]);
    }

    public function print(ReportFilterRequest $request): View
    {
        return view('reports.print.assessment-summary', $this->reportService->assessmentSummaryData($this->filters($request)) + [
            'courses' => $this->reportService->courseOptions(),
            'yearLevels' => $this->reportService->yearLevelOptions(),
        ]);
    }

    public function pdf(ReportFilterRequest $request): Response
    {
        $data = $this->reportService->assessmentSummaryData($this->filters($request)) + [
            'courses' => $this->reportService->courseOptions(),
            'yearLevels' => $this->reportService->yearLevelOptions(),
        ];

        return Pdf::loadView('reports.print.assessment-summary', $data)
            ->download('assessment-summary-report.pdf');
    }

    /**
     * @return array{course_id: ?int, year_level_id: ?int, gender: ?string, date_from: ?string, date_to: ?string}
     */
    private function filters(ReportFilterRequest $request): array
    {
        return $request->safe()->only(['course_id', 'year_level_id', 'gender', 'date_from', 'date_to']);
    }
}
