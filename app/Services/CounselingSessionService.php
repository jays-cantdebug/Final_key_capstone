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

    /**
     * @return Collection<int, Student>
     */
    public function searchStudentsByName(string $search): Collection
    {
        return Student::query()
            ->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                    ->orWhereRaw("CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
            })
            ->orderBy('first_name')
            ->limit(20)
            ->get();
    }

    public function findStudentById(int $id): ?Student
    {
        return Student::query()->find($id);
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
     * filtered by student name.
     */
    public function paginate(?string $search, int $perPage = 10): LengthAwarePaginator
    {
        return CounselingSession::query()
            ->with(['student', 'counselor', 'assessment'])
            ->when($search, function ($query, string $value) {
                $query->whereHas('student', function ($q) use ($value) {
                    $q->where('first_name', 'like', "%{$value}%")
                        ->orWhere('last_name', 'like', "%{$value}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$value}%"])
                        ->orWhereRaw("CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?", ["%{$value}%"]);
                });
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
