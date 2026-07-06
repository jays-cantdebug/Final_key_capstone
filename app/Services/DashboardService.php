<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Assessment;
use App\Models\FlaggedCase;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;

/**
 * Computes dashboard statistics using Eloquent aggregate methods, per the
 * Dashboard Standards requirement that dashboard cards never use
 * hardcoded values.
 */
class DashboardService
{
    /**
     * @return array{
     *     totalStudents: int, totalAssessments: int, todaysAssessments: int,
     *     flaggedStudentsSummary: int, recentAssessments: Collection<int, Assessment>
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
        ];
    }
}
