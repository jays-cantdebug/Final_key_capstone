<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Assessment;
use App\Models\ClassificationThreshold;
use App\Models\DassResult;
use App\Models\FlaggedCase;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Computes dashboard statistics using Eloquent aggregate methods, per the
 * Dashboard Standards requirement that dashboard cards never use
 * hardcoded values.
 */
class DashboardService
{
    private const SEVERE_LEVELS = [
        ClassificationThreshold::SEVERITY_SEVERE,
        ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE,
    ];

    /**
     * @return array{
     *     totalStudents: int, totalAssessments: int, todaysAssessments: int,
     *     flaggedStudentsSummary: int, recentAssessments: Collection<int, Assessment>,
     *     flaggedVolumeChart: array<int, array{label: string, count: int}>,
     *     flagTypeChart: array<string, int>
     * }
     */
    public function guidanceCounselorStats(): array
    {
        return [
            'totalStudents' => Student::query()->count(),
            'totalAssessments' => Assessment::query()->count(),
            'todaysAssessments' => Assessment::query()->whereDate('submitted_at', today())->count(),
            'flaggedStudentsSummary' => FlaggedCase::query()->where('status', FlaggedCase::STATUS_OPEN)->count(),
            'recentAssessments' => Assessment::query()
                ->with(['student', 'result'])
                ->latest('submitted_at')
                ->limit(5)
                ->get(),
            'flaggedVolumeChart' => $this->flaggedCaseVolumeSeries(),
            'flagTypeChart' => $this->flagTypeDistribution(),
        ];
    }

    /**
     * Flagged-assessment count per month for the last 6 months, zero-filled.
     * Counts distinct assessments with at least one flagged case (matching
     * the unit shown on the Flagged Cases listing), not raw flagged_cases
     * rows, since one assessment can produce up to 3 of those.
     *
     * @return array<int, array{label: string, count: int}>
     */
    private function flaggedCaseVolumeSeries(): array
    {
        $sixMonthsAgo = now()->subMonths(5)->startOfMonth();

        $counts = Assessment::query()
            ->whereHas('flaggedCases')
            ->where('submitted_at', '>=', $sixMonthsAgo)
            ->get(['submitted_at'])
            ->countBy(fn (Assessment $assessment): string => $assessment->submitted_at->format('Y-m'));

        return $this->zeroFilledSixMonthSeries($counts);
    }

    /**
     * Flagged assessments tallied by their priority flag type (Counseling
     * Endorsement takes priority over Awareness Notification when an
     * assessment has both, matching Assessment::priorityFlag() and the
     * Flagged Cases tab filters).
     *
     * @return array<string, int>
     */
    private function flagTypeDistribution(): array
    {
        $flagsByAssessment = FlaggedCase::query()
            ->select('assessment_id', 'flag_type')
            ->get()
            ->groupBy('assessment_id');

        $tallies = [
            FlaggedCase::FLAG_TYPE_COUNSELING_ENDORSEMENT => 0,
            FlaggedCase::FLAG_TYPE_AWARENESS_NOTIFICATION => 0,
        ];

        foreach ($flagsByAssessment as $flags) {
            $priorityType = $flags->contains('flag_type', FlaggedCase::FLAG_TYPE_COUNSELING_ENDORSEMENT)
                ? FlaggedCase::FLAG_TYPE_COUNSELING_ENDORSEMENT
                : FlaggedCase::FLAG_TYPE_AWARENESS_NOTIFICATION;

            $tallies[$priorityType]++;
        }

        return $tallies;
    }

    /**
     * Compute everything the Psychometrician Dashboard needs: the 4 stat
     * cards (with trend vs. the immediately preceding period of the same
     * length), the Assessment Volume and Severity Distribution charts,
     * and the paginated "All Assessments" table.
     *
     * @param array<string, mixed> $filters period, course_id, year_level_id, severity_subscale
     * @return array{
     *     period: string,
     *     cards: array<string, array{label: string, count: int, trend: ?float}>,
     *     volumeChart: array<int, array{label: string, count: int}>,
     *     severityChart: array<string, int>,
     *     assessments: LengthAwarePaginator<int, Assessment>
     * }
     */
    public function psychometricianStats(array $filters): array
    {
        $period = $filters['period'] ?? 'month';
        [$start, $end] = $this->periodRange($period);

        $baseQuery = fn (): Builder => Assessment::query()
            ->when($start, fn (Builder $q) => $q->whereBetween('submitted_at', [$start, $end]));

        $totalAssessments = (clone $baseQuery())->count();
        $subscaleCounts = [
            'stress' => $this->countBySubscale($baseQuery(), 'stress'),
            'anxiety' => $this->countBySubscale($baseQuery(), 'anxiety'),
            'depression' => $this->countBySubscale($baseQuery(), 'depression'),
        ];

        [$priorStart, $priorEnd] = $this->priorPeriodRange($period, $start, $end);
        $priorQuery = fn (): Builder => Assessment::query()
            ->when($priorStart, fn (Builder $q) => $q->whereBetween('submitted_at', [$priorStart, $priorEnd]));

        $priorTotal = $priorStart ? (clone $priorQuery())->count() : null;
        $priorSubscaleCounts = [
            'stress' => $priorStart ? $this->countBySubscale($priorQuery(), 'stress') : null,
            'anxiety' => $priorStart ? $this->countBySubscale($priorQuery(), 'anxiety') : null,
            'depression' => $priorStart ? $this->countBySubscale($priorQuery(), 'depression') : null,
        ];

        $cards = [
            'total' => [
                'label' => 'Total Assessment',
                'count' => $totalAssessments,
                'trend' => $this->trend($totalAssessments, $priorTotal),
            ],
            'stress' => [
                'label' => 'Total Stress',
                'count' => $subscaleCounts['stress'],
                'trend' => $this->trend($subscaleCounts['stress'], $priorSubscaleCounts['stress']),
            ],
            'anxiety' => [
                'label' => 'Total Anxiety',
                'count' => $subscaleCounts['anxiety'],
                'trend' => $this->trend($subscaleCounts['anxiety'], $priorSubscaleCounts['anxiety']),
            ],
            'depression' => [
                'label' => 'Total Depression',
                'count' => $subscaleCounts['depression'],
                'trend' => $this->trend($subscaleCounts['depression'], $priorSubscaleCounts['depression']),
            ],
        ];

        $assessmentIds = (clone $baseQuery())->pluck('id');

        return [
            'period' => $period,
            'cards' => $cards,
            'volumeChart' => $this->assessmentVolumeSeries(),
            'severityChart' => $this->severityDistribution($assessmentIds),
            'assessments' => $this->allAssessmentsTable($baseQuery(), $filters),
        ];
    }

