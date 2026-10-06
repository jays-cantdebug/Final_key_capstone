<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Assessment;
use App\Models\ClassificationThreshold;
use App\Models\CounselingSession;
use App\Models\Course;
use App\Models\DassResult;
use App\Models\FlaggedCase;
use App\Models\Student;
use App\Models\YearLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Builds the underlying data for every named report. This service only
 * gathers data via Eloquent aggregate methods and query builders; it
 * performs no rendering and has no knowledge of PDF/print output.
 */
class ReportService
{
    /**
     * Assessment Summary Report severity-count bases (`countsBasis`).
     */
    public const COUNTS_BASIS_REVIEWED = 'reviewed';

    public const COUNTS_BASIS_AI = 'ai';

    /**
     * Assessment Report: eager-load a single assessment for the report.
     */
    public function loadAssessmentForReport(Assessment $assessment): Assessment
    {
        return $assessment->load([
            'student.course',
            'student.yearLevel',
            'student.section',
            'questionnaireVersion.questionnaire',
            'result',
            'predictionFeedback',
            'responses.question',
            'psychometrician',
        ]);
    }

    /**
     * Student Assessment History Report: a student and their full
     * assessment history, optionally scoped to a date range.
     *
     * @return array{student: ?Student, assessments: Collection<int, Assessment>}
     */
    public function studentHistoryForReport(?string $studentNumber, ?string $dateFrom, ?string $dateTo): array
    {
        $student = $studentNumber
            ? Student::query()->where('student_number', $studentNumber)->first()
            : null;

        if ($student === null) {
            return ['student' => null, 'assessments' => new Collection];
        }

        $assessments = Assessment::query()
            ->where('student_id', $student->id)
            ->with(['result', 'predictionFeedback'])
            ->when($dateFrom, fn (Builder $q, string $v) => $q->whereDate('submitted_at', '>=', $v))
            ->when($dateTo, fn (Builder $q, string $v) => $q->whereDate('submitted_at', '<=', $v))
            ->orderByDesc('submitted_at')
            ->get();

        return ['student' => $student, 'assessments' => $assessments];
    }

