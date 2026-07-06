<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\Section;
use App\Models\Student;
use App\Models\YearLevel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\DatabaseManager;

class StudentService
{
    public function __construct(private readonly DatabaseManager $database)
    {
    }

    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return Student::query()
            ->with(['course', 'yearLevel', 'section'])
            ->orderBy('student_number')
            ->paginate($perPage);
    }

    /**
    * @return array<string, array<int, mixed>>
     */
    public function formData(): array
    {
        return [
            'courses' => Course::query()->orderBy('course_code')->get(),
            'yearLevels' => YearLevel::query()->orderBy('display_order')->get(),
            'sections' => Section::query()->orderBy('section_name')->get(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Student
    {
        return $this->database->transaction(fn (): Student => Student::query()->create($data));
    }

    /**
     * @param array<string, mixed> $data
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
}