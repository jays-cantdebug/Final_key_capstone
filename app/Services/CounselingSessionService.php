<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Assessment;
use App\Models\CounselingSession;
use App\Models\Student;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Collection;

/**
 * Manages Counseling Session records: creation, updates, deletion (soft),
 * and the search/listing needed to find an existing student and their
 * past assessments when scheduling a session.
 */
class CounselingSessionService
{
    public function __construct(private readonly DatabaseManager $database)
    {
    }

    public function findStudentByNumber(string $studentNumber): ?Student
    {
        return Student::query()->where('student_number', $studentNumber)->first();
    }

    /**
     * @return Collection<int, Assessment>
     */
    public function assessmentsForStudent(Student $student): Collection
    {
        return Assessment::query()
            ->where('student_id', $student->id)
            ->orderByDesc('submitted_at')
            ->get();
    }

    /**
     * Paginate counseling sessions, most recent first, optionally
     * filtered by student number.
     */
    public function paginate(?string $studentNumber, int $perPage = 10): LengthAwarePaginator
    {
        return CounselingSession::query()
            ->with(['student', 'counselor', 'assessment'])
            ->when($studentNumber, function ($query, string $value) {
                $query->whereHas('student', fn ($q) => $q->where('student_number', 'like', "%{$value}%"));
            })
            ->orderByDesc('session_datetime')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, User $counselor): CounselingSession
    {
        return $this->database->transaction(function () use ($data, $counselor): CounselingSession {
            return CounselingSession::query()->create([
                ...$data,
                'counselor_id' => $counselor->id,
            ]);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(CounselingSession $session, array $data): CounselingSession
    {
        return $this->database->transaction(function () use ($session, $data): CounselingSession {
            $session->update($data);

            return $session->refresh();
        });
    }

    public function delete(CounselingSession $session): void
    {
        $this->database->transaction(static function () use ($session): void {
            $session->delete();
        });
    }
}
