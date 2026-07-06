<?php

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Course::query()->updateOrCreate(
            ['course_code' => 'BSIT'],
            [
                'course_name' => 'Bachelor of Science in Information Technology',
                'status' => Course::STATUS_ACTIVE,
            ]
        );

        Course::query()->updateOrCreate(
            ['course_code' => 'BSEd'],
            [
                'course_name' => 'Bachelor of Secondary Education',
                'status' => Course::STATUS_ACTIVE,
            ]
        );
    }
}