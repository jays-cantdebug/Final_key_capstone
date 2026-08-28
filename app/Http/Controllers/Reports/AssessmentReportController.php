<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assessment Report: the print-optimized and PDF-exportable version of a
 * single assessment, reached via a button on the existing assessments.show
 * page (Modules 6/7) rather than a separate browse UI.
 */
class AssessmentReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    public function print(Assessment $assessment): View
    {
        return view('reports.print.assessment', [
            'assessment' => $this->reportService->loadAssessmentForReport($assessment),
        ]);
    }

    public function pdf(Assessment $assessment): Response
    {
        $data = ['assessment' => $this->reportService->loadAssessmentForReport($assessment)];

        return Pdf::loadView('reports.print.assessment', $data)
            ->download("assessment-report-{$assessment->id}.pdf");
    }
}
