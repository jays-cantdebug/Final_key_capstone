<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Questionnaire Usage Report: for each questionnaire version, its
 * question count and the number of assessments that used it.
 */
class QuestionnaireUsageReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService)
    {
    }

    public function index(): View
    {
        return view('reports.questionnaire-usage', [
            'versions' => $this->reportService->questionnaireUsageForReport(),
        ]);
    }

    public function print(): View
    {
        return view('reports.print.questionnaire-usage', [
            'versions' => $this->reportService->questionnaireUsageForReport(),
        ]);
    }

    public function pdf(): Response
    {
        $data = ['versions' => $this->reportService->questionnaireUsageForReport()];

        return Pdf::loadView('reports.print.questionnaire-usage', $data)
            ->download('questionnaire-usage-report.pdf');
    }
}
