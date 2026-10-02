<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportFilterRequest;
use App\Models\Student;
use App\Services\ReportService;
use App\Services\StudentCounselingHistoryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counseling Report: print-optimized and PDF-exportable version of the
 * Counseling Sessions listing, reached via a button on the existing
 * counseling-sessions.index page (Module 9). Restricted session notes
 * are redacted exactly as in Module 9's own show page.
 *
 * With a `student_id` filter it doubles as a single student's Counseling
 * History report (reached from that student's history page), adding the
 * student's details and follow-up summary above the session table.
 */
class CounselingReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly StudentCounselingHistoryService $historyService,
    ) {}

    public function print(ReportFilterRequest $request): View
    {
        return view('reports.print.counseling', $this->reportData($request));
    }

    public function pdf(ReportFilterRequest $request): Response
    {
        $data = $this->reportData($request);

        $filename = $data['student']
            ? 'counseling-history-'.$data['student']->student_number.'.pdf'
            : 'counseling-report.pdf';

        return Pdf::loadView('reports.print.counseling', $data)
            ->download($filename);
    }

    /**
     * @return array<string, mixed>
     */
    private function reportData(ReportFilterRequest $request): array
    {
        $filters = $request->validated();

        $student = isset($filters['student_id'])
            ? Student::withTrashed()->with(['course', 'yearLevel', 'section'])->find($filters['student_id'])
            : null;

        return [
            'sessions' => $this->reportService->counselingSessionsForReport($filters),
            'viewer' => $request->user(),
            'student' => $student,
            'summary' => $student ? $this->historyService->summaryFor($student) : null,
        ];
    }
}
