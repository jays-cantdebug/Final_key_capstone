<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CounselingSession;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Per-student view over Counseling Sessions: one summary row per student
 * (session count, last Completed session, next Scheduled session, and a
 * derived follow-up status) plus a single student's full session history.
 *
 * Follow-up status is derived, never stored:
 *  - "needed"  — the student's latest Completed session has
 *                follow_up_required = true, and no Scheduled/Completed
 *                session exists after it;
 *  - "overdue" — as "needed", and that session's follow_up_date is
 *                already in the past;
 *  - "none"    — otherwise.
 * Cancelled and No-Show sessions never count as the "last session" and
 * never satisfy a pending follow-up.
 *
 * Everything is computed in SQL (correlated subqueries), so the listing
 * can be filtered, sorted, and paginated by follow-up status without an
 * N+1 per student. Only unencrypted columns are touched — session_notes
 * is never read here.
 */
class StudentCounselingHistoryService
{
    public const FOLLOW_UP_NONE = 'none';

    public const FOLLOW_UP_NEEDED = 'needed';

    public const FOLLOW_UP_OVERDUE = 'overdue';

    /**
     * Paginate students who have at least one counseling session, most
     * urgent follow-up first, optionally filtered by student name and/or
     * follow-up status ("needed" includes overdue; "overdue" is overdue
     * only).
     */
    public function paginate(?string $search, ?string $followUp, int $perPage = 10): LengthAwarePaginator
    {
        $statusSql = $this->followUpStatusSql();
        $today = now()->toDateString();

        return $this->summaryQuery()
            ->whereHas('counselingSessions')
            ->when($search, function (Builder $query, string $value) {
                $query->where(function (Builder $q) use ($value) {
                    $q->where('first_name', 'like', "%{$value}%")
                        ->orWhere('last_name', 'like', "%{$value}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$value}%"])
                        ->orWhereRaw("CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?", ["%{$value}%"]);
                });
            })
            ->when($followUp === self::FOLLOW_UP_NEEDED, fn (Builder $q) => $q->whereRaw(
                "({$statusSql}) IN (?, ?)",
                [$today, self::FOLLOW_UP_OVERDUE, self::FOLLOW_UP_NEEDED]
            ))
            ->when($followUp === self::FOLLOW_UP_OVERDUE, fn (Builder $q) => $q->whereRaw(
                "({$statusSql}) = ?",
                [$today, self::FOLLOW_UP_OVERDUE]
            ))
            ->orderByRaw(
                "CASE ({$statusSql}) WHEN ? THEN 0 WHEN ? THEN 1 ELSE 2 END",
                [$today, self::FOLLOW_UP_OVERDUE, self::FOLLOW_UP_NEEDED]
            )
            ->orderByDesc('last_session_at')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * The summary row for one student (with the same aggregate attributes
     * as a paginate() row), or null if the student has no sessions.
     */
    public function summaryFor(Student $student): ?Student
    {
        return $this->summaryQuery()
            ->whereHas('counselingSessions')
            ->whereKey($student->getKey())
            ->first();
    }

    /**
     * A student's full counseling history, most recent first.
     *
     * @return Collection<int, CounselingSession>
     */
    public function sessionsFor(Student $student): Collection
    {
        return $student->counselingSessions()
            ->with(['counselor', 'assessment.result', 'assessment.predictionFeedback'])
            ->orderByDesc('session_datetime')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Students (archived included — their history must stay viewable)
     * with the aggregate summary columns:
     *  counseling_sessions_count, last_session_at, next_session_at,
     *  last_follow_up_required, last_follow_up_date,
     *  latest_active_session_at (internal), follow_up_status.
     *
     * The aggregates are computed in an inner query and the follow-up
     * status in an outer one, because SQL can't reference a select alias
     * from the same SELECT's WHERE/ORDER BY.
     *
     * @return Builder<Student>
     */
    private function summaryQuery(): Builder
    {
        $latestCompleted = fn (string $column) => CounselingSession::query()
            ->select("counseling_sessions.{$column}")
            ->whereColumn('counseling_sessions.student_id', 'students.id')
            ->where('session_status', CounselingSession::STATUS_COMPLETED)
            ->orderByDesc('counseling_sessions.session_datetime')
            ->orderByDesc('counseling_sessions.id')
            ->limit(1);

        $aggregates = Student::query()
            ->withTrashed()
            ->select('students.*')
            ->withCount('counselingSessions')
            ->addSelect([
                'last_session_at' => $latestCompleted('session_datetime'),
                'last_follow_up_required' => $latestCompleted('follow_up_required'),
                'last_follow_up_date' => $latestCompleted('follow_up_date'),
                'latest_active_session_at' => CounselingSession::query()
                    ->selectRaw('MAX(counseling_sessions.session_datetime)')
                    ->whereColumn('counseling_sessions.student_id', 'students.id')
                    ->whereIn('session_status', [CounselingSession::STATUS_SCHEDULED, CounselingSession::STATUS_COMPLETED]),
                'next_session_at' => CounselingSession::query()
                    ->selectRaw('MIN(counseling_sessions.session_datetime)')
                    ->whereColumn('counseling_sessions.student_id', 'students.id')
                    ->where('session_status', CounselingSession::STATUS_SCHEDULED)
                    ->where('counseling_sessions.session_datetime', '>=', now()),
            ]);

        return Student::query()
            ->withTrashed()
            ->fromSub($aggregates, 'students')
            ->select('students.*')
            ->selectRaw("({$this->followUpStatusSql()}) AS follow_up_status", [now()->toDateString()])
            ->withCasts([
                'last_session_at' => 'datetime',
                'next_session_at' => 'datetime',
                'last_follow_up_required' => 'boolean',
                'last_follow_up_date' => 'date',
            ]);
    }

    /**
     * SQL CASE expression deriving follow-up status from the aggregate
     * columns. Takes exactly one binding: today's date (Y-m-d).
     */
    private function followUpStatusSql(): string
    {
        $none = self::FOLLOW_UP_NONE;
        $needed = self::FOLLOW_UP_NEEDED;
        $overdue = self::FOLLOW_UP_OVERDUE;

        return 'CASE WHEN last_follow_up_required = 1 AND latest_active_session_at <= last_session_at THEN '
            ."CASE WHEN last_follow_up_date IS NOT NULL AND last_follow_up_date < ? THEN '{$overdue}' ELSE '{$needed}' END "
            ."ELSE '{$none}' END";
    }
}