    /**
     * Flagged Students Report: flagged cases matching the given filters
     * (mirrors Flagged Cases' own filter set from Module 8), optionally
     * narrowed to one flag type (`FlaggedCase::FLAG_TYPE_*`).
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, FlaggedCase>
     */
    public function flaggedCasesForReport(array $filters): Collection
    {
        return FlaggedCase::query()
            ->with(['assessment.student.course', 'assessment.student.yearLevel', 'assessment.student.section'])
            ->when($filters['search'] ?? null, function (Builder $query, string $value) {
                $query->whereHas('assessment.student', function (Builder $q) use ($value) {
                    $q->where('first_name', 'like', "%{$value}%")
                        ->orWhere('last_name', 'like', "%{$value}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$value}%"])
                        ->orWhereRaw("CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?", ["%{$value}%"]);
                });
            })
            ->when($filters['course_id'] ?? null, function (Builder $query, $value) {
                $query->whereHas('assessment.student', fn (Builder $q) => $q->where('course_id', $value));
            })
            ->when($filters['year_level_id'] ?? null, function (Builder $query, $value) {
                $query->whereHas('assessment.student', fn (Builder $q) => $q->where('year_level_id', $value));
            })
            ->when($filters['section_id'] ?? null, function (Builder $query, $value) {
                $query->whereHas('assessment.student', fn (Builder $q) => $q->where('section_id', $value));
            })
            ->when($filters['date_from'] ?? null, function (Builder $query, $value) {
                $query->whereHas('assessment', fn (Builder $q) => $q->whereDate('submitted_at', '>=', $value));
            })
            ->when($filters['date_to'] ?? null, function (Builder $query, $value) {
                $query->whereHas('assessment', fn (Builder $q) => $q->whereDate('submitted_at', '<=', $value));
            })
            ->when($filters['flag_type'] ?? null, function (Builder $query, string $value) {
                $query->where('flag_type', $value);

                // Mirrors the Flagged Cases listing's "Notification" tab,
                // which only shows assessments flagged for Awareness
                // Notification and NOT also Counseling Endorsement (the
                // listing gives Endorsement display priority when both
                // exist) — otherwise the report would include rows the
                // listing had excluded from the Notification view.
                if ($value === FlaggedCase::FLAG_TYPE_AWARENESS_NOTIFICATION) {
                    $query->whereDoesntHave('assessment.flaggedCases', function (Builder $q) {
                        $q->where('flag_type', FlaggedCase::FLAG_TYPE_COUNSELING_ENDORSEMENT);
                    });
                }
            })
            ->orderByDesc('flagged_at')
            ->get();
    }

    /**
     * Counseling Report: counseling sessions matching the given student
     * name filter, or scoped to one student via `student_id`. Session note redaction (Module 9's confidentiality
     * rule) is applied in the view, not here.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, CounselingSession>
     */
    public function counselingSessionsForReport(array $filters): Collection
    {
        return CounselingSession::query()
            ->with(['student', 'counselor', 'assessment'])
            ->when($filters['search'] ?? null, function (Builder $query, string $value) {
                $query->whereHas('student', function (Builder $q) use ($value) {
                    $q->where('first_name', 'like', "%{$value}%")
                        ->orWhere('last_name', 'like', "%{$value}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$value}%"])
                        ->orWhereRaw("CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?", ["%{$value}%"]);
                });
            })
            ->when($filters['student_id'] ?? null, fn (Builder $q, $v) => $q->where('student_id', $v))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('session_datetime', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('session_datetime', '<=', $v))
            ->orderByDesc('session_datetime')
            ->get();
    }

    /**
     * Assessment Summary Report: an institution-wide overview - totals
     * (students, assessments, counseling endorsements, awareness
     * notifications) plus a separate 5-tier severity breakdown for each of
     * Depression, Anxiety, and Stress, optionally scoped by course, year
     * level, gender, and/or date range.
     *
     * "Total Students" counts the distinct students behind the filtered
     * assessments (not every student in the system), so all four totals
     * describe the same filtered scope.
     *
     * `$reviewedLevels` picks the basis of the severity breakdown:
     *  - false (the Psychometrician's report): the AI's raw levels from
     *    `dass_results`, unchanged;
     *  - true (the Guidance Counselor's report, who must never see the AI's
     *    raw levels): the reviewed levels via `Assessment::effectiveLevel()`
     *    — the same rule flagging uses, so the Severe/Extremely Severe
     *    counts agree with the Endorsement/Notification totals. Costs one
     *    extra query (the `prediction_feedback` rows).
     * The flag totals come from `flagged_cases`, which always follow the
     * reviewed levels. `countsBasis` ("reviewed" / "ai") tells the views
     * which note to show.
     *
     * @param  array{course_id?: ?int, year_level_id?: ?int, gender?: ?string, date_from?: ?string, date_to?: ?string}  $filters
     * @return array{
     *     totalStudents: int,
     *     totalAssessments: int,
     *     counselingEndorsements: int,
     *     awarenessNotifications: int,
     *     depressionBySeverity: array<string, int>,
     *     anxietyBySeverity: array<string, int>,
     *     stressBySeverity: array<string, int>,
     *     countsBasis: string,
     *     courseId: ?int,
     *     yearLevelId: ?int,
     *     gender: ?string,
     *     dateFrom: ?string,
     *     dateTo: ?string,
     * }
     */
    public function assessmentSummaryData(array $filters, bool $reviewedLevels = false): array
    {
        $studentFilter = function (Builder $query) use ($filters): void {
            $query
                ->when($filters['course_id'] ?? null, fn (Builder $q, $v) => $q->where('course_id', $v))
                ->when($filters['year_level_id'] ?? null, fn (Builder $q, $v) => $q->where('year_level_id', $v))
                ->when($filters['gender'] ?? null, fn (Builder $q, $v) => $q->where('gender', $v));
        };

        $assessments = Assessment::query()
            ->whereHas('student', $studentFilter)
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('submitted_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('submitted_at', '<=', $v))
            ->get(['id']);

        $assessmentIds = $assessments->modelKeys();

        $totalAssessments = count($assessmentIds);

        $totalStudents = Assessment::query()
            ->whereIn('id', $assessmentIds)
            ->distinct('student_id')
            ->count('student_id');

        // One row of three levels per assessment that has a result.
        $levels = $reviewedLevels
            ? $assessments
                ->load(['result:id,assessment_id,depression_level,anxiety_level,stress_level', 'predictionFeedback'])
                ->filter(fn (Assessment $assessment): bool => $assessment->result !== null)
                ->map(fn (Assessment $assessment): array => [
                    'depression_level' => $assessment->effectiveLevel(FlaggedCase::SUBSCALE_DEPRESSION),
                    'anxiety_level' => $assessment->effectiveLevel(FlaggedCase::SUBSCALE_ANXIETY),
                    'stress_level' => $assessment->effectiveLevel(FlaggedCase::SUBSCALE_STRESS),
                ])
            : DassResult::query()
                ->whereIn('assessment_id', $assessmentIds)
                ->get(['depression_level', 'anxiety_level', 'stress_level']);

        $bySeverity = function (string $column) use ($levels): array {
            $counts = [];
            foreach (ClassificationThreshold::severityOrder() as $severity) {
                $counts[$severity] = $levels->where($column, $severity)->count();
            }

            return $counts;
        };

        $flaggedCases = FlaggedCase::query()->whereIn('assessment_id', $assessmentIds);

        return [
            'totalStudents' => $totalStudents,
            'totalAssessments' => $totalAssessments,
            'counselingEndorsements' => (clone $flaggedCases)->where('flag_type', FlaggedCase::FLAG_TYPE_COUNSELING_ENDORSEMENT)->count(),
            'awarenessNotifications' => (clone $flaggedCases)->where('flag_type', FlaggedCase::FLAG_TYPE_AWARENESS_NOTIFICATION)->count(),
            'depressionBySeverity' => $bySeverity('depression_level'),
            'anxietyBySeverity' => $bySeverity('anxiety_level'),
            'stressBySeverity' => $bySeverity('stress_level'),
            'countsBasis' => $reviewedLevels ? self::COUNTS_BASIS_REVIEWED : self::COUNTS_BASIS_AI,
            'courseId' => $filters['course_id'] ?? null,
            'yearLevelId' => $filters['year_level_id'] ?? null,
            'gender' => $filters['gender'] ?? null,
            'dateFrom' => $filters['date_from'] ?? null,
            'dateTo' => $filters['date_to'] ?? null,
        ];
    }

    /**
     * @return Collection<int, Course>
     */
    public function courseOptions(): Collection
    {
        return Course::query()->orderBy('course_code')->get();
    }

    /**
     * @return Collection<int, YearLevel>
     */
    public function yearLevelOptions(): Collection
    {
        return YearLevel::query()->orderBy('display_order')->get();
    }
}
