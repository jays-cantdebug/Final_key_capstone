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
    public function __construct(
        private readonly DatabaseManager $database,
    ) {
    }

    /**
     * Students are only ever created through New Assessment Step 1
     * (AssessmentService::registerStudent()) — this listing supports
     * View, Search, Edit, and Assessment History only, never creation.
     */
    public function paginate(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return Student::query()
            ->with(['course', 'yearLevel', 'section'])
            ->when($search, function ($query, string $value) {
                $query->where(function ($q) use ($value) {
                    $q->where('first_name', 'like', "%{$value}%")
                        ->orWhere('last_name', 'like', "%{$value}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$value}%"])
                        ->orWhereRaw("CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?", ["%{$value}%"]);
                });
            })
            ->orderBy('student_number')
            ->paginate($perPage)
            ->withQueryString();
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