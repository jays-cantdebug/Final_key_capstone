<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Assessment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Read-only browsing of past assessments (Assessment History). This is
 * intentionally separate from AssessmentService (Module 6), which owns
 * the New Assessment workflow and its persistence; this service only
 * queries already-completed assessments.
 */
class AssessmentHistoryService
{
    /**
     * Paginate assessment history, most recently submitted first,
     * optionally filtered by student number.
     */
    public function paginate(?string $studentNumber, int $perPage = 10): LengthAwarePaginator
    {
        return Assessment::query()
            ->with(['student.course', 'student.yearLevel', 'student.section', 'result'])
            ->when($studentNumber, function ($query, string $studentNumber) {
                $query->whereHas('student', function ($studentQuery) use ($studentNumber): void {
                    $studentQuery->where('student_number', 'like', "%{$studentNumber}%");
                });
            })
            ->orderByDesc('submitted_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
