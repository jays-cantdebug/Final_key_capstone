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
 * student, loading the active questionnaire version, and persisting the
 * completed assessment (responses + computed scores + AI classification +
 * differentiated flags/notifications) inside a single database
 * transaction.
 */
class AssessmentService
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly DassScoringService $scoringService,
        private readonly AIService $aiService,
        private readonly FlaggedCaseService $flaggedCaseService,
        private readonly StudentNumberGeneratorService $studentNumberGenerator,
        private readonly ClassificationThresholdService $thresholdService,
    ) {
    }

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
     * @param array<string, mixed> $data
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
     * Register the student and submit their completed assessment inside a
     * single transaction: create the student, save the assessment and its
     * responses, compute the DASS-21 scores, pass them to the AI Service
     * for classification, save the results, then evaluate and create any
     * differentiated flagged cases/notifications. Registering the student
     * here — rather than earlier in the wizard — means an abandoned
     * wizard run never leaves an orphan `students` row behind.
     *
     * @param array<string, mixed> $studentData
     * @param array<int, int> $responses Question ID => answer value (0-3).
     */
    public function submit(
        array $studentData,
        QuestionnaireVersion $version,
        User $psychometrician,
        array $responses
    ): Assessment {
        return $this->database->transaction(function () use ($studentData, $version, $psychometrician, $responses): Assessment {
            $student = $this->registerStudent($studentData);

            $assessment = Assessment::query()->create([
                'student_id' => $student->id,
                'questionnaire_version_id' => $version->id,
                'psychometrician_id' => $psychometrician->id,
                'status' => Assessment::STATUS_COMPLETED,
                'submitted_at' => now(),
            ]);

            foreach ($responses as $questionId => $answerValue) {
                $assessment->responses()->create([
                    'dass_question_id' => $questionId,
                    'answer_value' => $answerValue,
                ]);
            }

            $scores = $this->scoringService->score($version, $responses);

            $classification = $this->aiService->classify(new AssessmentPayload(
                assessmentId: $assessment->id,
                depressionFinalScore: $scores['depression_final_score'],
                anxietyFinalScore: $scores['anxiety_final_score'],
                stressFinalScore: $scores['stress_final_score'],
            ));

            $result = DassResult::query()->create([
                'assessment_id' => $assessment->id,
                ...$scores,
                'depression_level' => $classification->depressionLevel,
                'anxiety_level' => $classification->anxietyLevel,
                'stress_level' => $classification->stressLevel,
                'ai_provider' => $classification->provider,
                'used_non_official_thresholds' => $this->thresholdService->isOverridden(),
            ]);

            $this->flaggedCaseService->evaluateAndFlag($assessment, $result);

            return $assessment->refresh();
        });
    }
}