    private function countBySubscale(Builder $query, string $subscale): int
    {
        return $query->whereHas('result', fn (Builder $q) => $q->whereIn("{$subscale}_level", self::SEVERE_LEVELS))->count();
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function periodRange(string $period): array
    {
        $now = now();

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            default => [null, null],
        };
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function priorPeriodRange(string $period, ?Carbon $start, ?Carbon $end): array
    {
        if ($start === null || $end === null) {
            return [null, null];
        }

        return match ($period) {
            'today' => [$start->copy()->subDay(), $end->copy()->subDay()],
            'week' => [$start->copy()->subWeek(), $end->copy()->subWeek()],
            'month' => [$start->copy()->subMonthNoOverflow()->startOfMonth(), $start->copy()->subMonthNoOverflow()->endOfMonth()],
            default => [null, null],
        };
    }

    /**
     * Percentage change vs. the prior period. Null when there is no prior
     * period to compare against (period = "all") or the prior count was
     * zero (a percentage change is meaningless from a zero baseline).
     */
    private function trend(int $current, ?int $prior): ?float
    {
        if ($prior === null || $prior === 0) {
            return null;
        }

        return round((($current - $prior) / $prior) * 100, 1);
    }

    /**
     * Assessment count per month for the last 6 months, zero-filled.
     *
     * @return array<int, array{label: string, count: int}>
     */
    private function assessmentVolumeSeries(): array
    {
        $sixMonthsAgo = now()->subMonths(5)->startOfMonth();

        $counts = Assessment::query()
            ->where('submitted_at', '>=', $sixMonthsAgo)
            ->get(['submitted_at'])
            ->countBy(fn (Assessment $assessment): string => $assessment->submitted_at->format('Y-m'));

        return $this->zeroFilledSixMonthSeries($counts);
    }

    /**
     * Zero-fills a "Y-m" => count tally into the last 6 months in
     * chronological order, for the volume-chart series.
     *
     * @param \Illuminate\Support\Collection<string, int> $counts
     * @return array<int, array{label: string, count: int}>
     */
    private function zeroFilledSixMonthSeries(\Illuminate\Support\Collection $counts): array
    {
        $series = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $key = $month->format('Y-m');

            $series[] = [
                'label' => $month->format('M'),
                'count' => (int) ($counts[$key] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Breakdown of assessments (within the current filter) by their
     * highest severity tier, ordered Normal -> Extremely Severe.
     *
     * @param \Illuminate\Support\Collection<int, int> $assessmentIds
     * @return array<string, int>
     */
    private function severityDistribution(\Illuminate\Support\Collection $assessmentIds): array
    {
        $tallies = DassResult::query()
            ->whereIn('assessment_id', $assessmentIds)
            ->get(['depression_level', 'anxiety_level', 'stress_level'])
            ->countBy(fn (DassResult $result): string => $result->highestSeverityLevel());

        $orderedSeverities = [
            ClassificationThreshold::SEVERITY_NORMAL,
            ClassificationThreshold::SEVERITY_MILD,
            ClassificationThreshold::SEVERITY_MODERATE,
            ClassificationThreshold::SEVERITY_SEVERE,
            ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE,
        ];

        $ordered = [];
        foreach ($orderedSeverities as $severity) {
            $ordered[$severity] = $tallies[$severity] ?? 0;
        }

        return $ordered;
    }

    /**
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, Assessment>
     */
    private function allAssessmentsTable(Builder $query, array $filters): LengthAwarePaginator
    {
        return $query
            ->with(['student.course', 'student.yearLevel', 'result', 'flaggedCases'])
            ->when($filters['course_id'] ?? null, function (Builder $q, $value) {
                $q->whereHas('student', fn (Builder $qq) => $qq->where('course_id', $value));
            })
            ->when($filters['year_level_id'] ?? null, function (Builder $q, $value) {
                $q->whereHas('student', fn (Builder $qq) => $qq->where('year_level_id', $value));
            })
            ->when($filters['severity_subscale'] ?? null, function (Builder $q, string $subscale) {
                $q->whereHas('result', fn (Builder $qq) => $qq->whereIn("{$subscale}_level", self::SEVERE_LEVELS));
            })
            ->orderByDesc('submitted_at')
            ->paginate(10)
            ->withQueryString();
    }
}
