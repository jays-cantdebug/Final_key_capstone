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

        $search = $request->get('search');

        return view('counseling-sessions.index', [
            'sessions' => $this->sessionService->paginate($search),
            'search' => $search,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', CounselingSession::class);

        $foundStudent = null;
        $matches = collect();

        if ($request->filled('student_id')) {
            $foundStudent = $this->sessionService->findStudentById((int) $request->input('student_id'));
        } elseif ($request->filled('search')) {
            $matches = $this->sessionService->searchStudentsByName($request->string('search')->toString());

            if ($matches->count() === 1) {
                $foundStudent = $matches->first();
            }
        }

        return view('counseling-sessions.create', [
            'foundStudent' => $foundStudent,
            'matches' => $foundStudent ? collect() : $matches,
            'search' => $request->get('search'),
            'searched' => $request->filled('search') || $request->filled('student_id'),
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
