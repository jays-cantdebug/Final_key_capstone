<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DuplicateStudentException;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\Section;
use App\Models\Student;
use App\Models\YearLevel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class StudentService
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly StudentDuplicateService $duplicateService,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * Students are only ever created through New Assessment Step 1
     * (AssessmentService::registerStudent()) — this listing supports
     * View, Search, Edit, and Assessment History only, never creation.
     *
     * Newest registered student first: `created_at` descending, with `id`
     * descending as a tie-breaker so students created in the same second
     * (e.g. seeded rows) keep a stable order across pages. Not ordered by
     * `student_number`, which is a string. `created_at` is set once at
     * registration — a Take Again retake or an edit never changes it, so
     * neither moves a student up the list. Used by both the full page and
     * the live-search partial, so every page and every search share this order.
     */
    public function paginate(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->searchByName(Student::query(), $search)
            ->with(['course', 'yearLevel', 'section'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Archived (soft-deleted) students, most recently archived first, with
     * each one's assessment count and latest assessment date. Same name
     * search as the Students list.
     */
    public function paginateArchived(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->searchByName(Student::onlyTrashed(), $search)
            ->with(['course', 'yearLevel', 'section'])
            ->addSelect([
                'assessments_count' => Assessment::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('assessments.student_id', 'students.id'),
                'latest_assessment_at' => Assessment::query()
                    ->selectRaw('MAX(assessments.submitted_at)')
                    ->whereColumn('assessments.student_id', 'students.id'),
            ])
            ->withCasts([
                'assessments_count' => 'integer',
                'latest_assessment_at' => 'datetime',
            ])
            ->orderByDesc('deleted_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * For each given archived student, the active students with the same
     * name (StudentDuplicateService's rule, the one the wizard uses). A
     * non-empty entry means restoring that student would create a second
     * active record for the same person, so Restore is refused.
     *
     * @param  iterable<int, Student>  $students
     * @return array<int, Collection<int, Student>> keyed by archived student id
     */
    public function activeNameMatches(iterable $students): array
    {
        $matches = [];

        foreach ($students as $student) {
            $matches[$student->id] = $this->duplicateService->findMatches(
                $student->first_name,
                $student->middle_name,
                $student->last_name,
            )['active'];
        }

        return $matches;
    }

    /**
     * Restore an archived student, keeping their student number, and record
     * one "Restore" audit entry with the optional reason.
     *
     * restoreQuietly() skips model events, so AuditableObserver doesn't log
     * an "Update" (deleted_at cleared) plus its own "Restore"; the single
     * entry is written here instead, like "Archived Match Confirmed" in
     * AssessmentService::save().
     *
     * @return bool false when the student was already active (e.g. a double
     *              submit), in which case nothing is changed or logged.
     *
     * @throws DuplicateStudentException if an active student has the same name.
     */
    public function restore(Student $student, ?string $reason): bool
    {
        return $this->database->transaction(function () use ($student, $reason): bool {
            $student = Student::withTrashed()->lockForUpdate()->findOrFail($student->id);

            if (! $student->trashed()) {
                return false;
            }

            $activeMatches = $this->activeNameMatches([$student])[$student->id];

            if ($activeMatches->isNotEmpty()) {
                throw new DuplicateStudentException($activeMatches->modelKeys());
            }

            $archivedAt = $student->deleted_at?->toDateTimeString();
            $student->restoreQuietly();

            $this->auditLogService->record(
                'Student Information',
                'Restore',
                $student->id,
                ['archived_at' => $archivedAt],
                [...$student->getAttributes(), 'restore_reason' => $reason],
            );

            return true;
        });
    }

    /**
     * Options for the edit form. Archived courses, year levels and sections
     * are left out, except the student's own current ones (e.g. a restored
     * student whose course was archived meanwhile), so saving the form
     * never forces a change the Psychometrician didn't make. The view marks
     * those "(archived)".
     *
     * @return array<string, Collection<int, Model>>
     */
    public function formData(?Student $student = null): array
    {
        return [
            'courses' => $this->withCurrent(Course::query()->orderBy('course_code')->get(), $student?->course, 'course_code'),
            'yearLevels' => $this->withCurrent(YearLevel::query()->orderBy('display_order')->get(), $student?->yearLevel, 'display_order'),
            'sections' => $this->withCurrent(Section::query()->orderBy('section_name')->get(), $student?->section, 'section_name'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Student $student, array $data): Student
    {
        return $this->database->transaction(function () use ($student, $data): Student {
            $student->update($data);

            return $student->refresh();
        });
    }

    public function delete(Student $student): void
    {
        $this->database->transaction(static fn (): bool => (bool) $student->delete());
    }

    /**
     * @param  Builder<Student>  $query
     * @return Builder<Student>
     */
    private function searchByName(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $query, string $value) {
            $query->where(function (Builder $q) use ($value) {
                $q->where('first_name', 'like', "%{$value}%")
                    ->orWhere('last_name', 'like', "%{$value}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$value}%"])
                    ->orWhereRaw("CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?", ["%{$value}%"]);
            });
        });
    }

    /**
     * @param  Collection<int, Model>  $options
     * @return Collection<int, Model>
     */
    private function withCurrent(Collection $options, ?Model $current, string $sortKey): Collection
    {
        if ($current === null || $options->contains($current)) {
            return $options;
        }

        return $options->push($current)->sortBy($sortKey)->values();
    }
}
