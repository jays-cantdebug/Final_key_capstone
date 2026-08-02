<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\DassResult;
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
 * Evaluates a completed assessment's DASS-21 result against the
 * differentiated flagging rules and creates the appropriate Flagged Case
 * row(s), notifying Guidance Counselors accordingly. Also supports the
 * Flagged Students listing/search/filter feature.
 */
class FlaggedCaseService
{
    public function __construct(private readonly DatabaseManager $database)
    {
    }

    /**
     * Evaluate each subscale independently and create a Flagged Case for
     * every one that meets or exceeds Severe:
     *   - Stress Severe/Extremely Severe -> Counseling Endorsement.
     *   - Depression and/or Anxiety Severe/Extremely Severe -> Awareness
     *     Notification (evaluated independently of Stress and of each
     *     other, so a single assessment may produce up to three rows).
     * Idempotent per (assessment, flag_type, triggering_subscale); only
     * newly-created rows trigger a notification.
     *
     * @return Collection<int, FlaggedCase>
     */
    public function evaluateAndFlag(Assessment $assessment, DassResult $result): Collection
    {
        return $this->database->transaction(function () use ($assessment, $result): Collection {
            $candidates = [
                [
                    'level' => $result->stress_level,
                    'flag_type' => FlaggedCase::FLAG_TYPE_COUNSELING_ENDORSEMENT,
                    'triggering_subscale' => FlaggedCase::SUBSCALE_STRESS,
                ],
                [
                    'level' => $result->depression_level,
                    'flag_type' => FlaggedCase::FLAG_TYPE_AWARENESS_NOTIFICATION,
                    'triggering_subscale' => FlaggedCase::SUBSCALE_DEPRESSION,
                ],
                [
                    'level' => $result->anxiety_level,
                    'flag_type' => FlaggedCase::FLAG_TYPE_AWARENESS_NOTIFICATION,
                    'triggering_subscale' => FlaggedCase::SUBSCALE_ANXIETY,
                ],
            ];

            $flaggedCases = Collection::make();

            foreach ($candidates as $candidate) {
                if (! in_array($candidate['level'], FlaggedCase::SEVERE_LEVELS, true)) {
                    continue;
                }

                $flaggedCase = FlaggedCase::query()->firstOrCreate(
                    [
                        'assessment_id' => $assessment->id,
                        'flag_type' => $candidate['flag_type'],
                        'triggering_subscale' => $candidate['triggering_subscale'],
                    ],
                    [
                        'status' => FlaggedCase::STATUS_OPEN,
                        'flagged_at' => now(),
                    ]
                );

                $flaggedCases->push($flaggedCase);

                if ($flaggedCase->wasRecentlyCreated) {
                    $this->notifyGuidanceCounselors($flaggedCase);
                }
            }

            return $flaggedCases;
        });
    }

    private function notifyGuidanceCounselors(FlaggedCase $flaggedCase): void
    {
        $guidanceCounselors = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('name', 'guidance_counselor'))
            ->get();

        Notification::send($guidanceCounselors, new FlaggedAssessmentNotification($flaggedCase));
    }

    /**
     * Paginate assessments for the Flagged Students listing — one row per
     * assessment (not per flagged_cases row), most recent first, with
     * optional search/filter by student number, course, year level,
     * section, and assessment date range, plus the All/Endorsement/
     * Notification/Normal tab filter.
     *
     * `flaggedCases` is eager-loaded so `Assessment::priorityFlag()`/
     * `secondaryFlagCount()` never issue an extra query per row.
     *
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $tab = $filters['tab'] ?? 'all';

        return Assessment::query()
            ->with(['student.course', 'student.yearLevel', 'student.section', 'result', 'flaggedCases'])
            ->when($tab === 'endorsement', function ($query) {
                $query->whereHas('flaggedCases', fn ($q) => $q->where('flag_type', FlaggedCase::FLAG_TYPE_COUNSELING_ENDORSEMENT));
            })
            ->when($tab === 'notification', function ($query) {
                $query->whereHas('flaggedCases', fn ($q) => $q->where('flag_type', FlaggedCase::FLAG_TYPE_AWARENESS_NOTIFICATION))
                    ->whereDoesntHave('flaggedCases', fn ($q) => $q->where('flag_type', FlaggedCase::FLAG_TYPE_COUNSELING_ENDORSEMENT));
            })
            ->when($tab === 'normal', function ($query) {
                $query->whereDoesntHave('flaggedCases');
            })
            ->when($filters['search'] ?? null, function ($query, string $value) {
                $query->whereHas('student', function ($q) use ($value) {
                    $q->where('first_name', 'like', "%{$value}%")
                        ->orWhere('last_name', 'like', "%{$value}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$value}%"])
                        ->orWhereRaw("CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?", ["%{$value}%"]);
                });
            })
            ->when($filters['course_id'] ?? null, function ($query, $value) {
                $query->whereHas('student', fn ($q) => $q->where('course_id', $value));
            })
            ->when($filters['year_level_id'] ?? null, function ($query, $value) {
                $query->whereHas('student', fn ($q) => $q->where('year_level_id', $value));
            })
            ->when($filters['section_id'] ?? null, function ($query, $value) {
                $query->whereHas('student', fn ($q) => $q->where('section_id', $value));
            })
            ->when($filters['date_from'] ?? null, fn ($query, $value) => $query->whereDate('submitted_at', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($query, $value) => $query->whereDate('submitted_at', '<=', $value))
            ->orderByDesc('submitted_at')
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
