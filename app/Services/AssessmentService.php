<?php

declare(strict_types=1);

namespace App\Services;

use App\AI\DTOs\AssessmentPayload;
use App\AI\Services\AIService;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\DassResult;
use App\Models\QuestionnaireVersion;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Models\YearLevel;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Collection;

/**
 * Orchestrates the New Assessment workflow: locating or registering the
 * student, loading the active questionnaire version, and — since the
 * mandatory pre-save review requirement — separating (a) computing scores
 * and an AI classification for the Psychometrician to review from (b)
 * actually persisting the assessment (responses + the AI's raw scores/
 * classification + the review decision + differentiated flags/
 * notifications), which only ever happens once she confirms or corrects
 * that review, inside a single database transaction.
 */
class AssessmentService
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly DassScoringService $scoringService,
        private readonly AIService $aiService,
        private readonly FlaggedCaseService $flaggedCaseService,
        private readonly PredictionFeedbackService $predictionFeedbackService,
        private readonly StudentNumberGeneratorService $studentNumberGenerator,
        private readonly ClassificationThresholdService $thresholdService,
    ) {}

    /**
     * @return Collection<int, Course>
     */
    public function activeCourses(): Collection
    {
        return Course::query()->where('status', Course::STATUS_ACTIVE)->orderBy('course_code')->get();
    }

    /**
     * @return Collection<int, YearLevel>
     */
    public function activeYearLevels(): Collection
    {
        return YearLevel::query()->where('status', YearLevel::STATUS_ACTIVE)->orderBy('display_order')->get();
    }

    /**
     * @return Collection<int, Section>
     */
    public function activeSections(): Collection
    {
        return Section::query()->where('status', Section::STATUS_ACTIVE)->orderBy('section_name')->get();
    }

    /**
     * Register a new student. `$data` already carries `privacy_consent_at`
     * (captured at the moment consent was given in Step 1 of the wizard,
     * not whenever the wizard eventually finishes).
     *
     * @param  array<string, mixed>  $data
     */
    public function registerStudent(array $data): Student
    {
        return $this->database->transaction(function () use ($data): Student {
            return Student::query()->create([
                ...$data,
                'student_number' => $this->studentNumberGenerator->generate(),
            ]);
        });
    }

    public function activeQuestionnaireVersion(): ?QuestionnaireVersion
    {
        return QuestionnaireVersion::query()
            ->where('status', QuestionnaireVersion::STATUS_ACTIVE)
            ->with('questions')
            ->first();
    }

    /**
     * Compute the DASS-21 scores and AI classification for a set of
     * responses, for the Psychometrician to review at Step 3 — before
     * anything is persisted. Pure computation: no database writes happen
     * here, matching `DassScoringService`/`AIService` themselves having no
     * persistence dependency. `AssessmentWizardController` caches this
     * result in session so revisiting or refreshing the review page
     * doesn't re-invoke the AI provider (and, for the Claude provider,
     * re-spend an API call) on every request — the review shown is
     * exactly what gets saved, regardless of anything that changes
     * between review and save (e.g. an admin overriding thresholds).
     *
     * @param  array<int, int>  $responses  Question ID => answer value (0-3).
     * @return array{
     *     scores: array{depression_raw_score: int, anxiety_raw_score: int, stress_raw_score: int, depression_final_score: int, anxiety_final_score: int, stress_final_score: int},
     *     depression_level: string, anxiety_level: string, stress_level: string,
     *     ai_provider: string, used_non_official_thresholds: bool
     * }
     */
    public function reviewAssessment(QuestionnaireVersion $version, array $responses): array
    {
        $scores = $this->scoringService->score($version, $responses);

        $classification = $this->aiService->classify(new AssessmentPayload(
            depressionFinalScore: $scores['depression_final_score'],
            anxietyFinalScore: $scores['anxiety_final_score'],
            stressFinalScore: $scores['stress_final_score'],
        ));

        return [
            'scores' => $scores,
            'depression_level' => $classification->depressionLevel,
            'anxiety_level' => $classification->anxietyLevel,
            'stress_level' => $classification->stressLevel,
            'ai_provider' => $classification->provider,
            'used_non_official_thresholds' => $this->thresholdService->isOverridden(),
        ];
    }

    /**
     * Register the student and persist their completed, Psychometrician-
     * reviewed assessment inside a single transaction: create the
     * student, save the assessment and its responses, save the AI's raw
     * classification exactly as reviewed (never re-classified here),
     * record the review decision via the Feedback Loop, then evaluate and
     * create any differentiated flagged cases/notifications against the
     * *effective* severity (the reviewer's correction where one was
     * given, the AI's raw level otherwise) — never the AI's raw output
     * directly, so a Guidance Counselor is only ever notified about the
     * reviewed, final classification. Registering the student here —
     * rather than earlier in the wizard — means an abandoned wizard run
     * never leaves an orphan `students` row behind; this now also holds
     * for the review step itself, since nothing is written until this
     * method runs.
     *
     * `$review` is the cached output of `reviewAssessment()` — the AI's
     * classification the Psychometrician actually saw and reviewed.
     * `$feedbackData` is the validated Confirm/Correct submission
     * (`PredictionFeedbackFormRequest`'s shape: `is_confirmed`,
     * `corrected_{subscale}_level`, `notes`) — passed straight through to
     * `PredictionFeedbackService::submit()`, the same service and table
     * used by the standalone post-save Feedback Loop, so a mandatory
     * pre-save review and an optional later revision are recorded
     * identically.
     *
     * `$existingStudent` is only passed by the "Take Again" retake flow
     * (see `AssessmentWizardController::startRetake()`), to attach a new
     * assessment to an already-registered student instead of always
     * minting a new one. The regular New Assessment wizard never passes
     * it, so its "always a fresh student" behavior is unchanged.
     * `$privacyConsentAt` is likewise retake-only — the regular flow's
     * consent is captured on the `students` row at Step 1, not here.
     *
     * @param  array<string, mixed>  $studentData
     * @param  array<int, int>  $responses  Question ID => answer value (0-3).
     * @param  array<string, mixed>  $review  The cached return value of `reviewAssessment()`.
     * @param  array<string, mixed>  $feedbackData  Validated `PredictionFeedbackFormRequest` data.
     */
    public function save(
        array $studentData,
        QuestionnaireVersion $version,
        User $psychometrician,
        array $responses,
        array $review,
        array $feedbackData,
        ?Student $existingStudent = null,
        ?\DateTimeInterface $privacyConsentAt = null,
    ): Assessment {
        return $this->database->transaction(function () use ($studentData, $version, $psychometrician, $responses, $review, $feedbackData, $existingStudent, $privacyConsentAt): Assessment {
            $student = $existingStudent ?? $this->registerStudent($studentData);

            $assessment = Assessment::query()->create([
                'student_id' => $student->id,
                'questionnaire_version_id' => $version->id,
                'psychometrician_id' => $psychometrician->id,
                'status' => Assessment::STATUS_COMPLETED,
                'submitted_at' => now(),
                'privacy_consent_at' => $privacyConsentAt,
            ]);

            foreach ($responses as $questionId => $answerValue) {
                $assessment->responses()->create([
                    'dass_question_id' => $questionId,
                    'answer_value' => $answerValue,
                ]);
            }

            $result = DassResult::query()->create([
                'assessment_id' => $assessment->id,
                ...$review['scores'],
                'depression_level' => $review['depression_level'],
                'anxiety_level' => $review['anxiety_level'],
                'stress_level' => $review['stress_level'],
                'ai_provider' => $review['ai_provider'],
                'used_non_official_thresholds' => $review['used_non_official_thresholds'],
            ]);

            $this->predictionFeedbackService->submit($assessment, $psychometrician, $feedbackData);

            $isConfirmed = (bool) $feedbackData['is_confirmed'];

            $effectiveResult = new DassResult([
                'depression_level' => $isConfirmed ? $result->depression_level : ($feedbackData['corrected_depression_level'] ?? $result->depression_level),
                'anxiety_level' => $isConfirmed ? $result->anxiety_level : ($feedbackData['corrected_anxiety_level'] ?? $result->anxiety_level),
                'stress_level' => $isConfirmed ? $result->stress_level : ($feedbackData['corrected_stress_level'] ?? $result->stress_level),
            ]);

            $this->flaggedCaseService->evaluateAndFlag($assessment, $effectiveResult);

            return $assessment->refresh();
        });
    }
}
