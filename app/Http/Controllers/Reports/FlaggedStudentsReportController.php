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
 * Flagged Students Report: print-optimized and PDF-exportable version of
 * the Flagged Cases listing, reached via a button on the existing
 * flagged-cases.index page (Module 8), carrying through its filters.
 */
class FlaggedStudentsReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService)
    {
    }

    public function print(ReportFilterRequest $request): View
    {
        $filters = $request->validated();

        return view('reports.print.flagged-students', [
            'flaggedCases' => $this->reportService->flaggedCasesForReport($filters),
            'filters' => $filters,
        ]);
    }

    public function pdf(ReportFilterRequest $request): Response
    {
        $filters = $request->validated();

        $data = [
            'flaggedCases' => $this->reportService->flaggedCasesForReport($filters),
            'filters' => $filters,
        ];

        return Pdf::loadView('reports.print.flagged-students', $data)
            ->download('flagged-students-report.pdf');
    }
}
