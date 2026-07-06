<?php

declare(strict_types=1);

namespace App\Services;

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
 * completed assessment (responses + computed DASS-21 results) inside a
 * single database transaction.
 */
class AssessmentService
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly DassScoringService $scoringService,
        private readonly FlaggedCaseService $flaggedCaseService,
    ) {
    }

    public function findStudentByNumber(string $studentNumber): ?Student
    {
        return Student::query()->where('student_number', $studentNumber)->first();
    }

    public function findStudentOrFail(int $id): Student
    {
        return Student::query()->findOrFail($id);
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
     * Register a new student for this assessment, recording privacy
     * consent immediately. The legacy `sex` column is auto-populated by
     * mirroring `gender`, since it remains NOT NULL and is otherwise
     * unused going forward.
     *
     * @param array<string, mixed> $data
     */
    public function registerStudent(array $data): Student
    {
        return $this->database->transaction(function () use ($data): Student {
            return Student::query()->create([
                ...$data,
                'sex' => $data['gender'],
                'status' => Student::STATUS_ACTIVE,
                'privacy_consent_at' => now(),
            ]);
        });
    }

    /**
     * Record consent for an existing student who has not yet consented.
     */
    public function recordConsentIfMissing(Student $student): Student
    {
        if ($student->privacy_consent_at === null) {
            $this->database->transaction(function () use ($student): void {
                $student->update(['privacy_consent_at' => now()]);
            });
        }

        return $student->refresh();
    }

    public function activeQuestionnaireVersion(): ?QuestionnaireVersion
    {
        return QuestionnaireVersion::query()
            ->where('status', QuestionnaireVersion::STATUS_ACTIVE)
            ->with('questions')
            ->first();
    }

    /**
     * Submit a completed assessment: save the assessment, its responses,
     * and the computed DASS-21 results inside a single transaction.
     *
     * @param array<int, int> $responses Question ID => answer value (0-3).
     */
    public function submit(
        Student $student,
        QuestionnaireVersion $version,
        User $psychometrician,
        array $responses
    ): Assessment {
        return $this->database->transaction(function () use ($student, $version, $psychometrician, $responses): Assessment {
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

            DassResult::query()->create([
                'assessment_id' => $assessment->id,
                ...$scores,
            ]);

            if ($scores['overall_flag']) {
                $this->flaggedCaseService->createForFlaggedAssessment($assessment, $scores);
            }

            return $assessment->refresh();
        });
    }
}
