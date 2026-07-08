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
 * Monthly Assessment Report: all assessments submitted in a given
 * year/month (defaults to the current month).
 */
class MonthlyAssessmentReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService)
    {
    }

    public function index(ReportFilterRequest $request): View
    {
        $year = (int) ($request->validated('year') ?? now()->format('Y'));
        $month = (int) ($request->validated('month') ?? now()->format('n'));

        return view('reports.monthly-assessments', [
            'year' => $year,
            'month' => $month,
            'assessments' => $this->reportService->monthlyAssessmentsQuery($year, $month)->paginate(10)->withQueryString(),
        ]);
    }

    public function print(ReportFilterRequest $request): View
    {
        $year = (int) ($request->validated('year') ?? now()->format('Y'));
        $month = (int) ($request->validated('month') ?? now()->format('n'));

        return view('reports.print.monthly-assessments', [
            'year' => $year,
            'month' => $month,
            'assessments' => $this->reportService->monthlyAssessmentsQuery($year, $month)->get(),
        ]);
    }

    public function pdf(ReportFilterRequest $request): Response
    {
        $year = (int) ($request->validated('year') ?? now()->format('Y'));
        $month = (int) ($request->validated('month') ?? now()->format('n'));

        $data = [
            'year' => $year,
            'month' => $month,
            'assessments' => $this->reportService->monthlyAssessmentsQuery($year, $month)->get(),
        ];

        return Pdf::loadView('reports.print.monthly-assessments', $data)
            ->download("monthly-assessment-report-{$year}-{$month}.pdf");
    }
}
