<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AssessmentResponseFormRequest;
use App\Http\Requests\AssessmentStudentRequest;
use App\Http\Requests\PredictionFeedbackFormRequest;
use App\Models\Student;
use App\Services\AssessmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Drives the 3-step New Assessment workflow (Student -> Questionnaire ->
 * Review & Save). Wizard state (the intake data, in-progress responses,
 * and — once Step 3 is reached — the cached AI review) is held in the
 * session between steps; nothing is persisted to the
 * students/assessments/dass_responses/dass_results/prediction_feedback/
 * flagged_cases tables until the Psychometrician confirms or corrects
 * the AI's classification on Step 3 and clicks the final Confirm & Save
 * / Correct & Save action, so abandoning the wizard at any point —
 * including after seeing the AI's proposed classification — leaves no
 * trace in the database, and a Guidance Counselor is never notified
 * about a classification that hasn't been reviewed yet.
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
     * "Take Again" entry point: starts a retake for an already-registered
     * student (from their row on the Students list or their profile
     * page), skipping Step 1 entirely — the student's existing course/
     * year level/section/gender/name are staged straight into session,
     * along with `existing_student_id` marking this as a retake, and the
     * flow lands directly on Step 2. This is the only path that can make
     * `submit()` attach a new assessment to an existing student instead
     * of registering a fresh one; the regular Step 1 form never sets
     * `existing_student_id`, so its "always a fresh student" behavior is
     * untouched. Route-model binding on `$student` already 404s for an
     * archived (soft-deleted) student, matching how they're excluded
     * everywhere else.
     */
    public function startRetake(Student $student): RedirectResponse
    {
        Gate::authorize('view', $student);

        $studentData = [
            'first_name' => $student->first_name,
            'middle_name' => $student->middle_name,
            'last_name' => $student->last_name,
            'gender' => $student->gender,
            'course_id' => $student->course_id,
            'year_level_id' => $student->year_level_id,
            'section_id' => $student->section_id,
        ];

        session([
            self::SESSION_KEY.'.student_data' => $studentData,
            self::SESSION_KEY.'.existing_student_id' => $student->id,
        ]);
        session()->forget(self::SESSION_KEY.'.responses');
        session()->forget(self::SESSION_KEY.'.privacy_consent_at');
        session()->forget(self::SESSION_KEY.'.review');

        return redirect()->route('assessments.create.questionnaire');
    }

    /**
     * STEP 1 (POST): Validate the intake form and stage it in session,
     * then advance to Step 2. Nothing is written to the `students` table
     * yet — that only happens on final submit (Step 3), so a fresh
     * `students` row is guaranteed on every *completed* wizard run
     * without leaving an orphan row behind for abandoned ones.
     *
     * Explicitly clears `existing_student_id`/`privacy_consent_at` in
     * case a "Take Again" retake was started and abandoned earlier in
     * this same session — without this, starting a regular New
     * Assessment afterward would silently stay in retake mode and attach
     * to that earlier student instead of registering a fresh one.
     */
    public function confirmStudent(AssessmentStudentRequest $request): RedirectResponse
    {
        $studentData = $request->safe()->except(['privacy_consent']);
        $studentData['privacy_consent_at'] = now();

        $request->session()->put(self::SESSION_KEY.'.student_data', $studentData);
        $request->session()->forget(self::SESSION_KEY.'.responses');
        $request->session()->forget(self::SESSION_KEY.'.existing_student_id');
        $request->session()->forget(self::SESSION_KEY.'.privacy_consent_at');
        $request->session()->forget(self::SESSION_KEY.'.review');

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
            'existingStudentId' => $request->session()->get(self::SESSION_KEY.'.existing_student_id'),
        ]);
    }

    /**
     * STEP 2 (POST): Validate and store responses, then advance to Step 3.
     * In retake mode, `AssessmentResponseFormRequest` also requires and
     * validates `privacy_consent` here (Step 2 doubles as the consent
     * screen for a retake, since Step 1 — where the regular flow captures
     * it — is skipped entirely); the timestamp is staged in session and
     * only lands on the new assessment's own `privacy_consent_at` at
     * final submit.
     */
    public function storeResponses(AssessmentResponseFormRequest $request): RedirectResponse
    {
        $studentData = $request->session()->get(self::SESSION_KEY.'.student_data');

        if ($studentData === null) {
            return redirect()->route('assessments.create')
                ->withErrors(['student' => 'Please select or register a student before continuing.']);
        }

        $request->session()->put(self::SESSION_KEY.'.responses', $request->validated('responses'));
        $request->session()->forget(self::SESSION_KEY.'.review');

        if ($request->session()->has(self::SESSION_KEY.'.existing_student_id')) {
            $request->session()->put(self::SESSION_KEY.'.privacy_consent_at', now());
        }

        return redirect()->route('assessments.create.result');
    }

    /**
     * STEP 3 (GET): Review the AI's proposed classification before
     * anything is saved. Scores and classification are computed once and
     * cached in session (`.review`) rather than recomputed on every
     * visit — this means the AI provider (billed, for the Claude
     * provider) is only ever invoked once per set of responses, and that
     * what the Psychometrician actually reviews here is exactly what
     * `submit()` persists, unaffected by anything that changes between
     * viewing this page and clicking Confirm/Correct & Save.
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

        if ($version === null) {
            return redirect()->route('assessments.create')
                ->withErrors(['student' => 'No active questionnaire version is currently configured. Please contact an administrator.']);
        }

        $review = $request->session()->get(self::SESSION_KEY.'.review');

        if ($review === null) {
            $review = $this->assessmentService->reviewAssessment($version, $responses);
            $request->session()->put(self::SESSION_KEY.'.review', $review);
        }

        return view('assessments.create.result', [
            'student' => new Student($studentData),
            'version' => $version,
            'review' => $review,
            'responses' => $responses,
            'existingStudentId' => $request->session()->get(self::SESSION_KEY.'.existing_student_id'),
        ]);
    }

    /**
     * STEP 3 (POST): Confirm & Save / Correct & Save. This is the only
     * action in the whole wizard that writes to the database — it
     * validates the Confirm/Correct decision (the same
     * `PredictionFeedbackFormRequest` shape the standalone post-save
     * Feedback Loop uses), then persists the student, assessment,
     * responses, the AI's raw classification exactly as reviewed, the
     * review decision itself, and — evaluated against the *reviewed*
     * severity, never the AI's raw output — any differentiated flagged
     * cases and Guidance Counselor notifications, all in one transaction.
     */
    public function submit(PredictionFeedbackFormRequest $request): RedirectResponse
    {
        $studentData = $request->session()->get(self::SESSION_KEY.'.student_data');
        $responses = $request->session()->get(self::SESSION_KEY.'.responses');
        $review = $request->session()->get(self::SESSION_KEY.'.review');

        if ($studentData === null || $responses === null || $review === null) {
            return redirect()->route('assessments.create')
                ->withErrors(['student' => 'Please complete the previous steps before submitting.']);
        }

        $version = $this->assessmentService->activeQuestionnaireVersion();

        if ($version === null) {
            return redirect()->route('assessments.create')
                ->withErrors(['student' => 'No active questionnaire version is currently configured. Please contact an administrator.']);
        }

        $existingStudentId = $request->session()->get(self::SESSION_KEY.'.existing_student_id');
        $existingStudent = $existingStudentId !== null ? Student::findOrFail($existingStudentId) : null;
        $privacyConsentAt = $request->session()->get(self::SESSION_KEY.'.privacy_consent_at');

        $assessment = $this->assessmentService->save(
            $studentData,
            $version,
            $request->user(),
            $responses,
            $review,
            $request->validated(),
            $existingStudent,
            $privacyConsentAt,
        );

        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('assessments.show', $assessment)
            ->with('status', 'Assessment reviewed and saved successfully.');
    }
}
