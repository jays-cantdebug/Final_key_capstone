<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CounselingSessionFormRequest;
use App\Models\CounselingSession;
use App\Services\CounselingSessionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CounselingSessionController extends Controller
{
    public function __construct(private readonly CounselingSessionService $sessionService)
    {
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', CounselingSession::class);

        $studentNumber = $request->get('student_number');

        return view('counseling-sessions.index', [
            'sessions' => $this->sessionService->paginate($studentNumber),
            'studentNumber' => $studentNumber,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', CounselingSession::class);

        $searched = $request->filled('student_number');
        $foundStudent = null;

        if ($searched) {
            $foundStudent = $this->sessionService->findStudentByNumber(
                $request->string('student_number')->toString()
            );
        }

        return view('counseling-sessions.create', [
            'foundStudent' => $foundStudent,
            'searched' => $searched,
            'assessments' => $foundStudent ? $this->sessionService->assessmentsForStudent($foundStudent) : collect(),
        ]);
    }

    public function store(CounselingSessionFormRequest $request): RedirectResponse
    {
        Gate::authorize('create', CounselingSession::class);

        $session = $this->sessionService->create($request->validated(), $request->user());

        return redirect()->route('counseling-sessions.show', $session)
            ->with('status', 'Counseling session created successfully.');
    }

    public function show(CounselingSession $counselingSession): View
    {
        Gate::authorize('view', $counselingSession);

        $counselingSession->load(['student.course', 'student.yearLevel', 'student.section', 'counselor', 'assessment.result']);

        return view('counseling-sessions.show', ['session' => $counselingSession]);
    }

    public function edit(CounselingSession $counselingSession): View
    {
        Gate::authorize('update', $counselingSession);

        $counselingSession->load('student');

        return view('counseling-sessions.edit', [
            'session' => $counselingSession,
            'assessments' => $this->sessionService->assessmentsForStudent($counselingSession->student),
        ]);
    }

    public function update(CounselingSessionFormRequest $request, CounselingSession $counselingSession): RedirectResponse
    {
        Gate::authorize('update', $counselingSession);

        $this->sessionService->update($counselingSession, $request->validated());

        return redirect()->route('counseling-sessions.show', $counselingSession)
            ->with('status', 'Counseling session updated successfully.');
    }

    public function destroy(CounselingSession $counselingSession): RedirectResponse
    {
        Gate::authorize('delete', $counselingSession);

        $this->sessionService->delete($counselingSession);

        return redirect()->route('counseling-sessions.index')
            ->with('status', 'Counseling session deleted successfully.');
    }
}
