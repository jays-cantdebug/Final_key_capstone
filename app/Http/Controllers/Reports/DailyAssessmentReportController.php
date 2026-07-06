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
 * Daily Assessment Report: all assessments submitted on a given date
 * (defaults to today).
 */
class DailyAssessmentReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService)
    {
    }

    public function index(ReportFilterRequest $request): View
    {
        $date = $request->validated('date') ?? now()->toDateString();

        return view('reports.daily-assessments', [
            'date' => $date,
            'assessments' => $this->reportService->dailyAssessmentsQuery($date)->paginate(10)->withQueryString(),
        ]);
    }

    public function print(ReportFilterRequest $request): View
    {
        $date = $request->validated('date') ?? now()->toDateString();

        return view('reports.print.daily-assessments', [
            'date' => $date,
            'assessments' => $this->reportService->dailyAssessmentsQuery($date)->get(),
        ]);
    }

    public function pdf(ReportFilterRequest $request): Response
    {
        $date = $request->validated('date') ?? now()->toDateString();

        $data = [
            'date' => $date,
            'assessments' => $this->reportService->dailyAssessmentsQuery($date)->get(),
        ];

        return Pdf::loadView('reports.print.daily-assessments', $data)
            ->download("daily-assessment-report-{$date}.pdf");
    }
}
