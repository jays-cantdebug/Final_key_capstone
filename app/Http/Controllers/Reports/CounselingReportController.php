<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportFilterRequest;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counseling Report: print-optimized and PDF-exportable version of the
 * Counseling Sessions listing, reached via a button on the existing
 * counseling-sessions.index page (Module 9). Restricted session notes
 * are redacted exactly as in Module 9's own show page.
 */
class CounselingReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService)
    {
    }

    public function print(ReportFilterRequest $request): View
    {
        return view('reports.print.counseling', [
            'sessions' => $this->reportService->counselingSessionsForReport($request->validated()),
            'viewer' => $request->user(),
        ]);
    }

    public function pdf(ReportFilterRequest $request): Response
    {
        /** @var Authenticatable $user */
        $user = $request->user();

        $data = [
            'sessions' => $this->reportService->counselingSessionsForReport($request->validated()),
            'viewer' => $user,
        ];

        return Pdf::loadView('reports.print.counseling', $data)
            ->download('counseling-report.pdf');
    }
}
