<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Section;
use App\Models\Student;
use App\Models\YearLevel;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $course = Course::query()->firstOrCreate(
            ['course_code' => 'BSIT'],
            ['course_name' => 'Bachelor of Science in Information Technology', 'status' => Course::STATUS_ACTIVE]
        );

        $yearLevel = YearLevel::query()->firstOrCreate(
            ['display_order' => 1],
            ['label' => '1st Year', 'status' => YearLevel::STATUS_ACTIVE]
        );

        $section = Section::query()->firstOrCreate(
            ['section_name' => 'A'],
            ['capacity' => 40, 'status' => Section::STATUS_ACTIVE]
        );

        Student::query()->updateOrCreate(
            ['student_number' => '2024-0001'],
            [
                'first_name' => 'Maria',
                'middle_name' => 'Santos',
                'last_name' => 'Reyes',
                'sex' => 'Female',
                'course_id' => $course->id,
                'year_level_id' => $yearLevel->id,
                'section_id' => $section->id,
                'status' => Student::STATUS_ACTIVE,
            ]
        );

        Student::query()->updateOrCreate(
            ['student_number' => '2024-0002'],
            [
                'first_name' => 'John',
                'middle_name' => null,
                'last_name' => 'Cruz',
                'sex' => 'Male',
                'course_id' => $course->id,
                'year_level_id' => $yearLevel->id,
                'section_id' => $section->id,
                'status' => Student::STATUS_ACTIVE,
            ]
        );
    }
}