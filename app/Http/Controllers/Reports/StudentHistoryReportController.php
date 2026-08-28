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
 * Student Assessment History Report: print-optimized and PDF-exportable
 * version of a single student's assessment history, reached via a button
 * on the existing assessments.index page (Module 7) when filtered to a
 * student.
 */
class StudentHistoryReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    public function print(ReportFilterRequest $request): View
    {
        return view('reports.print.student-history', $this->reportService->studentHistoryForReport(
            $request->validated('student_number'),
            $request->validated('date_from'),
            $request->validated('date_to'),
        ));
    }

    public function pdf(ReportFilterRequest $request): Response
    {
        $data = $this->reportService->studentHistoryForReport(
            $request->validated('student_number'),
            $request->validated('date_from'),
            $request->validated('date_to'),
        );

        return Pdf::loadView('reports.print.student-history', $data)
            ->download('student-assessment-history-report.pdf');
    }
}
