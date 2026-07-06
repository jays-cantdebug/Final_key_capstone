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
 * Assessment Summary Report: aggregate counts (total, by severity,
 * flagged) over an optional date range.
 */
class AssessmentSummaryReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService)
    {
    }

    public function index(ReportFilterRequest $request): View
    {
        return view('reports.assessment-summary', $this->reportService->assessmentSummaryData(
            $request->validated('date_from'),
            $request->validated('date_to'),
        ));
    }

    public function print(ReportFilterRequest $request): View
    {
        return view('reports.print.assessment-summary', $this->reportService->assessmentSummaryData(
            $request->validated('date_from'),
            $request->validated('date_to'),
        ));
    }

    public function pdf(ReportFilterRequest $request): Response
    {
        $data = $this->reportService->assessmentSummaryData(
            $request->validated('date_from'),
            $request->validated('date_to'),
        );

        return Pdf::loadView('reports.print.assessment-summary', $data)
            ->download('assessment-summary-report.pdf');
    }
}
