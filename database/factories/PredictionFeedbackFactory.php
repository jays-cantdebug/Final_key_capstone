<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\PredictionFeedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PredictionFeedback>
 */
class PredictionFeedbackFactory extends Factory
{
    protected $model = PredictionFeedback::class;

    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'psychometrician_id' => User::factory()->psychometrician(),
            'is_confirmed' => true,
            'corrected_depression_level' => null,
            'corrected_anxiety_level' => null,
            'corrected_stress_level' => null,
            'notes' => null,
        ];
    }
}
