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
     * optionally filtered by student name (the user-facing search) and/or
     * an exact student number (used only for deep links from a specific
     * student's profile, never typed manually).
     */
    public function paginate(?string $search, ?string $studentNumber = null, int $perPage = 10): LengthAwarePaginator
    {
        return Assessment::query()
            ->with(['student.course', 'student.yearLevel', 'student.section', 'result'])
            ->when($studentNumber, function ($query, string $studentNumber) {
                $query->whereHas('student', fn ($q) => $q->where('student_number', $studentNumber));
            })
            ->when($search, function ($query, string $search) {
                $query->whereHas('student', function ($studentQuery) use ($search): void {
                    $studentQuery->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                        ->orWhereRaw("CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                });
            })
            ->orderByDesc('submitted_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
