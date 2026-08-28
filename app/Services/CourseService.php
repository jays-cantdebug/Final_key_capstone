<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\LookupRecordInUseException;
use App\Models\Course;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Collection;

/**
 * Handles persistence and retrieval of Course records.
 */
class CourseService
{
    public function __construct(private readonly DatabaseManager $database) {}

    /**
     * @return Collection<int, Course>
     */
    public function all(): Collection
    {
        return Course::query()->orderBy('course_code')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Course
    {
        return $this->database->transaction(fn (): Course => Course::query()->create($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Course $course, array $data): Course
    {
        return $this->database->transaction(function () use ($course, $data): Course {
            $course->update($data);

            return $course->refresh();
        });
    }

    /**
     * Archive (soft-delete) a course.
     *
     * @throws LookupRecordInUseException if any student still references
     *                                    this course — deactivate instead.
     */
    public function delete(Course $course): void
    {
        if ($course->students()->exists()) {
            throw new LookupRecordInUseException(
                'Cannot delete a course referenced by existing students. Deactivate it instead.'
            );
        }

        $this->database->transaction(static fn (): bool => (bool) $course->delete());
    }
}
