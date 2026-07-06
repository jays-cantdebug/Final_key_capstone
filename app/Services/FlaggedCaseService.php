<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\FlaggedCase;
use App\Models\Section;
use App\Models\User;
use App\Models\YearLevel;
use App\Notifications\FlaggedAssessmentNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Creates Flagged Cases for assessments that meet or exceed the
 * Notification Severity Threshold, notifies all Guidance Counselor
 * accounts, and supports the Flagged Students listing/search/filter
 * feature.
 */
class FlaggedCaseService
{
    public function __construct(private readonly DatabaseManager $database)
    {
    }

    /**
     * Create a Flagged Case for a newly-submitted, flagged assessment and
     * notify every active Guidance Counselor. Idempotent: does nothing if
     * a Flagged Case already exists for this assessment.
     *
     * @param array{overall_status: string} $scores
     */
    public function createForFlaggedAssessment(Assessment $assessment, array $scores): FlaggedCase
    {
        return $this->database->transaction(function () use ($assessment, $scores): FlaggedCase {
            $flaggedCase = FlaggedCase::query()->firstOrCreate(
                ['assessment_id' => $assessment->id],
                [
                    'highest_severity' => $scores['overall_status'],
                    'status' => FlaggedCase::STATUS_OPEN,
                    'flagged_at' => now(),
                ]
            );

            $guidanceCounselors = User::query()
                ->where('is_active', true)
                ->whereHas('role', fn ($query) => $query->where('name', 'guidance_counselor'))
                ->get();

            Notification::send($guidanceCounselors, new FlaggedAssessmentNotification($assessment));

            return $flaggedCase;
        });
    }

    /**
     * Paginate flagged cases, most recently flagged first, with optional
     * search/filter by student number, course, year level, section, and
     * assessment date range.
     *
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return FlaggedCase::query()
            ->with(['assessment.student.course', 'assessment.student.yearLevel', 'assessment.student.section'])
            ->when($filters['student_number'] ?? null, function ($query, string $value) {
                $query->whereHas('assessment.student', fn ($q) => $q->where('student_number', 'like', "%{$value}%"));
            })
            ->when($filters['course_id'] ?? null, function ($query, $value) {
                $query->whereHas('assessment.student', fn ($q) => $q->where('course_id', $value));
            })
            ->when($filters['year_level_id'] ?? null, function ($query, $value) {
                $query->whereHas('assessment.student', fn ($q) => $q->where('year_level_id', $value));
            })
            ->when($filters['section_id'] ?? null, function ($query, $value) {
                $query->whereHas('assessment.student', fn ($q) => $q->where('section_id', $value));
            })
            ->when($filters['date_from'] ?? null, function ($query, $value) {
                $query->whereHas('assessment', fn ($q) => $q->whereDate('submitted_at', '>=', $value));
            })
            ->when($filters['date_to'] ?? null, function ($query, $value) {
                $query->whereHas('assessment', fn ($q) => $q->whereDate('submitted_at', '<=', $value));
            })
            ->orderByDesc('flagged_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Collection<int, Course>
     */
    public function allCourses(): Collection
    {
        return Course::query()->orderBy('course_code')->get();
    }

    /**
     * @return Collection<int, YearLevel>
     */
    public function allYearLevels(): Collection
    {
        return YearLevel::query()->orderBy('display_order')->get();
    }

    /**
     * @return Collection<int, Section>
     */
    public function allSections(): Collection
    {
        return Section::query()->orderBy('section_name')->get();
    }
}
