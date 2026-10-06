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
        return view('reports.assessment-summary', $this->reportData($request));
    }

    public function print(ReportFilterRequest $request): View
    {
        return view('reports.print.assessment-summary', $this->reportData($request));
    }

    public function pdf(ReportFilterRequest $request): Response
    {
        return Pdf::loadView('reports.print.assessment-summary', $this->reportData($request))
            ->download('assessment-summary-report.pdf');
    }

    /**
     * The Guidance Counselor's copy counts the reviewed severity levels
     * (never the AI's raw ones); the Psychometrician's keeps the AI's.
     *
     * @return array<string, mixed>
     */
    private function reportData(ReportFilterRequest $request): array
    {
        return $this->reportService->assessmentSummaryData(
            $this->filters($request),
            reviewedLevels: $request->user()->hasRole('guidance_counselor'),
        ) + [
            'courses' => $this->reportService->courseOptions(),
            'yearLevels' => $this->reportService->yearLevelOptions(),
        ];
    }

    /**
     * @return array{course_id: ?int, year_level_id: ?int, gender: ?string, date_from: ?string, date_to: ?string}
     */
    private function filters(ReportFilterRequest $request): array
    {
        return $request->safe()->only(['course_id', 'year_level_id', 'gender', 'date_from', 'date_to']);
    }
}
