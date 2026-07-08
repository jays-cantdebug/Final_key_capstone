<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\QuestionnaireVersion;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    protected $model = Assessment::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'questionnaire_version_id' => QuestionnaireVersion::factory()->active(),
            'psychometrician_id' => User::factory()->psychometrician(),
            'status' => Assessment::STATUS_COMPLETED,
            'submitted_at' => now(),
        ];
    }
}
