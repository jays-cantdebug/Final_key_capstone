<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Assessment;
use App\Models\ClassificationThreshold;
use App\Models\CounselingSession;
use App\Models\DassResult;
use App\Models\FlaggedCase;
use App\Models\QuestionnaireVersion;
use App\Models\Student;
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
            return ['student' => null, 'assessments' => new Collection()];
        }

        $assessments = Assessment::query()
            ->where('student_id', $student->id)
            ->with('result')
            ->when($dateFrom, fn (Builder $q, string $v) => $q->whereDate('submitted_at', '>=', $v))
            ->when($dateTo, fn (Builder $q, string $v) => $q->whereDate('submitted_at', '<=', $v))
            ->orderByDesc('submitted_at')
            ->get();

        return ['student' => $student, 'assessments' => $assessments];
    }

    /**
     * Flagged Students Report: flagged cases matching the given filters
     * (mirrors Flagged Cases' own filter set from Module 8).
     *
     * @param array<string, mixed> $filters
     * @return Collection<int, FlaggedCase>
     */
    public function flaggedCasesForReport(array $filters): Collection
    {
        return FlaggedCase::query()
            ->with(['assessment.student.course', 'assessment.student.yearLevel', 'assessment.student.section'])
            ->when($filters['student_number'] ?? null, function (Builder $query, string $value) {
                $query->whereHas('assessment.student', fn (Builder $q) => $q->where('student_number', 'like', "%{$value}%"));
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
            ->orderByDesc('flagged_at')
            ->get();
    }

    /**
     * Counseling Report: counseling sessions matching the given student
     * number filter. Session note redaction (Module 9's confidentiality
     * rule) is applied in the view, not here.
     *
     * @param array<string, mixed> $filters
     * @return Collection<int, CounselingSession>
     */
    public function counselingSessionsForReport(array $filters): Collection
    {
        return CounselingSession::query()
            ->with(['student', 'counselor', 'assessment'])
            ->when($filters['student_number'] ?? null, function (Builder $query, string $value) {
                $query->whereHas('student', fn (Builder $q) => $q->where('student_number', 'like', "%{$value}%"));
            })
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('session_datetime', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('session_datetime', '<=', $v))
            ->orderByDesc('session_datetime')
            ->get();
    }

    /**
     * Questionnaire Usage Report: every questionnaire version with its
     * question count and the number of assessments that used it.
     *
     * Computed via a direct count query per version (rather than adding
     * an `assessments()` relation to QuestionnaireVersion) so Module 4
     * remains untouched.
     *
     * @return Collection<int, QuestionnaireVersion>
     */
    public function questionnaireUsageForReport(): Collection
    {
        return QuestionnaireVersion::query()
            ->with('questionnaire')
            ->withCount('questions')
            ->orderByDesc('version_number')
            ->get()
            ->map(function (QuestionnaireVersion $version): QuestionnaireVersion {
                $version->setAttribute(
                    'assessments_count',
                    Assessment::query()->where('questionnaire_version_id', $version->id)->count()
                );

                return $version;
            });
    }

    /**
     * Daily Assessment Report: all assessments submitted on the given date.
     */
    public function dailyAssessmentsQuery(string $date): Builder
    {
        return Assessment::query()
            ->with(['student', 'result', 'psychometrician'])
            ->whereDate('submitted_at', $date)
            ->orderBy('submitted_at');
    }

    /**
     * Monthly Assessment Report: all assessments submitted in the given
     * year/month.
     */
    public function monthlyAssessmentsQuery(int $year, int $month): Builder
    {
        return Assessment::query()
            ->with(['student', 'result', 'psychometrician'])
            ->whereYear('submitted_at', $year)
            ->whereMonth('submitted_at', $month)
            ->orderBy('submitted_at');
    }

    /**
     * Assessment Summary Report: aggregate counts over an optional date
     * range - total assessments, counts by overall severity, and the
     * flagged count.
     *
     * @return array{total: int, bySeverity: array<string, int>, flaggedCount: int, dateFrom: ?string, dateTo: ?string}
     */
    public function assessmentSummaryData(?string $dateFrom, ?string $dateTo): array
    {
        $assessmentIds = Assessment::query()
            ->when($dateFrom, fn (Builder $q, string $v) => $q->whereDate('submitted_at', '>=', $v))
            ->when($dateTo, fn (Builder $q, string $v) => $q->whereDate('submitted_at', '<=', $v))
            ->pluck('id');

        $total = $assessmentIds->count();

        $bySeverity = DassResult::query()
            ->whereIn('assessment_id', $assessmentIds)
            ->selectRaw('overall_status, count(*) as total')
            ->groupBy('overall_status')
            ->pluck('total', 'overall_status')
            ->all();

        $orderedSeverities = [
            ClassificationThreshold::SEVERITY_NORMAL,
            ClassificationThreshold::SEVERITY_MILD,
            ClassificationThreshold::SEVERITY_MODERATE,
            ClassificationThreshold::SEVERITY_SEVERE,
            ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE,
        ];

        $bySeverityOrdered = [];
        foreach ($orderedSeverities as $severity) {
            $bySeverityOrdered[$severity] = $bySeverity[$severity] ?? 0;
        }

        $flaggedCount = FlaggedCase::query()->whereIn('assessment_id', $assessmentIds)->count();

        return [
            'total' => $total,
            'bySeverity' => $bySeverityOrdered,
            'flaggedCount' => $flaggedCount,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ];
    }
}
