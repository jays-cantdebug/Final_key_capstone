<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AssessmentResponseFormRequest;
use App\Http\Requests\AssessmentStudentRequest;
use App\Services\AssessmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Drives the 3-step New Assessment workflow (Student -> Questionnaire ->
 * Result). Wizard state (the selected/registered student and in-progress
 * responses) is held in the session between steps; nothing is persisted
 * to the assessments/dass_responses/dass_results tables until the final
 * Submit & Calculate Score action.
 */
class AssessmentWizardController extends Controller
{
    private const SESSION_KEY = 'assessment_wizard';

    public function __construct(
        private readonly AssessmentService $assessmentService,
    ) {
    }

    /**
     * STEP 1 (GET): Show the student intake form. Every New Assessment is
     * treated as the student's first consultation encounter at intake —
     * there is no search for or reuse of an existing student record here.
     */
    public function showStudentStep(): View
    {
        return view('assessments.create.student', [
            'courses' => $this->assessmentService->activeCourses(),
            'yearLevels' => $this->assessmentService->activeYearLevels(),
            'sections' => $this->assessmentService->activeSections(),
        ]);
    }

    /**
     * STEP 1 (POST): Register a new student, then advance to Step 2. A
     * fresh `students` row is created on every submission of this step.
     */
    public function confirmStudent(AssessmentStudentRequest $request): RedirectResponse
    {
        $student = $this->assessmentService->registerStudent(
            $request->safe()->except(['privacy_consent', 'full_name'])
        );

        $request->session()->put(self::SESSION_KEY.'.student_id', $student->id);
        $request->session()->forget(self::SESSION_KEY.'.responses');

        return redirect()->route('assessments.create.questionnaire');
    }

    /**
     * STEP 2 (GET): Display the active questionnaire version's questions.
     */
    public function showQuestionnaireStep(Request $request): View|RedirectResponse
    {
        $studentId = $request->session()->get(self::SESSION_KEY.'.student_id');

        if ($studentId === null) {
            return redirect()->route('assessments.create')
                ->withErrors(['student' => 'Please select or register a student before continuing.']);
        }

        $version = $this->assessmentService->activeQuestionnaireVersion();

        if ($version === null) {
            return redirect()->route('assessments.create')
                ->withErrors(['student' => 'No active questionnaire version is currently configured. Please contact an administrator.']);
        }

        return view('assessments.create.questionnaire', [
            'student' => $this->assessmentService->findStudentOrFail($studentId),
            'version' => $version,
            'existingResponses' => $request->session()->get(self::SESSION_KEY.'.responses', []),
        ]);
    }

    /**
     * STEP 2 (POST): Validate and store responses, then advance to Step 3.
     */
    public function storeResponses(AssessmentResponseFormRequest $request): RedirectResponse
    {
        $studentId = $request->session()->get(self::SESSION_KEY.'.student_id');

        if ($studentId === null) {
            return redirect()->route('assessments.create')
                ->withErrors(['student' => 'Please select or register a student before continuing.']);
        }

        $request->session()->put(self::SESSION_KEY.'.responses', $request->validated('responses'));

        return redirect()->route('assessments.create.result');
    }

    /**
     * STEP 3 (GET): Review the pending assessment before final submission.
     */
    public function showResultStep(Request $request): View|RedirectResponse
    {
        $studentId = $request->session()->get(self::SESSION_KEY.'.student_id');
        $responses = $request->session()->get(self::SESSION_KEY.'.responses');

        if ($studentId === null || $responses === null) {
            return redirect()->route('assessments.create')
                ->withErrors(['student' => 'Please complete the previous steps before reviewing the assessment.']);
        }

        $version = $this->assessmentService->activeQuestionnaireVersion();

        return view('assessments.create.result', [
            'student' => $this->assessmentService->findStudentOrFail($studentId),
            'version' => $version,
            'responseCount' => count($responses),
            'questionCount' => $version?->questions->count() ?? 0,
        ]);
    }

    /**
     * STEP 3 (POST): Compute and persist the assessment, then redirect to
     * the final read-only result page.
     */
    public function submit(Request $request): RedirectResponse
    {
        $studentId = $request->session()->get(self::SESSION_KEY.'.student_id');
        $responses = $request->session()->get(self::SESSION_KEY.'.responses');

        if ($studentId === null || $responses === null) {
            return redirect()->route('assessments.create')
                ->withErrors(['student' => 'Please complete the previous steps before submitting.']);
        }

        $version = $this->assessmentService->activeQuestionnaireVersion();

        if ($version === null) {
            return redirect()->route('assessments.create')
                ->withErrors(['student' => 'No active questionnaire version is currently configured. Please contact an administrator.']);
        }

        $student = $this->assessmentService->findStudentOrFail($studentId);

        $assessment = $this->assessmentService->submit($student, $version, $request->user(), $responses);

        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('assessments.show', $assessment)
            ->with('status', 'Assessment submitted and scored successfully.');
    }
}
