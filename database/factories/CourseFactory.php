<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        return [
            'course_code' => strtoupper($this->faker->unique()->lexify('????')),
            'course_name' => $this->faker->words(3, true),
            'status' => Course::STATUS_ACTIVE,
        ];
    }
}
