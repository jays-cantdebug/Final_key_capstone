<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AssessmentResponseFormRequest;
use App\Http\Requests\AssessmentStudentRequest;
use App\Models\Student;
use App\Services\AssessmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Drives the 3-step New Assessment workflow (Student -> Questionnaire ->
 * Result). Wizard state (the intake data and in-progress responses) is
 * held in the session between steps; nothing is persisted to the
 * students/assessments/dass_responses/dass_results tables until the
 * final Submit & Calculate Score action, so abandoning the wizard at any
 * point leaves no trace in the database.
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
     * STEP 1 (POST): Validate the intake form and stage it in session,
     * then advance to Step 2. Nothing is written to the `students` table
     * yet — that only happens on final submit (Step 3), so a fresh
     * `students` row is guaranteed on every *completed* wizard run
     * without leaving an orphan row behind for abandoned ones.
     */
    public function confirmStudent(AssessmentStudentRequest $request): RedirectResponse
    {
        $studentData = $request->safe()->except(['privacy_consent', 'full_name']);
        $studentData['privacy_consent_at'] = now();

        $request->session()->put(self::SESSION_KEY.'.student_data', $studentData);
        $request->session()->forget(self::SESSION_KEY.'.responses');

        return redirect()->route('assessments.create.questionnaire');
    }

    /**
     * STEP 2 (GET): Display the active questionnaire version's questions.
     */
    public function showQuestionnaireStep(Request $request): View|RedirectResponse
    {
        $studentData = $request->session()->get(self::SESSION_KEY.'.student_data');

        if ($studentData === null) {
            return redirect()->route('assessments.create')
                ->withErrors(['student' => 'Please select or register a student before continuing.']);
        }

        $version = $this->assessmentService->activeQuestionnaireVersion();

        if ($version === null) {
            return redirect()->route('assessments.create')
                ->withErrors(['student' => 'No active questionnaire version is currently configured. Please contact an administrator.']);
        }

        return view('assessments.create.questionnaire', [
            'student' => new Student($studentData),
            'version' => $version,
            'existingResponses' => $request->session()->get(self::SESSION_KEY.'.responses', []),
        ]);
    }

    /**
     * STEP 2 (POST): Validate and store responses, then advance to Step 3.
     */
    public function storeResponses(AssessmentResponseFormRequest $request): RedirectResponse
    {
        $studentData = $request->session()->get(self::SESSION_KEY.'.student_data');

        if ($studentData === null) {
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
        $studentData = $request->session()->get(self::SESSION_KEY.'.student_data');
        $responses = $request->session()->get(self::SESSION_KEY.'.responses');

        if ($studentData === null || $responses === null) {
            return redirect()->route('assessments.create')
                ->withErrors(['student' => 'Please complete the previous steps before reviewing the assessment.']);
        }

        $version = $this->assessmentService->activeQuestionnaireVersion();

        return view('assessments.create.result', [
            'student' => new Student($studentData),
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
        $studentData = $request->session()->get(self::SESSION_KEY.'.student_data');
        $responses = $request->session()->get(self::SESSION_KEY.'.responses');

        if ($studentData === null || $responses === null) {
            return redirect()->route('assessments.create')
                ->withErrors(['student' => 'Please complete the previous steps before submitting.']);
        }

        $version = $this->assessmentService->activeQuestionnaireVersion();

        if ($version === null) {
            return redirect()->route('assessments.create')
                ->withErrors(['student' => 'No active questionnaire version is currently configured. Please contact an administrator.']);
        }

        $assessment = $this->assessmentService->submit($studentData, $version, $request->user(), $responses);

        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('assessments.show', $assessment)
            ->with('status', 'Assessment submitted and scored successfully.');
    }
}
