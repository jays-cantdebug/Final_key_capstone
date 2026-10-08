<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\DuplicateStudentException;
use App\Exceptions\QuestionnaireVersionMismatchException;
use App\Http\Requests\AssessmentResponseFormRequest;
use App\Http\Requests\AssessmentStudentRequest;
use App\Http\Requests\PredictionFeedbackFormRequest;
use App\Models\QuestionnaireVersion;
use App\Models\Student;
use App\Services\AssessmentService;
use App\Services\AssessmentWizardStaging;
use App\Services\RemoteAssessmentService;
use App\Services\StudentDuplicateService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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

    /**
     * Shown at Step 2 when Step 3 or the final save has no pinned
     * questionnaire version to work from (a wizard session started before
     * pinning existed).
     */
    public const SUBMIT_QUESTIONNAIRE_FIRST_MESSAGE = 'Please submit the questionnaire before reviewing the assessment.';

    public const CONFIRM_ARCHIVED_MATCH_MESSAGE = 'Please tick the box to confirm you want to create a new student record.';

    public const ANSWERED_ON_STUDENT_DEVICE_MESSAGE = 'These answers were given on the student device and can’t be changed here.';

    public function __construct(
        private readonly AssessmentService $assessmentService,
        private readonly StudentDuplicateService $duplicateService,
        private readonly RemoteAssessmentService $remoteAssessments,
        private readonly AssessmentWizardStaging $staging,
    ) {}

    /**
     * STEP 1 (GET): Show the student intake form, plus the duplicate-
     * student panel when Step 1 (or the final save) was just refused or
     * warned about a student with the same name — see confirmStudent().
     *
     * The panel is flashed for one page load only, so when a form that
     * showed the archived-match warning fails validation (e.g. the privacy
     * box left unticked), the warning is rebuilt from the old input
     * instead — otherwise it would vanish along with the confirm box.
     */
    public function showStudentStep(Request $request): View
    {
        $duplicate = $request->session()->get('duplicate_student')
            ?? $this->archivedWarningFromOldInput($request);

        return view('assessments.create.student', [
            'courses' => $this->assessmentService->activeCourses(),
            'yearLevels' => $this->assessmentService->activeYearLevels(),
            'sections' => $this->assessmentService->activeSections(),
            'duplicate' => $duplicate === null ? null : [
                ...$duplicate,
                'students' => $this->duplicateService->describe($duplicate['ids']),
            ],
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
     * `existing_student_id` — and refuses a name that already belongs to
     * an active student, pointing here instead. Route-model binding on `$student` already 404s for an
     * archived (soft-deleted) student, matching how they're excluded
     * everywhere else.
     */
    public function startRetake(Request $request, Student $student): RedirectResponse
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
        session()->forget(self::SESSION_KEY.'.questionnaire_version_id');
        session()->forget(self::SESSION_KEY.'.privacy_consent_at');
        session()->forget(self::SESSION_KEY.'.review');
        session()->forget(self::SESSION_KEY.'.acknowledged_archived_ids');
        session()->forget(self::SESSION_KEY.'.'.AssessmentWizardStaging::ADMINISTRATION_MODE_KEY);
        $this->staging->forgetRemote($request->session());
        $this->remoteAssessments->discardFor($request->user());

        return redirect()->route('assessments.create.questionnaire');
    }

    /**
     * STEP 1 (POST): Validate the intake form and stage it in session,
     * then advance to Step 2. Nothing is written to the `students` table
     * yet — that only happens on final submit (Step 3), so an abandoned
     * wizard run never leaves an orphan row behind.
     *
     * Every Step 1 POST starts the wizard over: any earlier staged state
     * (including a "Take Again" retake that was started and abandoned, and
     * any earlier archived-match confirmation) is cleared first, so a
     * regular New Assessment can never silently stay in retake mode or
     * inherit a confirmation given for a different name.
     *
     * Duplicate check (StudentDuplicateService): a matching *active*
     * student always blocks — the Psychometrician is sent to that
     * student's Take Again instead, with no way to continue as a new
     * student. A matching *archived* student (who can't use Take Again)
     * only warns: the form must be re-submitted with the warning's confirm
     * box ticked, and the archived students found right now — computed
     * here, never taken from the request — are staged as
     * `acknowledged_archived_ids` for the final save's audit entry.
     */
    public function confirmStudent(AssessmentStudentRequest $request): RedirectResponse
    {
        // Starting over also ends any student-device draft from before.
        $this->remoteAssessments->discardFor($request->user());
        $request->session()->forget(self::SESSION_KEY);

        $matches = $this->duplicateService->findMatches(
            $request->validated('first_name'),
            $request->validated('middle_name'),
            $request->validated('last_name'),
        );

        if ($matches['active']->isNotEmpty()) {
            return $this->backToStudentStepWithDuplicate('active', $matches['active']->modelKeys(), $request->validated());
        }

        if ($matches['archived']->isNotEmpty() && ! $request->boolean('confirm_archived_match')) {
            $redirect = $this->backToStudentStepWithDuplicate('archived', $matches['archived']->modelKeys(), $request->validated());

            // Only flag the checkbox once the warning (and so the
            // checkbox) has actually been on screen.
            return $request->boolean('archived_warning_shown')
                ? $redirect->withErrors(['confirm_archived_match' => self::CONFIRM_ARCHIVED_MATCH_MESSAGE])
                : $redirect;
        }

        $studentData = $request->safe()->except(['privacy_consent', 'archived_warning_shown', 'confirm_archived_match']);
        $studentData['privacy_consent_at'] = now();

        $request->session()->put(self::SESSION_KEY.'.student_data', $studentData);

        if ($matches['archived']->isNotEmpty()) {
            $request->session()->put(self::SESSION_KEY.'.acknowledged_archived_ids', $matches['archived']->modelKeys());
        }

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

        $pinnedVersionId = $request->session()->get(self::SESSION_KEY.'.questionnaire_version_id');

        // After a student-device Submit the answers are read-only here,
        // shown on the version they were answered on.
        $answeredOnStudentDevice = $this->staging->answeredOnStudentDevice($request->session())
            && $request->session()->get(self::SESSION_KEY.'.responses') !== null;

        return view('assessments.create.questionnaire', [
            'student' => new Student($studentData),
            'version' => $version,
            // Answered under a version that is no longer the active one.
            'questionnaireChanged' => $pinnedVersionId !== null && $pinnedVersionId !== $version->id,
            'existingResponses' => $request->session()->get(self::SESSION_KEY.'.responses', []),
            'existingStudentId' => $request->session()->get(self::SESSION_KEY.'.existing_student_id'),
            'answeredOnStudentDevice' => $answeredOnStudentDevice,
            'answeredVersion' => $answeredOnStudentDevice ? ($this->pinnedVersion($request) ?? $version) : null,
            'remoteDraftInProgress' => $this->remoteAssessments->ownedDraft(
                $request->user(),
                $request->session()->get(self::SESSION_KEY.'.remote_draft_id'),
            ) !== null,
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
     *
     * Pins the version the answers were validated against (the one Active
     * now) in session: Step 3 and the final save use that version, even if
     * another one is activated in the meantime. Resubmitting Step 2 re-pins
     * whatever is Active at that moment.
     */
    public function storeResponses(AssessmentResponseFormRequest $request): RedirectResponse
    {
        $studentData = $request->session()->get(self::SESSION_KEY.'.student_data');

        if ($studentData === null) {
            return redirect()->route('assessments.create')
                ->withErrors(['student' => 'Please select or register a student before continuing.']);
        }

        if ($this->staging->answeredOnStudentDevice($request->session())) {
            return redirect()->route('assessments.create.questionnaire')
                ->withErrors(['questionnaire' => self::ANSWERED_ON_STUDENT_DEVICE_MESSAGE]);
        }

        $version = $request->questionnaireVersion();

        if ($version === null) {
            return redirect()->route('assessments.create')
                ->withErrors(['student' => 'No active questionnaire version is currently configured. Please contact an administrator.']);
        }

        // Answered on this device after all: end any student-device draft.
        $this->remoteAssessments->discardFor($request->user());
        $this->staging->forgetRemote($request->session());
        $this->staging->stageResponses($request->session(), $version, $request->validated('responses'));

        return redirect()->route('assessments.create.result');
    }

    /**
     * STEP 3 (GET): Review the AI's proposed classification before
     * anything is saved. Scores and classification are computed once and
     * cached in session (`.review`) rather than recomputed on every
     * visit — this means the AI provider (billed, for the Claude
     * provider) is invoked once per Step 2 submission (storeResponses()
     * clears the cache, so resubmitting identical answers calls it
     * again), not on every refresh, and that
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

        $version = $this->pinnedVersion($request);

        if ($version === null) {
            return redirect()->route('assessments.create.questionnaire')
                ->withErrors(['questionnaire' => self::SUBMIT_QUESTIONNAIRE_FIRST_MESSAGE]);
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
     * validates the Confirm/Correct decision
     * (`PredictionFeedbackFormRequest`; this is the only place a review
     * decision is made — there is no post-save Feedback Loop), then
     * persists the student, assessment,
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

        $version = $this->pinnedVersion($request);

        if ($version === null) {
            return redirect()->route('assessments.create.questionnaire')
                ->withErrors(['questionnaire' => self::SUBMIT_QUESTIONNAIRE_FIRST_MESSAGE]);
        }

        // A "correction" that picks the AI's own level changes nothing, so
        // Correct & Save needs at least one subscale that really differs.
        // Same-level picks next to a real change are kept as chosen.
        if (! $request->boolean('is_confirmed') && ! $this->changesAnyLevel($request->correctedLevels(), $review)) {
            return redirect()->route('assessments.create.result')->withInput()->withErrors(['corrections' => PredictionFeedbackFormRequest::NO_CORRECTION_MESSAGE]);
        }

        $existingStudentId = $request->session()->get(self::SESSION_KEY.'.existing_student_id');
        $existingStudent = $existingStudentId !== null ? Student::findOrFail($existingStudentId) : null;
        $privacyConsentAt = $request->session()->get(self::SESSION_KEY.'.privacy_consent_at');

        try {
            $assessment = $this->assessmentService->save(
                $studentData,
                $version,
                $request->user(),
                $responses,
                $review,
                $request->validated(),
                $existingStudent,
                $privacyConsentAt,
                $request->session()->get(self::SESSION_KEY.'.acknowledged_archived_ids', []),
                $request->session()->get(self::SESSION_KEY.'.'.AssessmentWizardStaging::ADMINISTRATION_MODE_KEY),
            );
        } catch (DuplicateStudentException $exception) {
            // Someone registered this student after Step 1's check (another
            // tab, a stale session, a double submit). Nothing was saved;
            // the wizard starts over and points at Take Again.
            $this->remoteAssessments->discardFor($request->user());
            $request->session()->forget(self::SESSION_KEY);

            return $this->backToStudentStepWithDuplicate('conflict', $exception->studentIds, $studentData);
        } catch (QuestionnaireVersionMismatchException $exception) {
            // Nothing was saved. Keep the student, drop the answers that
            // don't fit, and have the questionnaire answered again.
            $request->session()->forget([
                self::SESSION_KEY.'.responses',
                self::SESSION_KEY.'.review',
                self::SESSION_KEY.'.questionnaire_version_id',
                self::SESSION_KEY.'.'.AssessmentWizardStaging::ADMINISTRATION_MODE_KEY,
            ]);

            return redirect()->route('assessments.create.questionnaire')
                ->withErrors(['questionnaire' => $exception->getMessage()]);
        }

        $this->remoteAssessments->discardFor($request->user());
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('assessments.show', $assessment)
            ->with('status', 'Assessment reviewed and saved successfully.');
    }

    /**
     * The questionnaire version pinned at Step 2, with its questions, or
     * null when nothing is pinned (a wizard session from before pinning).
     */
    private function pinnedVersion(Request $request): ?QuestionnaireVersion
    {
        $versionId = $request->session()->get(self::SESSION_KEY.'.questionnaire_version_id');

        return $versionId === null ? null : $this->assessmentService->questionnaireVersion((int) $versionId);
    }

    /**
     * Whether any chosen correction differs from the AI's level for that
     * subscale in the cached review.
     *
     * @param  array<string, string>  $correctedLevels  `corrected_{subscale}_level` => level.
     * @param  array<string, mixed>  $review
     */
    private function changesAnyLevel(array $correctedLevels, array $review): bool
    {
        foreach ($correctedLevels as $field => $level) {
            $aiLevelKey = str_replace('corrected_', '', $field);

            if ($level !== ($review[$aiLevelKey] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Back to Step 1 with the duplicate-student panel: `$kind` is
     * "active" (blocked at Step 1), "archived" (warning that needs
     * confirming) or "conflict" (blocked at final save). Only the
     * matching students' IDs are flashed; showStudentStep() loads their
     * details fresh. The entered student details — and the privacy
     * consent tick — are kept as old input so confirming an archived match
     * doesn't mean retyping or re-ticking the form.
     *
     * @param  array<int, int>  $studentIds
     * @param  array<string, mixed>  $studentData
     */
    private function backToStudentStepWithDuplicate(string $kind, array $studentIds, array $studentData): RedirectResponse
    {
        return redirect()->route('assessments.create')
            ->withInput(Arr::only($studentData, ['first_name', 'middle_name', 'last_name', 'gender', 'course_id', 'year_level_id', 'section_id', 'privacy_consent']))
            ->with('duplicate_student', $this->duplicatePanel($kind, $studentIds, $studentData));
    }

    /**
     * The archived-match warning again, after a submit that had it on
     * screen (`archived_warning_shown`) failed validation. Re-checked
     * against the old input's name, so it only reappears while that name
     * still has archived — and no active — matches; a dismissed warning
     * sent no `archived_warning_shown` and stays dismissed.
     *
     * @return array{kind: string, ids: array<int, int>, name: string, search: string}|null
     */
    private function archivedWarningFromOldInput(Request $request): ?array
    {
        $name = [
            'first_name' => $request->old('first_name'),
            'middle_name' => $request->old('middle_name'),
            'last_name' => $request->old('last_name'),
        ];

        if (! $request->old('archived_warning_shown')
            || ! is_string($name['first_name']) || ! is_string($name['last_name'])
            || ! (is_string($name['middle_name']) || $name['middle_name'] === null)) {
            return null;
        }

        $matches = $this->duplicateService->findMatches($name['first_name'], $name['middle_name'], $name['last_name']);

        if ($matches['active']->isNotEmpty() || $matches['archived']->isEmpty()) {
            return null;
        }

        return $this->duplicatePanel('archived', $matches['archived']->modelKeys(), $name);
    }

    /**
     * The duplicate-student panel's data: only the matching students' IDs
     * (showStudentStep() loads their details fresh) and the entered name.
     *
     * @param  array<int, int>  $studentIds
     * @param  array<string, mixed>  $studentData
     * @return array{kind: string, ids: array<int, int>, name: string, search: string}
     */
    private function duplicatePanel(string $kind, array $studentIds, array $studentData): array
    {
        $nameParts = Arr::only($studentData, ['first_name', 'middle_name', 'last_name']);

        return [
            'kind' => $kind,
            'ids' => $studentIds,
            'name' => implode(' ', array_filter($nameParts, fn ($part): bool => filled($part))),
            'search' => trim(($studentData['first_name'] ?? '').' '.($studentData['last_name'] ?? '')),
        ];
    }
}
