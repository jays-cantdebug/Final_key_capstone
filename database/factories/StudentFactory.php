<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Section;
use App\Models\Student;
use App\Models\YearLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{
    /**
     * The model the factory corresponds to.
     *
     * @var class-string<\App\Models\Student>
     */
    protected $model = Student::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_number' => fake()->unique()->numerify('2024-#####'),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => fake()->randomElement(['Male', 'Female', 'Prefer not to say']),
            'course_id' => $this->courseId(),
            'year_level_id' => $this->yearLevelId(),
            'section_id' => $this->sectionId(),
        ];
    }

    private function courseId(): int
    {
        return Course::query()->firstOrCreate(
            ['course_code' => 'BSIT'],
            ['course_name' => 'Bachelor of Science in Information Technology', 'status' => Course::STATUS_ACTIVE]
        )->id;
    }

    private function yearLevelId(): int
    {
        return YearLevel::query()->firstOrCreate(
            ['display_order' => 1],
            ['label' => '1st Year', 'status' => YearLevel::STATUS_ACTIVE]
        )->id;
    }

    private function sectionId(): int
    {
        return Section::query()->firstOrCreate(
            ['section_name' => 'A'],
            ['capacity' => 40, 'status' => Section::STATUS_ACTIVE]
        )->id;
    }
}