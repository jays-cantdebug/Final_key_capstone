<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CounselingSession;
use App\Models\Student;
use App\Services\StudentCounselingHistoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Per-student counseling history: the "By Student" tab of the Counseling
 * Sessions module, and a single student's full session history. Guidance
 * Counselor-only, like the rest of Counseling Sessions — this is a
 * different lens on the same records, so it reuses CounselingSessionPolicy.
 */
class StudentCounselingHistoryController extends Controller
{
    public function __construct(private readonly StudentCounselingHistoryService $historyService) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', CounselingSession::class);

        $search = $request->get('search');
        $followUp = in_array($request->get('follow_up'), [
            StudentCounselingHistoryService::FOLLOW_UP_NEEDED,
            StudentCounselingHistoryService::FOLLOW_UP_OVERDUE,
        ], true) ? $request->get('follow_up') : null;

        $data = [
            'students' => $this->historyService->paginate($search, $followUp),
            'search' => $search,
            'followUp' => $followUp,
        ];

        if ($request->header('X-Live-Search') === 'true') {
            return view('counseling-sessions.students._table', $data);
        }

        return view('counseling-sessions.students.index', $data);
    }

    public function show(Student $student): View
    {
        Gate::authorize('viewAny', CounselingSession::class);

        $student->load(['course', 'yearLevel', 'section']);

        return view('counseling-sessions.students.show', [
            'student' => $student,
            'summary' => $this->historyService->summaryFor($student),
            'sessions' => $this->historyService->sessionsFor($student),
        ]);
    }
}
